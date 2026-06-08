/**
 * api-cache.js
 * Simple API fetching with localStorage caching (stale-while-revalidate).
 */

const API_BASE = 'http://localhost:8000';

// ── Service Worker — caches only CSS/JS to eliminate refresh flash ────────────
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js').catch(function() {});
    });
}

// ── Offline check ─────────────────────────────────────────────────────────
(function checkStatus() {
    if (window.location.pathname.includes('maintenance.html')) return;
    // Only redirect to maintenance after 2 failed attempts with a delay
    // Prevents false positives on hard refresh when server is briefly busy
    var attempts = 0;
    function check() {
        fetch(API_BASE + '/api/status', { cache: 'no-store' })
            .then(function(r) { /* server is up, do nothing */ })
            .catch(function() {
                attempts++;
                if (attempts >= 2) {
                    window.location.replace('/maintenance.html');
                } else {
                    setTimeout(check, 1500);
                }
            });
    }
    check();
})();

// ── Scroll position save/restore ─────────────────────────────────────────
(function() {
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
    window.addEventListener('load', function() { window.scrollTo(0, 0); });

    window.addEventListener('beforeunload', function() {
        sessionStorage.setItem('_scrollY', window.scrollY);
    });

    window.addEventListener('load', function() {
        var nav = performance.getEntriesByType('navigation')[0];
        var savedY = parseInt(sessionStorage.getItem('_scrollY') || '0', 10);
        if (nav && nav.type === 'reload' && savedY > 0) {
            setTimeout(function() {
                window.scrollTo({ top: savedY, behavior: 'smooth' });
            }, 120);
        } else {
            sessionStorage.removeItem('_scrollY');
        }
    });
})();

// ── Global search autocomplete ────────────────────────────────────────────
(function initGlobalSearch() {
    document.addEventListener('DOMContentLoaded', function() {
        var input = document.getElementById('hamper-search');
        if (!input) return;

        if (window.__searchInitialized__) return;
        window.__searchInitialized__ = true;

        var dropdown = document.getElementById('search-dropdown');
        if (!dropdown) {
            dropdown = document.createElement('div');
            dropdown.id = 'search-dropdown';
            dropdown.className = 'search-dropdown';
            document.body.appendChild(dropdown);
        }

        var allProducts = [];

        fetchCached('/api/products', 'all_products', function(data) {
            if (data && data.length) allProducts = data;
        });

        function positionDropdown() {
            var rect = input.getBoundingClientRect();
            dropdown.style.top = (rect.bottom + 4) + 'px';
            dropdown.style.left = rect.left + 'px';
            dropdown.style.width = rect.width + 'px';
        }

        function getMatches(query) {
            var words = query.toLowerCase().split(/\s+/).filter(Boolean);
            return allProducts.filter(function(h) {
                var s = [h.name, h.badge || '', h.description || ''].join(' ').toLowerCase();
                return words.every(function(w) { return s.includes(w); });
            });
        }

        function showDropdown(query) {
            if (!query) { dropdown.innerHTML = ''; dropdown.classList.remove('open'); return; }
            var matches = getMatches(query);
            if (!matches.length) {
                dropdown.innerHTML = '<div class="search-dd-item search-dd-none">No results for "' + query + '"</div>';
            } else {
                dropdown.innerHTML = matches.map(function(h) {
                    return '<div class="search-dd-item" onclick="goToSearch(\'' + encodeURIComponent(h.name) + '\')">'
                        + '<div><div class="search-dd-name">' + h.name + '</div>'
                        + (h.badge ? '<div class="search-dd-badge">' + h.badge + '</div>' : '')
                        + '</div></div>';
                }).join('');
                dropdown.innerHTML += '<div class="search-dd-item search-dd-all" onclick="goToSearch(\'' + encodeURIComponent(query) + '\')">'
                    + '<i class="fas fa-search"></i> See all results for "<strong>' + query + '</strong>"</div>';
            }
            positionDropdown();
            dropdown.classList.add('open');
        }

        window.goToSearch = function(q) {
            if (typeof window.goSearch === 'function') { window.goSearch(q); return; }
            var isInPages = location.pathname.includes('/pages/');
            window.location.href = (isInPages ? '' : 'pages/') + 'search.html?q=' + q;
        };

        window.addEventListener('resize', function() { if (dropdown.classList.contains('open')) positionDropdown(); });
        input.addEventListener('input', function() { showDropdown(input.value.trim()); });
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { var q = input.value.trim(); if (q) { dropdown.innerHTML = ''; dropdown.classList.remove('open'); window.goToSearch(encodeURIComponent(q)); } }
            if (e.key === 'Escape') { dropdown.innerHTML = ''; dropdown.classList.remove('open'); }
        });
        document.addEventListener('click', function(e) {
            var wrap = input.closest('.header-search-wrap');
            if (wrap && !wrap.contains(e.target)) { dropdown.innerHTML = ''; dropdown.classList.remove('open'); }
        });
    });
})();

const CACHE_TTL_SHORT  = 30 * 1000;
const CACHE_TTL_LONG   = 5 * 60 * 1000;
const CACHE_TTL = CACHE_TTL_LONG;

const CACHE_TTL_MAP = {
    'featured_products': CACHE_TTL_SHORT,
    'all_products':      CACHE_TTL_SHORT,
    'reviews':           CACHE_TTL_LONG,
    'faqs':              CACHE_TTL_LONG,
    'banners':           CACHE_TTL_LONG,
    'terms':             CACHE_TTL_LONG,
    'privacy':           CACHE_TTL_LONG,
    'about_hero_subtitle':  CACHE_TTL_LONG,
    'about_who_label':      CACHE_TTL_LONG,
    'about_section_title':  CACHE_TTL_LONG,
    'about_story_p1':       CACHE_TTL_LONG,
    'about_story_p2':       CACHE_TTL_LONG,
    'about_story_p3':       CACHE_TTL_LONG,
    'about_cta_title':      CACHE_TTL_LONG,
    'about_cta_subtitle':   CACHE_TTL_LONG,
    'logo_wide':            CACHE_TTL_LONG,
    'logo_square':          CACHE_TTL_LONG,
    'top_bar_text':         CACHE_TTL_LONG,
    'hero_title':           CACHE_TTL_LONG,
    'hero_subtitle':        CACHE_TTL_LONG,
};

function cacheGet(key) {
    try {
        const item = localStorage.getItem('ac_' + key);
        if (!item) return null;
        const parsed = JSON.parse(item);
        const ttl = CACHE_TTL_MAP[key] ?? CACHE_TTL;
        return { data: parsed.data, stale: Date.now() - parsed.ts > ttl };
    } catch { return null; }
}

function cacheSet(key, data) {
    try {
        localStorage.setItem('ac_' + key, JSON.stringify({ data, ts: Date.now() }));
    } catch {}
}

function cacheInvalidate(key) {
    try { localStorage.removeItem('ac_' + key); } catch {}
}

function cacheInvalidateAll() {
    try {
        Object.keys(localStorage)
            .filter(k => k.startsWith('ac_'))
            .forEach(k => localStorage.removeItem(k));
    } catch {}
}

function fetchCached(url, cacheKey, onData) {
    const cached = cacheGet(cacheKey);
    let cachedStr = null;

    if (cached && cached.data !== undefined) {
        cachedStr = JSON.stringify(cached.data);
        onData(cached.data, true);
        if (!cached.stale) return;
    }

    fetch(API_BASE + url)
        .then(r => r.json())
        .then(json => {
            if (json.success !== false) {
                const fresh = json.data !== undefined ? json.data : json;
                const freshStr = JSON.stringify(fresh);
                cacheSet(cacheKey, fresh);
                if (freshStr !== cachedStr) {
                    onData(fresh, false);
                }
            }
        })
        .catch(() => {});
}

// ── Dynamic logo loader ───────────────────────────────────────────────────
(function loadSiteLogos() {
    fetchCached('/api/content/logo_wide', 'logo_wide', function(data) {
        if (!data || !data.value || !data.value.startsWith('http')) return;
        document.querySelectorAll('img.logo-wide, img#site-logo-wide').forEach(function(el) {
            if (el.src !== data.value) el.src = data.value;
        });
    });
    fetchCached('/api/content/logo_square', 'logo_square', function(data) {
        if (!data || !data.value || !data.value.startsWith('http')) return;
        document.querySelectorAll('img#site-logo-square').forEach(function(el) {
            if (el.src !== data.value) el.src = data.value;
        });
    });
    fetchCached('/api/content/top_bar_text', 'top_bar_text', function(data) {
        if (!data || !data.value) return;
        var el = document.getElementById('top-bar-text');
        if (el && el.textContent !== data.value) el.textContent = data.value;
    });
})();

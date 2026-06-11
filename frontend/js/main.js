// Header scroll + scroll-to-top
window.addEventListener('scroll', () => {
    const header = document.getElementById('header');
    const scrollTop = document.getElementById('scrollTop');
    if (window.scrollY > 60) {
        header?.classList.add('scrolled');
        scrollTop?.classList.add('visible');
    } else {
        header?.classList.remove('scrolled');
        scrollTop?.classList.remove('visible');
    }
});

// Live hampers array populated from API
let hampers = [];

function hamperCardHTML(h, index) {
    const inStock = h.stock > 0;
    const stockLabel = h.stock === 0
        ? `<span class="stock-pill out">Out of Stock</span>`
        : h.stock <= 3
        ? `<span class="stock-pill low">Only ${h.stock} left</span>`
        : `<span class="stock-pill in">${h.stock} in stock</span>`;
    return `
    <div class="product-card ${!inStock ? 'out-of-stock' : ''}">
        <div class="product-img">
            <img src="${h.img}" alt="${h.name}" loading="eager" style="width:100%;height:100%;object-fit:cover;display:block;">
            ${h.badge ? `<span class="product-badge">${h.badge}</span>` : ''}
        </div>
        <div class="product-body">
            ${h.badge ? `<div class="product-tag">${h.badge}</div>` : ''}
            <div class="product-name">${h.name}</div>
            <div class="product-desc">${h.desc}</div>
        </div>
        <div class="product-footer">
            ${stockLabel}
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
                <span style="font-weight:700;font-size:1rem;color:var(--text-dark);">${h.price}</span>
                <button class="btn-add" ${!inStock ? 'disabled' : ''} onclick="event.stopPropagation(); ${inStock ? `addToCart(${index})` : ''}">
                    ${inStock ? 'Add to Cart' : 'Unavailable'}
                </button>
            </div>
        </div>
    </div>`;
}

// Skeleton cards shown while loading
function renderSkeletons(count) {
    const grid = document.getElementById('products-grid');
    if (!grid || grid.children.length > 0) return;
    grid.innerHTML = Array(count).fill(`
        <div class="product-card" style="pointer-events:none;">
            <div class="product-img" style="background:var(--gold-pale);animation:pulse 1.5s ease-in-out infinite;"></div>
            <div class="product-body">
                <div style="height:14px;background:var(--gold-pale);border-radius:4px;width:40%;margin-bottom:8px;animation:pulse 1.5s ease-in-out infinite;"></div>
                <div style="height:18px;background:var(--gold-pale);border-radius:4px;width:80%;margin-bottom:8px;animation:pulse 1.5s ease-in-out infinite;"></div>
                <div style="height:12px;background:var(--gold-pale);border-radius:4px;width:60%;animation:pulse 1.5s ease-in-out infinite;"></div>
            </div>
        </div>
    `).join('');
}

function renderHampers() {
    const grid = document.getElementById('products-grid');
    if (!grid) return;

    // If SSR already populated the grid, sync hampers array but also
    // do a background fetch to catch any stock changes since SSR was rendered
    const ssrProducts = window.__SSR__ && window.__SSR__.products;
    if (grid.children.length > 0 && ssrProducts && ssrProducts.length) {
        hampers = ssrProducts.map((p, i) => ({
            id: p.id, name: p.name, desc: p.description || '',
            img: p.image_url || '', badge: p.badge || null,
            stock: p.stock,
            price: 'MVR ' + parseFloat(p.price_mvr).toLocaleString(),
            price_raw: p.price_mvr
        }));
        // Update cache silently in background without re-rendering
        fetch(API_BASE + '/api/products/featured')
            .then(r => r.json())
            .then(json => { if (json.success !== false) { const fresh = json.data !== undefined ? json.data : json; cacheSet('featured_products', fresh); } })
            .catch(() => {});
        return;
    }

    function applyProducts(data) {
        if (!data || !data.length) return;
        hampers = data.map(p => ({
            id: p.id, name: p.name, desc: p.description || '',
            img: p.image_url || '', badge: p.badge || null,
            stock: p.stock,
            price: 'MVR ' + parseFloat(p.price_mvr).toLocaleString(),
            price_raw: p.price_mvr
        }));
        grid.innerHTML = hampers.map((h, i) => hamperCardHTML(h, i)).join('');
    }

    fetchCached('/api/products/featured', 'featured_products', applyProducts);
}renderHampers();

// ── Search ────────────────────────────────────────────────────────
(function initSearch() {
    window.__searchInitialized__ = true; // prevent global search from running again
    const input = document.getElementById('hamper-search');
    if (!input) return;

    let dropdown = document.getElementById('search-dropdown');
    if (!dropdown) {
        dropdown = document.createElement('div');
        dropdown.id = 'search-dropdown';
        dropdown.className = 'search-dropdown';
        document.body.appendChild(dropdown);
    }

    function getMatches(query) {
        const words = query.toLowerCase().split(/\s+/).filter(Boolean);
        return hampers.filter(h => {
            const searchable = [h.name, h.badge || '', h.desc].join(' ').toLowerCase();
            return words.every(w => searchable.includes(w));
        });
    }

    function positionDropdown() {
        const rect = input.getBoundingClientRect();
        dropdown.style.top = (rect.bottom + 4) + 'px';
        dropdown.style.left = rect.left + 'px';
        dropdown.style.width = rect.width + 'px';
    }

    function showDropdown(query) {
        if (!query) { dropdown.innerHTML = ''; dropdown.classList.remove('open'); return; }
        const matches = getMatches(query);
        if (!matches.length) {
            dropdown.innerHTML = `<div class="search-dd-item search-dd-none">No results for "${query}"</div>`;
        } else {
            dropdown.innerHTML = matches.map(h => `
                <div class="search-dd-item" onclick="goSearch('${encodeURIComponent(h.name)}')">
                    <div>
                        <div class="search-dd-name">${h.name}</div>
                        ${h.badge ? `<div class="search-dd-badge">${h.badge}</div>` : ''}
                    </div>
                </div>
            `).join('');
            dropdown.innerHTML += `<div class="search-dd-item search-dd-all" onclick="goSearch('${encodeURIComponent(query)}')">
                <i class="fas fa-search"></i> See all results for "<strong>${query}</strong>"
            </div>`;
        }
        positionDropdown();
        dropdown.classList.add('open');
    }

    window.addEventListener('resize', () => { if (dropdown.classList.contains('open')) positionDropdown(); });
    input.addEventListener('input', () => showDropdown(input.value.trim()));
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') { const q = input.value.trim(); if (q) { dropdown.innerHTML = ''; dropdown.classList.remove('open'); goSearch(encodeURIComponent(q)); } }
        if (e.key === 'Escape') { dropdown.innerHTML = ''; dropdown.classList.remove('open'); }
    });
    document.addEventListener('click', e => {
        if (!input.closest('.header-search-wrap').contains(e.target)) { dropdown.innerHTML = ''; dropdown.classList.remove('open'); }
    });
})();

function goSearch(encodedQuery) {
    window.location.href = 'pages/search.html?q=' + encodedQuery;
}

// ── Reviews ───────────────────────────────────────────────────────
function renderReviews() {
    const grid = document.getElementById('reviews-grid');
    if (!grid) return;

    // If SSR already populated the grid, just update cache in background
    if (grid.children.length > 0) {
        fetchCached('/api/reviews', 'reviews', () => {});
        return;
    }

    function applyReviews(data) {
        if (!data || !data.length) { grid.innerHTML = ''; return; }
        const cardHTML = r => `
            <div class="testimonial-card">
                <div class="stars">${'\u2605'.repeat(r.rating)}${'\u2606'.repeat(5 - r.rating)}</div>
                <p class="testimonial-text">${r.text}</p>
                <div class="testimonial-author">
                    <div class="author-avatar" style="background:#b8a898;">${(r.reviewer_name || 'A')[0].toUpperCase()}</div>
                    <div>
                        <div class="author-name">${r.reviewer_name}</div>
                        <div class="author-loc">${r.reviewer_location || ''}</div>
                    </div>
                </div>
            </div>`;
        let items = [...data];
        while (items.length < 8) items = [...items, ...data];
        const half = items.map(cardHTML).join('');
        grid.innerHTML = half + half;
        const duration = items.length * 4;
        grid.style.animationDuration = duration + 's';
    }

    fetchCached('/api/reviews', 'reviews', applyReviews);
}

renderReviews();

// ── Modal ──────────────────────────────────────────────────────────
let currentHamper = null;

function openModal(index) {
    currentHamper = hampers[index];
    if (!currentHamper) return;
    document.getElementById('modal-title').textContent = currentHamper.name;
    document.getElementById('modal-tag').textContent = currentHamper.desc;
    document.getElementById('modal-sender').value = '';
    document.getElementById('modal-recipient').value = '';
    document.getElementById('modal-message').value = '';
    document.getElementById('modal-overlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('modal-overlay').classList.remove('open');
    document.body.style.overflow = '';
}

function closeModalOnBg(e) {
    if (e.target === document.getElementById('modal-overlay')) closeModal();
}

function handleEnquiry() {
    const name = document.getElementById('modal-sender').value.trim();
    const email = document.getElementById('modal-recipient').value.trim();
    if (!name || !email) { alert('Please fill in your name and email address.'); return; }
    const subject = encodeURIComponent('Hamper Enquiry ' + currentHamper.name);
    const body = encodeURIComponent('Hi Aura Gifts,\n\nI am interested in: ' + currentHamper.name + '\n\nName: ' + name + '\nEmail: ' + email + '\n\nMessage:\n' + document.getElementById('modal-message').value);
    window.location.href = 'mailto:aura.gifts.mv@gmail.com?subject=' + subject + '&body=' + body;
    closeModal();
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal(); closeCart(); } });

// ── addToCart ─────────────────────────────────────────────────────
function addToCart(index) {
    const h = hampers[index];
    const existing = cart.find(item => item.index === index);
    const currentQty = existing ? existing.qty : 0;
    if (currentQty >= h.stock) { showWarningToast(`Only ${h.stock} of "${h.name}" available`); return; }
    if (existing) { existing.qty += 1; }
    else { cart.push({ index, name: h.name, price: h.price, img: h.img, qty: 1, stock: h.stock }); }
    saveCart(); updateCartCount(); renderCart(); showCartToast(h.name);
}

function showCartToast(name) { _showToast(name + ' added to cart', 'check-circle', false); }
function showWarningToast(msg) { _showToast(msg, 'exclamation-circle', true); }

function _showToast(text, icon, isWarning) {
    const old = document.getElementById('cart-toast');
    if (old) old.remove();
    const toast = document.createElement('div');
    toast.id = 'cart-toast';
    toast.className = 'cart-toast' + (isWarning ? ' warning' : '');
    toast.innerHTML = `<div class="cart-toast-label"><i class="fas fa-${icon}"></i><span>${text}</span></div><div class="cart-toast-bar-wrap"><div class="cart-toast-bar"></div></div>`;
    document.body.appendChild(toast);
    requestAnimationFrame(() => requestAnimationFrame(() => toast.classList.add('show')));
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, 1800);
}

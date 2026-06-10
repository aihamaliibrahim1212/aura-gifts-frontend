/**
 * auth.js — Aura Gifts customer authentication utility
 * Shared across all pages. Handles token storage, user state,
 * Google Sign-In, header nav updates, and cart sync.
 *
 * NOTE: The initial #auth-nav-btn render is handled by an inline <script>
 * injected directly into each HTML page — that runs synchronously before
 * first paint. auth.js (deferred) handles updates after that.
 */

// ─── Constants ───────────────────────────────────────────────────────────────
const AUTH_TOKEN_KEY = 'aura_user_token';
const AUTH_USER_KEY  = 'aura_user';

// ─── Token helpers ────────────────────────────────────────────────────────────
function authGetToken() {
    return localStorage.getItem(AUTH_TOKEN_KEY);
}

function authGetUser() {
    try { return JSON.parse(localStorage.getItem(AUTH_USER_KEY)); } catch { return null; }
}

function authSetSession(token, user) {
    localStorage.setItem(AUTH_TOKEN_KEY, token);
    localStorage.setItem(AUTH_USER_KEY, JSON.stringify(user));
}

function authClearSession() {
    localStorage.removeItem(AUTH_TOKEN_KEY);
    localStorage.removeItem(AUTH_USER_KEY);
}

function authIsLoggedIn() {
    return !!authGetToken();
}

// ─── API helper (auth-aware) ──────────────────────────────────────────────────
async function authFetch(url, method = 'GET', body = null) {
    const fullUrl = url.startsWith('http') ? url : (typeof API_BASE !== 'undefined' ? API_BASE : '') + url;
    const opts = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        credentials: 'include',
    };
    const token = authGetToken();
    if (token) opts.headers['Authorization'] = 'Bearer ' + token;
    if (body !== null) opts.body = JSON.stringify(body);

    const res = await fetch(fullUrl, opts);
    const json = await res.json();

    if (res.status === 401) {
        authClearSession();
        authUpdateNav();
    }

    return { ok: res.ok, status: res.status, data: json };
}

// ─── Logout ───────────────────────────────────────────────────────────────────
async function authLogout(redirectTo) {
    try {
        await authFetch('/api/user/logout', 'POST');
    } catch (_) {}
    authClearSession();
    authUpdateNav();

    // Merge cart back to localStorage on logout (so items aren't lost)
    if (redirectTo) {
        window.location.href = redirectTo;
    } else {
        window.location.reload();
    }
}

// ─── Cart sync ────────────────────────────────────────────────────────────────
// When a user logs in, merge server-side saved cart with local cart.
async function authSyncCartOnLogin() {
    if (!authIsLoggedIn()) return;
    try {
        const res = await authFetch('/api/user/cart');
        if (!res.ok || !res.data.success) return;

        const serverItems = res.data.data.items || [];
        const localCart   = JSON.parse(localStorage.getItem('aura_cart') || '[]');

        if (serverItems.length > 0 && localCart.length === 0) {
            // No local cart — restore server cart
            localStorage.setItem('aura_cart', JSON.stringify(serverItems));
        } else if (localCart.length > 0) {
            // Merge: server items + local items, deduplicating by name
            const merged = [...localCart];
            serverItems.forEach(serverItem => {
                const exists = merged.find(i => i.name === serverItem.name);
                if (!exists) merged.push(serverItem);
            });
            localStorage.setItem('aura_cart', JSON.stringify(merged));
            // Push merged cart back to server
            await authFetch('/api/user/cart', 'PUT', { items: merged });
        }

        // Refresh cart count display if cart.js is loaded
        if (typeof updateCartCount === 'function') updateCartCount();
    } catch (_) {}
}

// Push current localStorage cart to server (called on checkout / cart changes)
async function authSaveCartToServer() {
    if (!authIsLoggedIn()) return;
    try {
        const cart = JSON.parse(localStorage.getItem('aura_cart') || '[]');
        await authFetch('/api/user/cart', 'PUT', { items: cart });
    } catch (_) {}
}

// ─── Navigation update ────────────────────────────────────────────────────────
// Builds the innerHTML for #auth-nav-btn from the current user state.
// Called both from the inline boot script (sync, pre-paint) and from
// DOMContentLoaded (to handle mobile menu). Keep this function pure / side-effect free.
function _authNavHTML(user, isInPages) {
    return '<i class="fas fa-user" style="font-size:1rem;color:#787878;"></i>';
}

function authUpdateNav() {
    var user       = authGetUser();
    var isInPages  = window.location.pathname.includes('/pages/');

    // ── Desktop / header button ──
    var btn = document.getElementById('auth-nav-btn');
    if (btn) {
        btn.innerHTML = _authNavHTML(user, isInPages);

        // Remove old dropdown if exists
        var oldDropdown = document.getElementById('auth-dropdown');
        if (oldDropdown) oldDropdown.remove();

        if (user) {
            // When logged in, show dropdown on click
            btn.style.cursor = 'pointer';
            btn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                var dropdown = document.getElementById('auth-dropdown');
                if (dropdown) {
                    dropdown.remove();
                } else {
                    showAuthDropdown();
                }
            };
        } else if (typeof openAuthModal !== 'undefined') {
            // When logged out, open modal
            btn.onclick = openAuthModal;
        }
    }

    // ── Mobile menu ──
    var mobileMenu = document.getElementById('mobile-menu');
    if (mobileMenu) {
        var mobileAuthEl = document.getElementById('mobile-auth-link');
        if (mobileAuthEl) mobileAuthEl.remove();

        var a   = document.createElement('a');
        a.id    = 'mobile-auth-link';

        if (user) {
            a.href      = isInPages ? 'account.html' : 'pages/account.html';
            a.innerHTML = '<i class="fas fa-user-circle" style="margin-right:8px;color:var(--gold-dark);"></i> My Account (' + _esc((user.name || '').split(' ')[0]) + ')';
            a.onclick = function() {
                if (typeof closeMobileMenu === 'function') closeMobileMenu();
            };
        } else {
            a.href      = '#';
            a.innerHTML = '<i class="fas fa-sign-in-alt" style="margin-right:8px;color:var(--gold-dark);"></i> Sign In / Register';
            a.onclick   = function(e) {
                e.preventDefault();
                if (typeof openAuthModal !== 'undefined') openAuthModal();
                if (typeof closeMobileMenu === 'function') closeMobileMenu();
            };
        }

        var lastLink = mobileMenu.querySelector('nav a:last-child');
        if (lastLink) {
            lastLink.insertAdjacentElement('afterend', a);
        } else {
            mobileMenu.appendChild(a);
        }
    }
}

function showAuthDropdown() {
    var isInPages = window.location.pathname.includes('/pages/');
    var accountHref = isInPages ? 'account.html' : 'pages/account.html';
    var user = authGetUser();

    var dropdown = document.createElement('div');
    dropdown.id = 'auth-dropdown';

    // Responsive positioning: adjust for mobile/tablet only
    var topVal = 90, rightVal = 90;
    if (window.innerWidth < 768) {
        // Mobile: align with banner top line
        topVal = 108;
        rightVal = 16;
    } else if (window.innerWidth < 1024) {
        // Tablet: slight adjustment
        topVal = 88;
        rightVal = 85;
    }

    dropdown.style.cssText = 'position:fixed;top:' + topVal + 'px;right:' + rightVal + 'px;background:#fff;border:1.5px solid #e0d8cc;box-shadow:0 4px 16px rgba(0,0,0,0.12);z-index:9999;min-width:220px;overflow:hidden;';

    // User header
    var header = document.createElement('div');
    header.style.cssText = 'padding:14px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:10px;background:#faf8f5;';

    var avatar;
    if (user && user.avatar_url) {
        avatar = document.createElement('img');
        avatar.src = user.avatar_url;
        avatar.style.cssText = 'width:36px;height:36px;border-radius:50%;object-fit:cover;border:1.5px solid #e0d8cc;flex-shrink:0;';
    } else {
        avatar = document.createElement('span');
        avatar.style.cssText = 'width:36px;height:36px;border-radius:50%;background:#b8a898;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.75rem;color:#fff;flex-shrink:0;';
        avatar.textContent = _initials(user ? user.name : '?');
    }
    header.appendChild(avatar);

    var nameDiv = document.createElement('div');
    nameDiv.style.cssText = 'display:flex;flex-direction:column;min-width:0;';
    var nameSpan = document.createElement('div');
    nameSpan.style.cssText = 'font-weight:600;font-size:0.9rem;color:#1a1a1a;word-break:break-word;';
    nameSpan.textContent = user ? user.name : 'User';
    nameDiv.appendChild(nameSpan);
    header.appendChild(nameDiv);

    dropdown.appendChild(header);

    // Account link
    var accountLink = document.createElement('a');
    accountLink.href = accountHref;
    accountLink.style.cssText = 'display:block;padding:12px 14px;color:#1a1a1a;text-decoration:none;font-size:0.9rem;border-bottom:1px solid #f0f0f0;transition:background 0.2s;';
    accountLink.innerHTML = '<i class="fas fa-user" style="margin-right:8px;color:#b8a898;width:16px;"></i>Account';
    accountLink.onmouseover = function() { this.style.background = '#f5f1eb'; };
    accountLink.onmouseout = function() { this.style.background = 'none'; };

    // Logout link
    var logoutLink = document.createElement('a');
    logoutLink.href = '#';
    logoutLink.style.cssText = 'display:block;padding:12px 14px;color:#1a1a1a;text-decoration:none;font-size:0.9rem;transition:background 0.2s;cursor:pointer;';
    logoutLink.innerHTML = '<i class="fas fa-sign-out-alt" style="margin-right:8px;color:#b8a898;width:16px;"></i>Logout';
    logoutLink.onmouseover = function() { this.style.background = '#f5f1eb'; };
    logoutLink.onmouseout = function() { this.style.background = 'none'; };
    logoutLink.onclick = function(e) { e.preventDefault(); authLogout(); };

    dropdown.appendChild(accountLink);
    dropdown.appendChild(logoutLink);
    document.body.appendChild(dropdown);

    // Close dropdown when clicking elsewhere
    setTimeout(function() {
        document.addEventListener('click', closeDropdown, { once: true });
    }, 0);
}

function closeDropdown(e) {
    var dropdown = document.getElementById('auth-dropdown');
    if (dropdown && !dropdown.contains(e.target) && !document.getElementById('auth-nav-btn').contains(e.target)) {
        dropdown.remove();
    }
}

// ─── Path helpers ─────────────────────────────────────────────────────────────
function _isInPages() {
    return window.location.pathname.includes('/pages/');
}
function _loginPage()   { return _isInPages() ? 'login.html'   : 'pages/login.html'; }
function _accountPage() { return _isInPages() ? 'account.html' : 'pages/account.html'; }

// ─── Security helpers ─────────────────────────────────────────────────────────
function _esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function _escAttr(str) {
    return _esc(str).replace(/'/g,'&#039;');
}
function _initials(name) {
    return (name || '?').split(' ').slice(0,2).map(w => w[0]).join('').toUpperCase();
}

// ─── Require auth guard (for protected pages) ────────────────────────────────
// Call this at the top of account/profile pages.
// Returns user if authenticated, otherwise redirects to login.
function authRequire() {
    const user = authGetUser();
    if (!user || !authGetToken()) {
        const currentPath = encodeURIComponent(window.location.pathname + window.location.search);
        window.location.href = _loginPage() + '?redirect=' + currentPath;
        return null;
    }
    return user;
}

// ─── Init on DOMContentLoaded ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    authUpdateNav();

    if (authIsLoggedIn()) {
        authSyncCartOnLogin();

        // Periodically re-validate session in background (every 5 min)
        // Only updates nav if data actually changed — no unnecessary repaints
        setInterval(async function () {
            const res = await authFetch('/api/user/me');
            if (!res.ok) {
                authClearSession();
                authUpdateNav();
            } else if (res.data && res.data.success) {
                const current = JSON.stringify(authGetUser());
                const fresh   = JSON.stringify(res.data.data);
                if (current !== fresh) {
                    authSetSession(authGetToken(), res.data.data);
                    authUpdateNav();
                }
            }
        }, 5 * 60 * 1000);
    }
});

// ─── Google One Tap initialiser ───────────────────────────────────────────────
// Call this on login / register pages after loading the Google GSI script.
function authInitGoogleOneTap(clientId, onSuccess) {
    if (!window.google || !google.accounts) return;

    google.accounts.id.initialize({
        client_id:         clientId,
        callback:          function(response) { _handleGoogleCredential(response, onSuccess); },
        auto_select:       false,
        cancel_on_tap_outside: true,
        context:           'signin',
    });

    // Render the standard Google button
    const container = document.getElementById('google-signin-btn');
    if (container) {
        google.accounts.id.renderButton(container, {
            theme:  'outline',
            size:   'large',
            width:  340,
            text:   'continue_with',
            shape:  'rectangular',
            logo_alignment: 'left',
        });
    }

    // Show One Tap prompt if not already signed in locally
    if (!authIsLoggedIn()) {
        google.accounts.id.prompt();
    }
}

async function _handleGoogleCredential(response, onSuccess) {
    const idToken = response.credential;
    if (!idToken) return;

    try {
        const apiBase = (typeof API_BASE !== 'undefined') ? API_BASE : '';
        const res = await fetch(apiBase + '/api/user/google', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ id_token: idToken, remember: true }),
        });
        const json = await res.json();

        if (res.ok && json.success) {
            authSetSession(json.data.token, json.data.user);
            await authSyncCartOnLogin();
            authUpdateNav();
            if (typeof onSuccess === 'function') {
                onSuccess(json.data.user);
            } else {
                // Default: go to account page
                window.location.href = _accountPage();
            }
        } else {
            authShowError(document.getElementById('auth-error'), json.error || 'Google sign-in failed. Please try again.');
        }
    } catch (err) {
        authShowError(document.getElementById('auth-error'), 'Could not connect. Please check your connection.');
    }
}

// ─── Shared error display ─────────────────────────────────────────────────────
function authShowError(container, msg) {
    if (!container) return;
    container.textContent = msg;
    container.style.display = 'block';
    container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function authClearError(container) {
    if (!container) return;
    container.textContent = '';
    container.style.display = 'none';
}

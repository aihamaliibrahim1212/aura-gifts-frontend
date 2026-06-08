// ─── Standalone Cart ,  works on any page ─────────────────────────

// Inject cart drawer only if not already in the page
if (!document.getElementById('cart-drawer')) {
    const drawer = document.createElement('div');
    drawer.innerHTML = `
        <div class="cart-overlay" id="cart-overlay" onclick="toggleCart()"></div>
        <div class="cart-drawer" id="cart-drawer">
            <div class="cart-header">
                <h3>Your Cart</h3>
                <button class="cart-close" onclick="toggleCart()"><i class="fas fa-times"></i></button>
            </div>
            <div class="cart-items" id="cart-items"></div>
            <div class="cart-footer" id="cart-footer"></div>
        </div>
    `;
    document.body.appendChild(drawer);
}

// Cart state from localStorage.

let cart = JSON.parse(localStorage.getItem('aura_cart') || '[]');
// Normalize any stored img paths (strip leading ../)
cart = cart.map(item => ({ ...item, img: item.img ? item.img.replace(/^\.\.\//, '') : item.img }));
saveCart();

function saveCart() {
    localStorage.setItem('aura_cart', JSON.stringify(cart));
    // Sync to server if logged in (debounced 1s to avoid hammering on fast changes)
    if (typeof authIsLoggedIn === 'function' && authIsLoggedIn()) {
        clearTimeout(window._cartSyncTimer);
        window._cartSyncTimer = setTimeout(function() {
            if (typeof authSaveCartToServer === 'function') authSaveCartToServer();
        }, 1000);
    }
}

function updateCartCount() {
    const total = cart.reduce((sum, item) => sum + item.qty, 0);
    document.querySelectorAll('.cart-count').forEach(el => {
        el.textContent = total;
        el.style.display = total > 0 ? 'flex' : 'none';
    });
}

// Resolve correct path prefix based on where the page is loaded from
const _pathPrefix = (function() {
    const p = window.location.pathname;
    if (p.endsWith('/') || p.includes('index.html') || p.includes('404.html') ||
        p.split('/').filter(Boolean).length <= 1) {
        return '';
    }
    return '../';
})();

function _normalizeImg(img) {
    // Always store as root-relative (strip leading ../)
    return img.replace(/^\.\.\//, '');
}

function renderCart() {
    const container = document.getElementById('cart-items');
    const footer = document.getElementById('cart-footer');
    if (!container) return;

    if (cart.length === 0) {
        container.innerHTML = `
            <div class="cart-empty">
                <i class="fas fa-shopping-basket"></i>
                <p>Your cart is empty</p>
                <span>Add some hampers to get started</span>
                <a href="${_pathPrefix}pages/hampers.html" class="btn btn-gold" style="margin-top:16px;">Browse Hampers</a>
            </div>`;
        footer.innerHTML = '';
        return;
    }

    const grandTotal = cart.reduce((sum, item) => {
        const num = parseFloat(item.price.replace(/[^0-9.]/g, '').replace(',', '')) || 0;
        return sum + num * item.qty;
    }, 0);
    const currency = cart[0].price.replace(/[\d,.\s]/g, '').trim() || 'MVR';

    container.innerHTML = cart.map(item => {
        // If img is already a full URL use it directly, otherwise prefix for relative paths
        const imgSrc = item.img && item.img.startsWith('http') ? item.img : _pathPrefix + item.img;
        return `
        <div class="cart-item" data-index="${item.index}">
            <img src="${imgSrc}" alt="${item.name}" class="cart-item-img">
            <div class="cart-item-info">
                <div class="cart-item-name">${item.name}</div>
                <div class="cart-item-price">${item.price}</div>
                <div class="cart-item-qty">
                    <button onclick="changeQty(${item.index}, -1)">−</button>
                    <span>${item.qty}</span>
                    <button onclick="changeQty(${item.index}, 1)">+</button>
                </div>
            </div>
            <button class="cart-item-remove" onclick="removeFromCart(${item.index})" title="Remove"><i class="fas fa-times"></i></button>
        </div>`;
    }).join('');

    footer.innerHTML = `
        <div class="cart-total">
            <span>Total</span>
            <span>${currency} ${grandTotal.toLocaleString()}</span>
        </div>
        <button class="btn btn-gold btn-lg" style="width:100%;justify-content:center;margin-top:14px;" onclick="cartCheckout()">
            <i class="fas fa-paper-plane"></i> Confirm Order
        </button>
        <p style="text-align:center;margin-top:8px;font-size:0.75rem;color:var(--text-light);">We'll confirm your order via email</p>
    `;
}

function removeFromCart(index) {
    const el = document.querySelector(`.cart-item[data-index="${index}"]`);
    if (el) {
        el.classList.add('removing');
        setTimeout(() => {
            cart = cart.filter(item => item.index !== index);
            saveCart(); updateCartCount();
            // If cart now empty, fade in the empty state smoothly
            const container = document.getElementById('cart-items');
            const footer = document.getElementById('cart-footer');
            if (cart.length === 0) {
                container.style.overflow = 'hidden';
                container.style.opacity = '0';
                setTimeout(() => {
                    renderCart();
                    container.style.overflow = '';
                    container.style.transition = 'opacity 0.2s ease';
                    container.style.opacity = '1';
                    setTimeout(() => container.style.transition = '', 200);
                }, 50);
            } else {
                renderCart();
            }
        }, 280);
    } else {
        cart = cart.filter(item => item.index !== index);
        saveCart(); updateCartCount(); renderCart();
    }
}

function changeQty(index, delta) {
    const item = cart.find(item => item.index === index);
    if (!item) return;
    if (delta > 0 && item.stock !== undefined && item.qty >= item.stock) {
        if (typeof showWarningToast === 'function') {
            showWarningToast(`Only ${item.stock} of "${item.name}" available`);
        }
        return;
    }
    if (delta < 0 && item.qty <= 1) {
        removeFromCart(index);
        return;
    }
    item.qty += delta;
    saveCart(); updateCartCount();
    // Animate just the qty number
    const qtyEl = document.querySelector(`.cart-item[data-index="${index}"] .cart-item-qty span`);
    if (qtyEl) {
        qtyEl.textContent = item.qty;
        qtyEl.classList.remove('qty-pop');
        void qtyEl.offsetWidth; // reflow
        qtyEl.classList.add('qty-pop');
    } else {
        renderCart();
    }
}

function cartCheckout() {
    if (cart.length === 0) return;

    // Pre-fill from logged-in user if available
    const loggedInUser = (typeof authGetUser === 'function') ? authGetUser() : null;

    // Build and show checkout modal
    const existing = document.getElementById('checkout-modal-overlay');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'checkout-modal-overlay';
    overlay.style.cssText = `
        position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);
        display:flex;align-items:center;justify-content:center;padding:16px;
    `;
    overlay.innerHTML = `
        <div id="checkout-modal" style="
            background:#fff;border-radius:16px;padding:32px 28px;max-width:420px;width:100%;
            box-shadow:0 20px 60px rgba(0,0,0,0.18);position:relative;max-height:90vh;overflow-y:auto;
        ">
            <button onclick="document.getElementById('checkout-modal-overlay').remove()" style="
                position:absolute;top:14px;right:16px;background:none;border:none;
                font-size:1.2rem;cursor:pointer;color:#999;line-height:1;
            "><i class="fas fa-times"></i></button>
            <div style="text-align:center;margin-bottom:20px;">
                <div style="width:48px;height:48px;background:var(--beige,#e8e0d0);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                    <i class="fas fa-shopping-basket" style="color:var(--gold-dark,#b8a898);font-size:1.2rem;"></i>
                </div>
                <h3 style="margin:0;font-size:1.25rem;font-weight:700;">Complete Your Order</h3>
                <p style="margin:6px 0 0;font-size:0.85rem;color:#888;">We'll get back to you to confirm payment and delivery.</p>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:0.8rem;font-weight:600;margin-bottom:4px;color:#555;">Your Name <span style="color:#e55;">*</span></label>
                <input id="co-name" type="text" placeholder="Full name" style="
                    width:100%;padding:10px 14px;border:1.5px solid #e0d8cc;border-radius:8px;
                    font-size:0.9rem;outline:none;box-sizing:border-box;
                " onfocus="this.style.borderColor='#b8a898'" onblur="this.style.borderColor='#e0d8cc'">
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:0.8rem;font-weight:600;margin-bottom:4px;color:#555;">Email Address <span style="color:#e55;">*</span></label>
                <input id="co-email" type="email" placeholder="your@email.com" style="
                    width:100%;padding:10px 14px;border:1.5px solid #e0d8cc;border-radius:8px;
                    font-size:0.9rem;outline:none;box-sizing:border-box;
                " onfocus="this.style.borderColor='#b8a898'" onblur="this.style.borderColor='#e0d8cc'">
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:0.8rem;font-weight:600;margin-bottom:4px;color:#555;">Phone Number <span style="color:#e55;">*</span></label>
                <input id="co-phone" type="tel" placeholder="e.g. 9991234" style="
                    width:100%;padding:10px 14px;border:1.5px solid #e0d8cc;border-radius:8px;
                    font-size:0.9rem;outline:none;box-sizing:border-box;
                " onfocus="this.style.borderColor='#b8a898'" onblur="this.style.borderColor='#e0d8cc'">
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block;font-size:0.8rem;font-weight:600;margin-bottom:4px;color:#555;">Notes / Special Requests (optional)</label>
                <textarea id="co-notes" placeholder="Occasion, delivery preferences, personalisation..." rows="3" style="
                    width:100%;padding:10px 14px;border:1.5px solid #e0d8cc;border-radius:8px;
                    font-size:0.9rem;outline:none;resize:vertical;font-family:inherit;box-sizing:border-box;
                " onfocus="this.style.borderColor='#b8a898'" onblur="this.style.borderColor='#e0d8cc'"></textarea>
            </div>
            <button id="co-submit-btn" onclick="submitOrder()" style="
                width:100%;padding:13px;background:var(--gold-dark,#b8a898);color:#fff;border:none;
                border-radius:10px;font-size:0.95rem;font-weight:600;cursor:pointer;
                display:flex;align-items:center;justify-content:center;gap:8px;
            ">
                <i class="fas fa-check-circle"></i> Place Order
            </button>
            <p id="co-error" style="display:none;color:#e55;font-size:0.8rem;text-align:center;margin-top:10px;"></p>
        </div>
    `;
    document.body.appendChild(overlay);
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
    document.getElementById('co-name').focus();

    // Pre-fill if logged in
    if (loggedInUser) {
        const nameEl  = document.getElementById('co-name');
        const emailEl = document.getElementById('co-email');
        if (nameEl  && !nameEl.value  && loggedInUser.name)  nameEl.value  = loggedInUser.name;
        if (emailEl && !emailEl.value && loggedInUser.email) emailEl.value = loggedInUser.email;
    }
}

async function submitOrder() {
    const nameEl = document.getElementById('co-name');
    const emailEl = document.getElementById('co-email');
    const phoneEl = document.getElementById('co-phone');
    const notesEl = document.getElementById('co-notes');
    const errEl = document.getElementById('co-error');
    const btn = document.getElementById('co-submit-btn');

    const name = nameEl ? nameEl.value.trim() : '';
    const email = emailEl ? emailEl.value.trim() : '';
    const phone = phoneEl ? phoneEl.value.trim() : '';
    const notes = notesEl ? notesEl.value.trim() : '';

    if (!name) { showCoError('Please enter your name.'); nameEl && nameEl.focus(); return; }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showCoError('Please enter a valid email address.'); emailEl && emailEl.focus(); return; }
    if (!phone) { showCoError('Please enter your phone number.'); phoneEl && phoneEl.focus(); return; }

    // Build items array with numeric price
    const items = cart.map(item => {
        const numPrice = parseFloat(item.price.replace(/[^0-9.]/g, '').replace(',', '')) || 0;
        return { name: item.name, qty: item.qty, price: numPrice };
    });

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Placing Order...';
    if (errEl) errEl.style.display = 'none';

    try {
        const apiBase = (typeof API_BASE !== 'undefined') ? API_BASE : '';
        const headers = { 'Content-Type': 'application/json' };
        const userToken = (typeof authGetToken === 'function') ? authGetToken() : null;
        if (userToken) headers['Authorization'] = 'Bearer ' + userToken;

        const res = await fetch(apiBase + '/api/orders', {
            method: 'POST',
            headers,
            credentials: 'include',
            body: JSON.stringify({
                customer_name: name,
                customer_email: email,
                customer_phone: phone,
                items,
                notes
            })
        });
        const json = await res.json();
        if (res.ok && json.success !== false) {
            // Success: clear cart, close modal, show toast
            cart = [];
            saveCart();
            updateCartCount();
            renderCart();
            // Clear server cart too
            if (typeof authSaveCartToServer === 'function') authSaveCartToServer();
            const overlay = document.getElementById('checkout-modal-overlay');
            if (overlay) overlay.remove();
            closeCart();
            showSuccessToast('Order placed! We\'ll be in touch.');
        } else {
            showCoError(json.error || 'Something went wrong. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Place Order';
        }
    } catch (err) {
        showCoError('Could not connect. Please check your connection and try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Place Order';
    }
}

function showCoError(msg) {
    const errEl = document.getElementById('co-error');
    if (errEl) { errEl.textContent = msg; errEl.style.display = 'block'; }
}

function showSuccessToast(msg) {
    const old = document.getElementById('cart-toast');
    if (old) old.remove();
    const toast = document.createElement('div');
    toast.id = 'cart-toast';
    toast.className = 'cart-toast success';
    toast.innerHTML = `
        <div class="cart-toast-label">
            <i class="fas fa-check-circle"></i>
            <span>${msg}</span>
        </div>
        <div class="cart-toast-bar-wrap">
            <div class="cart-toast-bar"></div>
        </div>
    `;
    document.body.appendChild(toast);
    requestAnimationFrame(() => requestAnimationFrame(() => toast.classList.add('show')));
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, 3500);
}

function openCart() {
    renderCart();
    document.getElementById('cart-drawer').classList.add('open');
    document.getElementById('cart-overlay').classList.add('open');
    document.body.style.overflow = 'hidden';
    document.body.style.touchAction = 'none';
}

function closeCart() {
    document.getElementById('cart-drawer').classList.remove('open');
    document.getElementById('cart-overlay').classList.remove('open');
    document.body.style.overflow = '';
    document.body.style.touchAction = '';
}

function toggleCart() {
    const isOpen = document.getElementById('cart-drawer').classList.contains('open');
    isOpen ? closeCart() : openCart();
}

// Init
updateCartCount();

// Auto-open if redirected with #cart
if (window.location.hash === '#cart') {
    openCart();
    history.replaceState(null, '', window.location.pathname);
}

// Keyboard close
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeCart();
});

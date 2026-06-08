/* ============================================================
   Aura Gifts Admin Panel — admin.js
   Now uses customer auth token (aura_user_token) instead of
   the old admin-only token system.
   ============================================================ */

const API_BASE = 'http://localhost:8000';
const TOKEN_KEY = 'aura_user_token';
const USER_KEY  = 'aura_user';

// ── Prevent scroll restoration on refresh ───────────────────
(function() {
  if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
  window.addEventListener('load', function() { window.scrollTo(0, 0); });

  // Save scroll position before unload
  window.addEventListener('beforeunload', function() {
    sessionStorage.setItem('_adminScrollY_' + location.pathname, window.scrollY);
  });

  // On load, if it was a browser refresh, scroll back smoothly
  window.addEventListener('load', function() {
    var nav = performance.getEntriesByType('navigation')[0];
    var savedY = parseInt(sessionStorage.getItem('_adminScrollY_' + location.pathname) || '0', 10);
    if (nav && nav.type === 'reload' && savedY > 0) {
      setTimeout(function() {
        window.scrollTo({ top: savedY, behavior: 'smooth' });
      }, 120);
    } else {
      sessionStorage.removeItem('_adminScrollY_' + location.pathname);
    }
  });
})();

function getToken() {
  return localStorage.getItem(TOKEN_KEY);
}

function getStoredUser() {
  try { return JSON.parse(localStorage.getItem(USER_KEY)); } catch { return null; }
}

// ── Auth guard ───────────────────────────────────────────────

function requireAuth() {
  var token = getToken();
  var user  = getStoredUser();

  if (!token || !user) {
    window.location.href = '/pages/login.html?redirect=' + encodeURIComponent(window.location.pathname);
    return;
  }

  // Must have admin or superadmin role
  if (user.role !== 'admin' && user.role !== 'superadmin') {
    window.location.href = '/pages/account.html';
    return;
  }

  document.body.classList.add('auth-ready');
  var nameEl = document.getElementById('adminName');
  if (nameEl) {
    var full = (user.name || user.email) + ' (' + user.role + ')';
    nameEl.textContent = full;
    nameEl.title = full;
  }
}

// ── Logout ───────────────────────────────────────────────────

async function logout() {
  try {
    await fetch(API_BASE + '/api/user/logout', {
      method: 'POST',
      headers: { 'Authorization': 'Bearer ' + getToken(), 'Accept': 'application/json' }
    });
  } catch (_) {}
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
  // Go back to main site, not an admin login page
  window.location.href = '/pages/login.html';
}

// ── Invalidate frontend cache ─────────────────────────────────

function invalidateFrontendCache() {
  try {
    Object.keys(localStorage)
      .filter(k => k.startsWith('ac_'))
      .forEach(k => localStorage.removeItem(k));
  } catch {}
}

// ── Core API helper ──────────────────────────────────────────

async function api(url, method = 'GET', body = null) {
  const fullUrl = url.startsWith('http') ? url : API_BASE + url;
  const options = {
    method,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': 'Bearer ' + getToken()
    },
  };
  if (body !== null) options.body = JSON.stringify(body);

  const res = await fetch(fullUrl, options);

  if (res.status === 401) {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    showSessionExpiredBanner();
    throw new Error('Session expired. Please log in again.');
  }

  const json = await res.json();
  if (!json.success) throw new Error(json.error || 'Request failed');
  return json.data;
}

async function apiFormData(url, formData) {
  const fullUrl = url.startsWith('http') ? url : API_BASE + url;
  const res = await fetch(fullUrl, {
    method: 'POST',
    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + getToken() },
    body: formData,
  });
  if (res.status === 401) {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    showSessionExpiredBanner();
    throw new Error('Session expired. Please log in again.');
  }
  const json = await res.json();
  if (!json.success) throw new Error(json.error || 'Upload failed');
  return json.data;
}

function showSessionExpiredBanner() {
  const existing = document.getElementById('session-expired-banner');
  if (existing) existing.remove();
  const banner = document.createElement('div');
  banner.id = 'session-expired-banner';
  banner.style.cssText = `
    position:fixed;top:0;left:0;right:0;z-index:99999;
    background:#c62828;color:white;padding:14px 24px;
    display:flex;align-items:center;justify-content:space-between;
    font-family:'Inter',sans-serif;font-size:0.9rem;font-weight:600;
    box-shadow:0 4px 12px rgba(0,0,0,0.2);
  `;
  banner.innerHTML = `
    <span><i class="fas fa-lock" style="margin-right:8px;"></i>Your session has expired. Please sign in again.</span>
    <a href="/pages/login.html" style="background:white;color:#c62828;padding:7px 16px;border-radius:6px;font-weight:700;text-decoration:none;font-size:0.85rem;margin-left:16px;white-space:nowrap;">Sign In</a>
  `;
  document.body.prepend(banner);
}

// ── Toast ────────────────────────────────────────────────────

let _toastTimer = null;
function showToast(message, type = 'info') {
  const el = document.getElementById('toast');
  if (!el) return;
  el.textContent = message;
  el.className = `toast ${type} show`;
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => { el.className = 'toast'; }, 3200);
}

// ── Sidebar ──────────────────────────────────────────────────

function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  if (sidebar) sidebar.classList.toggle('open');
  if (backdrop) backdrop.classList.toggle('show', sidebar.classList.contains('open'));
}

function closeSidebar() {
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  if (sidebar) sidebar.classList.remove('open');
  if (backdrop) backdrop.classList.remove('show');
}

// Inject backdrop once DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  if (!document.getElementById('sidebarBackdrop')) {
    const bd = document.createElement('div');
    bd.id = 'sidebarBackdrop';
    bd.className = 'sidebar-backdrop';
    bd.onclick = closeSidebar;
    document.body.appendChild(bd);
  }
});

document.addEventListener('click', (e) => {
  const sidebar = document.getElementById('sidebar');
  const hamburger = document.querySelector('.hamburger');
  if (!sidebar) return;
  if (window.innerWidth <= 900 && sidebar.classList.contains('open') &&
      !sidebar.contains(e.target) && e.target !== hamburger && !hamburger?.contains(e.target)) {
    closeSidebar();
  }
});

// ── Utilities ────────────────────────────────────────────────

function escHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function formatDate(isoString) {
  if (!isoString) return ', ';
  const d = new Date(isoString);
  if (isNaN(d)) return isoString;
  return d.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
}

function statusColor(status) {
  const map = { pending:'orange', confirmed:'blue', delivered:'green', cancelled:'red' };
  return map[status] || 'gray';
}

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(el => el.classList.remove('open'));
  }
});

document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('open');
});

// ── Themed confirm dialog ─────────────────────────────────────
function showConfirm(message, onConfirm) {
  const existing = document.getElementById('confirm-dialog-overlay');
  if (existing) existing.remove();

  const overlay = document.createElement('div');
  overlay.id = 'confirm-dialog-overlay';
  overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.45);display:flex;align-items:center;justify-content:center;padding:16px;';
  overlay.innerHTML = `
    <div style="background:#fff;border-radius:12px;padding:28px 24px;max-width:380px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.15);font-family:'Inter',sans-serif;">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
        <div style="width:36px;height:36px;border-radius:50%;background:#fef2f2;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="fas fa-exclamation-triangle" style="color:#dc2626;font-size:0.95rem;"></i>
        </div>
        <p style="margin:0;font-size:0.9rem;color:#374151;line-height:1.5;">${message}</p>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button id="confirm-cancel-btn" style="padding:8px 18px;border:1.5px solid #e0d8cc;border-radius:7px;background:#fff;font-size:0.85rem;font-weight:600;cursor:pointer;color:#555;">Cancel</button>
        <button id="confirm-ok-btn" style="padding:8px 18px;border:none;border-radius:7px;background:#dc2626;color:#fff;font-size:0.85rem;font-weight:600;cursor:pointer;">Delete</button>
      </div>
    </div>
  `;
  document.body.appendChild(overlay);
  document.getElementById('confirm-cancel-btn').onclick = () => overlay.remove();
  document.getElementById('confirm-ok-btn').onclick = () => { overlay.remove(); onConfirm(); };
  overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
}


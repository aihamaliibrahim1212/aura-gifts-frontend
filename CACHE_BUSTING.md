# Cache Busting Guide - Update These When You Make Changes

## Quick Reference

When you change a JavaScript or CSS file, update the version number in the script/link tags to force browsers to load the latest version.

---

## Files and Their Current Versions

| File | Current Version | Files That Load It |
|------|-----------------|-------------------|
| `js/auth.js` | `?v=2` | All HTML pages |
| `js/api-cache.js` | `?v=11` | All HTML pages |
| `js/main.js` | `?v=10` | index.html + all pages |
| `js/cart.js` | `?v=10` | All HTML pages |
| `css/style.css` | `?v=12` | All HTML pages |
| `css/auth.css` | `?v=4` | Login/Register pages |

---

## How to Update (3 Easy Steps)

### 1. Make your code change
Edit the file, e.g., `js/auth.js`

### 2. Increment the version number
Find all places the file is loaded:
- `<script src="js/auth.js?v=2"` → change to `?v=3`
- `<script src="../js/auth.js?v=2"` → change to `?v=3`

### 3. Test
- Hard refresh in browser: `Ctrl+Shift+R` (Windows) or `Cmd+Shift+R` (Mac)
- Open DevTools Console to verify no errors

---

## Example: Updating auth.js

**Scenario:** You modify `js/auth.js`

**Step 1:** Find all occurrences in HTML files
```bash
grep -r "js/auth.js" .
```

Output shows:
```
index.html:    <script src="js/auth.js?v=2"
pages/hampers.html:    <script src="../js/auth.js?v=2"
pages/login.html:    <script src="../js/auth.js?v=2"
... (all other pages)
```

**Step 2:** Update all instances
- In `index.html`: `js/auth.js?v=2` → `js/auth.js?v=3`
- In all pages: `../js/auth.js?v=2` → `../js/auth.js?v=3`

**Step 3:** Verify
- Refresh page
- DevTools should show `auth.js?v=3` in Network tab
- New version is now loaded ✓

---

## Files to Check By Type

### When updating **auth.js** or **api-cache.js**
Update in: index.html + all pages in `/pages/`

### When updating **main.js** or **cart.js**
Update in: index.html + all pages in `/pages/`

### When updating **style.css**
Update in: index.html + all pages in `/pages/`

### When updating **auth.css**
Update in: login.html, register.html, forgot-password.html, reset-password.html, verify-email.html

---

## Production Notes

- **In production:** Users will automatically get the new version (no hard refresh needed)
- **Locally:** You need to hard refresh to see changes
- **No customer notification:** The version number in the URL handles it automatically
- **Always increment:** Never reuse version numbers (go 2→3→4, not 2→2)

---

## Common Mistakes to Avoid

❌ Forgetting to update ALL occurrences (pages/ folder versions are different)
❌ Using same version number for multiple changes
❌ Not hard refreshing locally to test
❌ Only updating index.html (forget about pages/)

✓ Update every occurrence
✓ Always increment (+1)
✓ Hard refresh before testing
✓ Check both root and pages/ folder

---

## Quick Commands

**Find all script tags for a file:**
```bash
grep -r "auth.js" . --include="*.html"
```

**Find current version of a file:**
```bash
grep "auth.js?v=" index.html
```

---

That's it! Just increment the version number whenever you change the file.

# Development Setup: GitHub Pages Frontend + Local Backend

Run frontend from GitHub Pages, backend locally on your PC.

---

## STEP 1: Setup GitHub Pages for Frontend

### Create Frontend Repository

```bash
# Create new folder for frontend-only repo
mkdir aura-gifts-frontend
cd aura-gifts-frontend
git init

# Copy only frontend files
cp ../aura-gifts/index.html .
cp ../aura-gifts/404.html .
cp -r ../aura-gifts/pages/ .
cp -r ../aura-gifts/css/ .
cp -r ../aura-gifts/js/ .

# Initialize git
git add .
git commit -m "Initial frontend setup"
git branch -M main

# Add remote (replace YOUR_USERNAME)
git remote add origin https://github.com/YOUR_USERNAME/aura-gifts-frontend.git
git push -u origin main
```

### Enable GitHub Pages

1. Go to: **https://github.com/YOUR_USERNAME/aura-gifts-frontend/settings**
2. Scroll to **Pages** section
3. Source: Select `main` branch
4. Click **Save**
5. Wait 2-3 minutes

Your frontend will be live at:
```
https://YOUR_USERNAME.github.io/aura-gifts-frontend
```

---

## STEP 2: Configure Backend for Local Development

### Setup CORS for GitHub Pages

Your backend needs to allow requests from GitHub Pages.

Edit `.env` in `backend-php/`:

```env
CORS_ALLOWED_ORIGINS=https://YOUR_USERNAME.github.io,http://localhost:8000,http://localhost:3000
```

Or more permissive for dev:

```env
CORS_ALLOWED_ORIGINS=*
```

Then run:

```bash
cd backend-php
php artisan config:clear
php artisan config:cache
```

### Start Backend Locally

```bash
cd backend-php

# Run Laravel dev server
php artisan serve

# Runs on: http://localhost:8000
```

**Output should show:**
```
Laravel development server started: http://127.0.0.1:8000
```

---

## STEP 3: Update Frontend API Calls

Your frontend JavaScript needs to point to your local backend.

**Create `js/config.js`:**

```javascript
// API Configuration
const API_BASE = window.location.hostname === 'localhost' 
  ? 'http://localhost:8000'  // Local dev
  : 'https://YOUR_USERNAME.github.io';  // Won't be used from GitHub Pages

// Override for GitHub Pages
if (window.location.hostname.includes('github.io')) {
  window.API_BASE = 'http://localhost:8000';  // Point to your local PC
}
```

**Update `js/api-cache.js`:**

Change line 6 from:
```javascript
const API_BASE = 'http://localhost:8000';
```

To use environment-aware config:
```javascript
const API_BASE = window.API_BASE || 'http://localhost:8000';
```

**Update `js/auth.js`:**

Same pattern - use `window.API_BASE` instead of hardcoding.

---

## STEP 4: Test the Connection

### From GitHub Pages

1. Open: `https://YOUR_USERNAME.github.io/aura-gifts-frontend`
2. Open Browser DevTools (F12)
3. Go to **Console** tab
4. Try:

```javascript
fetch('http://localhost:8000/api/status')
  .then(r => r.json())
  .then(d => console.log(d))
```

If it works, you'll see response. If CORS error:
```
Access to fetch at 'http://localhost:8000/api/status' from origin 
'https://your-username.github.io' has been blocked by CORS policy
```

**Fix:** Make sure your `.env` has:
```
CORS_ALLOWED_ORIGINS=https://YOUR_USERNAME.github.io
```

---

## WORKFLOW: Making Changes

### Change Frontend

```bash
cd aura-gifts-frontend

# Edit files
nano pages/index.html

# Commit and push
git add .
git commit -m "Update homepage"
git push origin main

# Wait 1-2 minutes for GitHub to rebuild
# Then refresh: https://YOUR_USERNAME.github.io/aura-gifts-frontend
```

### Change Backend

```bash
cd aura-gifts/backend-php

# Edit controller or migration
nano app/Http/Controllers/AdminController.php

# Laravel auto-reloads changes with `php artisan serve`
# Just refresh your browser
```

---

## COMMON ISSUES & FIXES

### "Cannot GET /pages/..." 

Frontend files aren't being served. GitHub Pages issue.

**Fix:** Make sure all files are committed and pushed:
```bash
git status
git add .
git commit -m "Add missing pages"
git push
```

### CORS Error: "has been blocked by CORS policy"

Backend not allowing GitHub Pages origin.

**Fix:**
```bash
# Edit backend-php/.env
CORS_ALLOWED_ORIGINS=https://YOUR_USERNAME.github.io,http://localhost:8000

# Clear cache
php artisan config:clear
php artisan config:cache
```

### API Calls Return 404

Your frontend is pointing to wrong API URL.

**Fix:** Check what URL is being called:
```javascript
console.log(API_BASE);  // Should be http://localhost:8000
```

### "Error: [object Object]" in Console

Backend error. Check backend logs:
```bash
# In another terminal, watch backend logs
tail -f backend-php/storage/logs/laravel.log
```

---

## DEVELOPMENT WORKFLOW EXAMPLE

### You want to test login

**Step 1: Make backend change**
```bash
cd aura-gifts/backend-php
# Edit authentication logic
php artisan serve  # Backend running on http://localhost:8000
```

**Step 2: Make frontend change**
```bash
cd aura-gifts-frontend
# Edit login form
git add pages/login.html
git commit -m "Update login UI"
git push origin main
# Wait 1-2 min
```

**Step 3: Test**
- Open: `https://YOUR_USERNAME.github.io/aura-gifts-frontend/pages/login.html`
- Login form uses API at `http://localhost:8000/api/auth/login`
- Backend receives request, processes it
- Response comes back to frontend

---

## Advanced: Use Local Frontend Too

Don't want to wait for GitHub Pages to rebuild? Run frontend locally:

```bash
# In one terminal - Backend
cd aura-gifts/backend-php
php artisan serve
# http://localhost:8000

# In another terminal - Frontend
cd aura-gifts-frontend
python3 -m http.server 8001
# http://localhost:8001
```

Now both running locally. Update `.env`:
```
CORS_ALLOWED_ORIGINS=http://localhost:8001,http://localhost:8000
```

---

## Switching to GitHub Pages Only (Later)

When files are ready, just push to the same repo:

```bash
cd aura-gifts-frontend
git add .
git commit -m "Frontend ready"
git push origin main
```

GitHub auto-deploys!

---

## Full Architecture (Current Dev Setup)

```
┌─────────────────────────────────────────┐
│         GitHub Pages                    │
│  (Frontend hosted at github.io)         │
│  - HTML/CSS/JS                          │
│  - Makes API calls to localhost:8000    │
└──────────────────┬──────────────────────┘
                   │
                   │ HTTP Requests
                   ↓
┌─────────────────────────────────────────┐
│        Your PC (Local)                  │
│  - Laravel Backend (localhost:8000)     │
│  - MySQL Database                       │
│  - PHP Development Server               │
└─────────────────────────────────────────┘

Flow:
1. Open GitHub Pages URL in browser
2. Download frontend files
3. JavaScript makes API call to localhost:8000
4. Backend processes request
5. Returns data to frontend
6. Frontend displays results
```

---

## Quick Commands Reference

```bash
# Start backend
cd aura-gifts/backend-php && php artisan serve

# Update frontend on GitHub
cd aura-gifts-frontend
git add .
git commit -m "Your message"
git push origin main

# Check if backend is running
curl http://localhost:8000/api/status

# View backend logs
tail -f aura-gifts/backend-php/storage/logs/laravel.log

# Clear Laravel cache
php artisan config:clear && php artisan cache:clear

# Run migrations
php artisan migrate
```

---

## When You're Ready to Deploy

Just follow DEPLOYMENT_GUIDE.md:
1. Update backend to production server
2. Update frontend CORS_ALLOWED_ORIGINS to production URL
3. Update API_BASE in frontend to production domain
4. Deploy frontend to GitHub Pages (or your own hosting)

---

**Status:** Ready for development! 🚀

Start with:
```bash
# Terminal 1 - Backend
cd aura-gifts/backend-php && php artisan serve

# Terminal 2 - Frontend (optional, if developing locally)
cd aura-gifts-frontend && python3 -m http.server 8001
```

Then test at:
- `https://YOUR_USERNAME.github.io/aura-gifts-frontend` (after push)
- Or `http://localhost:8001` (if running locally)

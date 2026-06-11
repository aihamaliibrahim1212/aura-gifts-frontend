# Aura Gifts — Backend API

Laravel 13 / PHP 8.3 REST API powering the Aura Gifts storefront.

---

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed
php artisan serve
```

---

## Environment Variables

Key variables to configure in `.env`:

| Variable | Description |
|---|---|
| `DB_*` | MySQL connection (host, port, database, username, password) |
| `APP_URL` | Full URL of your site, e.g. `https://auragifts.mv` |
| `CLOUDINARY_*` | Cloudinary credentials for image uploads |
| `GOOGLE_CLIENT_ID` | Google OAuth Client ID (from Google Cloud Console) |
| `GOOGLE_CLIENT_SECRET` | Google OAuth Client Secret |
| `MAIL_*` | SMTP credentials for password reset & email verification emails |

---

## Authentication

### Admin (existing)
- `POST /api/auth/login` — admin login, returns bearer token
- Token stored in `localStorage` by the admin panel SPA
- Protected routes use `admin.auth` middleware (bearer token)

### Customer (new)
- `POST /api/user/register` — register with name, email, password
- `POST /api/user/login` — login with email + password
- `POST /api/user/google` — authenticate via Google ID token (from frontend GSI)
- `POST /api/user/logout` — invalidate token
- `GET  /api/user/me` — get current user info
- `POST /api/user/forgot-password` — send password reset email
- `POST /api/user/reset-password` — reset password with token
- `GET  /api/user/verify-email?token=` — verify email address

### Customer protected routes (require Bearer token)
- `PUT    /api/user/profile` — update name or password
- `GET    /api/user/orders` — order history
- `GET    /api/user/cart` — get saved cart
- `PUT    /api/user/cart` — save cart
- `DELETE /api/user/account` — delete account

---

## Google OAuth Setup

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a project → APIs & Services → Credentials
3. Create an **OAuth 2.0 Client ID** (Web application type)
4. Add your domain to **Authorised JavaScript origins**, e.g. `https://auragifts.mv`
5. Copy the **Client ID** into:
   - `.env` → `GOOGLE_CLIENT_ID=your_client_id`
   - `pages/login.html` → `const GOOGLE_CLIENT_ID = 'your_client_id'`
   - `pages/register.html` → `const GOOGLE_CLIENT_ID = 'your_client_id'`

---

## Security Features

- bcrypt password hashing (12 rounds)
- Timing-safe login (constant-time dummy hash check on missing accounts)
- Rate limiting: 6 login attempts/min, 5 registrations/min, 3 password resets/15min
- Bearer token auth with expiry (12h normal, 30d with "remember me")
- All sessions invalidated on password change
- Input sanitisation and length limits on all endpoints
- CORS configured with `supports_credentials: true`
- Google ID tokens verified against Google's tokeninfo endpoint
- Email enumeration prevention on forgot-password endpoint

---

## Mail (Password Reset & Email Verification)

Configure your SMTP provider in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourprovider.com
MAIL_PORT=587
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@aura.gifts"
MAIL_FROM_NAME="Aura Gifts"
```

Recommended providers: [Resend](https://resend.com), [Mailgun](https://mailgun.com), [Postmark](https://postmarkapp.com).

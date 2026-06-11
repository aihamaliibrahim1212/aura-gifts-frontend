<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CacheController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\UserAuthController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

// ── Rate limiter for login ─────────────────────────────────────────────────
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(10)->by($request->ip());
});

RateLimiter::for('user_login', function (Request $request) {
    // 6 attempts per minute per IP — brute-force protection
    return Limit::perMinute(6)->by($request->ip());
});

RateLimiter::for('user_register', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});

RateLimiter::for('password_reset', function (Request $request) {
    // Very conservative — 3 per 15 minutes per IP
    return Limit::perMinutes(15, 3)->by($request->ip());
});

RateLimiter::for('orders', function (Request $request) {
    return Limit::perMinutes(5, 5)->by($request->ip());
});

// ── Public routes ──────────────────────────────────────────────────────────
Route::get('/products/featured',    [PublicController::class, 'featuredProducts']);
Route::get('/products',             [PublicController::class, 'products']);
Route::get('/products/{id}',        [PublicController::class, 'product']);
Route::get('/reviews',              [PublicController::class, 'reviews']);
Route::get('/faq',                  [PublicController::class, 'faq']);
Route::get('/content/{key}',        [PublicController::class, 'content']);
Route::get('/banners',              [PublicController::class, 'banners']);
Route::middleware('throttle:orders')->post('/orders', [PublicController::class, 'createOrder']);

// ── Auth ───────────────────────────────────────────────────────────────────
Route::middleware('throttle:login')->post('/auth/login',  [AdminController::class, 'login']);
Route::post('/auth/logout', [AdminController::class, 'logout']);
Route::get('/auth/me',      [AdminController::class, 'me']);

// ── Admin routes (protected by user token with admin/superadmin role) ─────
Route::middleware('user.admin.auth')->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);

    // Cache
    Route::post('/increment-cache-versions', [CacheController::class, 'incrementVersions']);

    // Products
    Route::get('/products',                         [AdminController::class, 'listProducts']);
    Route::post('/products',                        [AdminController::class, 'createProduct']);
    Route::post('/products/featured',               [AdminController::class, 'setFeatured']);
    Route::post('/products/reorder',                [AdminController::class, 'reorderProducts']);
    Route::get('/products/{id}',                    [AdminController::class, 'getProduct']);
    Route::put('/products/{id}',                    [AdminController::class, 'updateProduct']);
    Route::delete('/products/{id}',                 [AdminController::class, 'deleteProduct']);
    Route::post('/products/{id}/upload-image',      [AdminController::class, 'uploadProductImage']);

    // Orders
    Route::get('/orders',           [AdminController::class, 'listOrders']);
    Route::get('/orders/{id}',      [AdminController::class, 'getOrder']);
    Route::put('/orders/{id}',      [AdminController::class, 'updateOrder']);
    Route::delete('/orders/{id}',   [AdminController::class, 'deleteOrder']);

    // Reviews
    Route::get('/reviews',          [AdminController::class, 'listReviews']);
    Route::post('/reviews',         [AdminController::class, 'createReview']);
    Route::put('/reviews/{id}',     [AdminController::class, 'updateReview']);
    Route::delete('/reviews/{id}',  [AdminController::class, 'deleteReview']);

    // FAQ
    Route::get('/faq',              [AdminController::class, 'listFaq']);
    Route::post('/faq',             [AdminController::class, 'createFaq']);
    Route::post('/faq/reorder',     [AdminController::class, 'reorderFaq']);
    Route::put('/faq/{id}',         [AdminController::class, 'updateFaq']);
    Route::delete('/faq/{id}',      [AdminController::class, 'deleteFaq']);

    // Content
    Route::get('/content',          [AdminController::class, 'listContent']);
    Route::put('/content',          [AdminController::class, 'bulkUpdateContent']);
    Route::get('/content/{key}',    [AdminController::class, 'getContentByKey']);
    Route::put('/content/{key}',    [AdminController::class, 'updateContentByKey']);

    // Banners
    Route::get('/banners',                          [AdminController::class, 'listBanners']);
    Route::post('/banners',                         [AdminController::class, 'createBanner']);
    Route::put('/banners/{id}',                     [AdminController::class, 'updateBanner']);
    Route::delete('/banners/{id}',                  [AdminController::class, 'deleteBanner']);
    Route::post('/banners/{id}/upload-image',       [AdminController::class, 'uploadBannerImage']);

    // Logos
    Route::post('/logos/{type}/upload', [AdminController::class, 'uploadLogo']);

    // Users
    Route::get('/users',            [AdminController::class, 'listUsers']);
    Route::post('/users',           [AdminController::class, 'createUser']);
    Route::put('/users/{id}',       [AdminController::class, 'updateUser']);
    Route::delete('/users/{id}',    [AdminController::class, 'deleteUser']);
});

// ── Customer auth (public) ─────────────────────────────────────────────────
Route::middleware('throttle:user_register')->post('/user/register', [UserAuthController::class, 'register']);
Route::middleware('throttle:user_login')->post('/user/login',       [UserAuthController::class, 'login']);
Route::post('/user/logout',                                          [UserAuthController::class, 'logout']);
Route::get('/user/me',                                               [UserAuthController::class, 'me']);
Route::middleware('throttle:user_login')->post('/user/google',       [UserAuthController::class, 'googleAuth']);
Route::middleware('throttle:password_reset')->post('/user/forgot-password',  [UserAuthController::class, 'forgotPassword']);
Route::middleware('throttle:password_reset')->post('/user/reset-password',   [UserAuthController::class, 'resetPassword']);
Route::get('/user/verify-email',                                     [UserAuthController::class, 'verifyEmail']);

// ── Customer protected routes ──────────────────────────────────────────────
Route::middleware('user.auth')->prefix('user')->group(function () {
    Route::put('/profile',  [UserAuthController::class, 'updateProfile']);
    Route::get('/orders',   [UserAuthController::class, 'orderHistory']);
    Route::get('/cart',     [UserAuthController::class, 'getCart']);
    Route::put('/cart',     [UserAuthController::class, 'saveCart']);
    Route::delete('/account', [UserAuthController::class, 'deleteAccount']);

    // Admin-only: manage customer user roles
    Route::get('/admin/users',            [UserAuthController::class, 'adminListUsers']);
    Route::put('/admin/users/{id}/role',  [UserAuthController::class, 'adminSetUserRole']);
});

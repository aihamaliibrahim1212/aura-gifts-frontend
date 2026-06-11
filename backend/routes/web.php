<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

// Site root ,  one level up from backend-php/
$siteDir = realpath(__DIR__ . '/../../');

// Helper to serve a file with no-cache for HTML
function serveFile(string $path, bool $noCache = false): BinaryFileResponse
{
    $response = response()->file($path);
    if ($noCache) {
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
    }
    return $response;
}

// ── Main site ─────────────────────────────────────────────────────────────

Route::get('/', function() use ($siteDir) {
    // SSR: inline critical data directly into the HTML for zero-delay first paint
    $html = file_get_contents($siteDir . '/frontend/index.html');

    // Fetch banners and featured products from DB (cached for 60s to reduce response time)
    $banners  = \Illuminate\Support\Facades\Cache::remember('ssr_banners', 60, fn() =>
        \App\Models\Banner::where('is_active', true)->orderBy('sort_order')->get()->map->toApiArray()->values()->toArray()
    );
    $products = \Illuminate\Support\Facades\Cache::remember('ssr_products', 30, fn() =>
        \App\Models\Product::where('is_active', true)->where('featured', true)->orderBy('sort_order')->limit(6)->get()->map->toApiArray()->values()->toArray()
    );
    $reviews = \Illuminate\Support\Facades\Cache::remember('ssr_reviews', 60, fn() =>
        \App\Models\Review::where('is_approved', true)->orderByDesc('created_at')->get()->toArray()
    );

    // Build banner slides HTML directly (like aivahome.mv - no JS injection needed)
    $slidesHtml = '';
    $preloadLinks = '';
    foreach ($banners as $i => $b) {
        $src = $b['image_url'] ?? '';
        if ($src) {
            // Preload ALL banner images so clones don't show grey
            $preloadLinks .= "<link rel=\"preload\" as=\"image\" href=\"{$src}\" fetchpriority=\"" . ($i === 0 ? 'high' : 'low') . "\">";
        }
        $slidesHtml .= "<div class=\"banner-slide\">"
            . "<img src=\"{$src}\" alt=\"Banner\" class=\"banner-slide-img\" loading=\"eager\" decoding=\"async\" fetchpriority=\"" . ($i === 0 ? 'high' : 'low') . "\">"
            . "</div>";
    }

    // Inject preload links into <head>
    if ($preloadLinks) {
        $html = str_replace('</head>', $preloadLinks . '</head>', $html);
    }

    // Replace empty banner-slides div with actual HTML content
    $html = preg_replace(
        '/<div class="banner-slides" id="banner-slides"><\/div>/',
        '<div class="banner-slides" id="banner-slides">' . $slidesHtml . '</div>',
        $html
    );

    // Fix favicon and apple touch icon to use Cloudinary
    $logoSquareVal = \Illuminate\Support\Facades\Cache::remember('ssr_logo_square', 300, fn() =>
        optional(\App\Models\SiteContent::where('key', 'logo_square')->first())->value ?? ''
    );
    if ($logoSquareVal && str_starts_with($logoSquareVal, 'http')) {
        $html = str_replace('href="img/logos/auragiftslogo.jpeg"', 'href="' . $logoSquareVal . '"', $html);
    }
    $logoWideVal = \Illuminate\Support\Facades\Cache::remember('ssr_logo_wide', 300, fn() =>
        optional(\App\Models\SiteContent::where('key', 'logo_wide')->first())->value ?? ''
    );
    if ($logoWideVal && str_starts_with($logoWideVal, 'http')) {
        $html = str_replace('src="img/logos/auragiftswidelogo.jpeg"', 'src="' . $logoWideVal . '"', $html);
        $html = str_replace('</head>', '<link rel="preload" as="image" href="' . $logoWideVal . '">' . '</head>', $html);
    }
    $topBarVal = \Illuminate\Support\Facades\Cache::remember('ssr_top_bar_text', 300, fn() =>
        optional(\App\Models\SiteContent::where('key', 'top_bar_text')->first())->value ?? ''
    );
    if ($topBarVal) {
        $html = preg_replace(
            '/(<div[^>]+id="top-bar-text"[^>]*>)[^<]*(<\/div>)/i',
            '$1' . htmlspecialchars($topBarVal, ENT_QUOTES) . '$2',
            $html
        );
    }
    $bannersJson  = json_encode(array_values($banners));
    $productsJson = json_encode(array_values($products));

    // Build product grid HTML server-side so cards are in HTML before JS runs
    $gridHtml = '';
    $productPreloadLinks = '';
    foreach ($products as $i => $p) {
        $src    = $p['image_url'] ?? '';
        // Preload all product images
        if ($src) $productPreloadLinks .= "<link rel=\"preload\" as=\"image\" href=\"{$src}\">";
        $name   = htmlspecialchars($p['name'] ?? '', ENT_QUOTES);
        $desc   = htmlspecialchars($p['description'] ?? '', ENT_QUOTES);
        $badge  = $p['badge'] ?? '';
        $stock  = (int)($p['stock'] ?? 0);
        $price  = 'MVR ' . number_format((float)($p['price_mvr'] ?? 0), 0, '.', ',');
        $inStock = $stock > 0;
        $stockLabel = $stock === 0
            ? '<span class="stock-pill out">Out of Stock</span>'
            : ($stock <= 3 ? "<span class=\"stock-pill low\">Only {$stock} left</span>" : "<span class=\"stock-pill in\">{$stock} in stock</span>");
        $badgeHtml = $badge ? '<span class="product-badge">' . htmlspecialchars($badge, ENT_QUOTES) . '</span>' : '';
        $tagHtml   = $badge ? '<div class="product-tag">'   . htmlspecialchars($badge, ENT_QUOTES) . '</div>'   : '';
        $onclick   = $inStock ? "openModal({$i})" : '';
        $btnOnclick = $inStock ? "event.stopPropagation(); addToCart({$i})" : '';
        $disabled  = $inStock ? '' : 'disabled';
        $btnLabel  = $inStock ? 'Add to Cart' : 'Unavailable';

        $gridHtml .= '<div class="product-card' . (!$inStock ? ' out-of-stock' : '') . '">'
            . '<div class="product-img">'
            . "<img src=\"{$src}\" alt=\"{$name}\" loading=\"eager\" style=\"width:100%;height:100%;object-fit:cover;display:block;\">"
            . $badgeHtml . '</div>'
            . '<div class="product-body">' . $tagHtml
            . "<div class=\"product-name\">{$name}</div>"
            . "<div class=\"product-desc\">{$desc}</div></div>"
            . '<div class="product-footer">' . $stockLabel
            . '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">'
            . "<span style=\"font-weight:700;font-size:1rem;color:var(--text-dark);\">{$price}</span>"
            . "<button class=\"btn-add\" {$disabled} onclick=\"{$btnOnclick}\">{$btnLabel}</button>"
            . '</div></div></div>';
    }
    if ($gridHtml) {
        $html = str_replace(
            '<div class="products-grid" id="products-grid"></div>',
            '<div class="products-grid" id="products-grid">' . $gridHtml . '</div>',
            $html
        );
    }

    // Inject product image preloads into head
    if ($productPreloadLinks) {
        $html = str_replace('</head>', $productPreloadLinks . '</head>', $html);
    }

    // Build reviews marquee HTML server-side to prevent layout shift on refresh
    if (count($reviews)) {
        $items = $reviews;
        // Ensure enough cards for seamless loop (minimum 8)
        while (count($items) < 8) $items = array_merge($items, $reviews);
        $cardHtml = '';
        foreach ($items as $r) {
            $name     = htmlspecialchars($r['reviewer_name'] ?? 'A', ENT_QUOTES);
            $location = htmlspecialchars($r['reviewer_location'] ?? '', ENT_QUOTES);
            $text     = htmlspecialchars($r['text'] ?? '', ENT_NOQUOTES);
            $rating   = (int)($r['rating'] ?? 5);
            $initial  = strtoupper(substr($r['reviewer_name'] ?? 'A', 0, 1));
            $stars    = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
            $cardHtml .= '<div class="testimonial-card">'
                . "<div class=\"stars\">{$stars}</div>"
                . "<p class=\"testimonial-text\">{$text}</p>"
                . '<div class="testimonial-author">'
                . "<div class=\"author-avatar\" style=\"background:#b8a898;\">{$initial}</div>"
                . '<div>'
                . "<div class=\"author-name\">{$name}</div>"
                . "<div class=\"author-loc\">{$location}</div>"
                . '</div></div></div>';
        }
        // Two identical sets for seamless infinite scroll
        $marqueeHtml = $cardHtml . $cardHtml;
        $duration = count($items) * 4;
        $html = str_replace(
            '<div class="reviews-marquee" id="reviews-grid"></div>',
            '<div class="reviews-marquee" id="reviews-grid" style="animation-duration:' . $duration . 's;">' . $marqueeHtml . '</div>',
            $html
        );
    }

    $inject = "<script>window.__SSR__={banners:{$bannersJson},products:{$productsJson}};</script>";
    $html = str_replace('</head>', $inject . '</head>', $html);

    return response($html, 200)
        ->header('Content-Type', 'text/html; charset=utf-8')
        ->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=60');
});
Route::get('/index.html', fn() => redirect('/'));
Route::get('/404', fn() => serveFile($siteDir . '/frontend/404.html'));

Route::get('/pages/{file}', function (string $file) use ($siteDir) {
    $path = $siteDir . '/frontend/pages/' . basename($file);
    if (!file_exists($path)) abort(404);

    // SSR for content pages — inject DB content directly to eliminate flash
    $ssrPages = ['terms.html', 'privacy.html', 'about.html', 'faq.html', 'hampers.html', 'search.html', 'personalised-hampers.html', 'create-your-own.html', 'corporate-orders.html'];
    if (!in_array(basename($file), $ssrPages)) {
        return serveFile($path);
    }

    $html = file_get_contents($path);

    // ── Inject logo and top bar across ALL pages ──────────────────
    $logoWide   = \App\Models\SiteContent::where('key', 'logo_wide')->first();
    $topBarText = \App\Models\SiteContent::where('key', 'top_bar_text')->first();
    if ($logoWide && $logoWide->value && str_starts_with($logoWide->value, 'http')) {
        $html = str_replace('src="../img/logos/auragiftswidelogo.jpeg"', 'src="' . $logoWide->value . '"', $html);
        $html = str_replace('src="https://res.cloudinary.com/dat7p3xuv/image/upload/v1780717076/aura-gifts/logos/tdpoqlfhze5zkuvdppgw.jpg"', 'src="' . $logoWide->value . '"', $html);
    }
    if ($topBarText && $topBarText->value) {
        $html = preg_replace('/(<div[^>]+id="top-bar-text"[^>]*>)[^<]*(<\/div>)/i', '$1' . htmlspecialchars($topBarText->value, ENT_QUOTES) . '$2', $html);
    }

    if (basename($file) === 'terms.html') {
        $item = \App\Models\SiteContent::where('key', 'terms_of_service')->first();
        if ($item && $item->value) {
            $content = '';
            try {
                $sections = json_decode($item->value, true);
                foreach ($sections as $s) {
                    $content .= ($s['heading'] ? '<h3>' . htmlspecialchars($s['heading'], ENT_QUOTES) . '</h3>' : '');
                    $content .= '<p>' . nl2br(htmlspecialchars($s['body'], ENT_QUOTES)) . '</p>';
                }
            } catch (\Exception $e) {
                $content = nl2br(htmlspecialchars($item->value, ENT_QUOTES));
            }
            $html = str_replace('<div class="legal-content" id="terms-content"></div>', '<div class="legal-content" id="terms-content">' . $content . '</div>', $html);
        }
    }

    if (basename($file) === 'privacy.html') {
        $item = \App\Models\SiteContent::where('key', 'privacy_policy')->first();
        if ($item && $item->value) {
            $content = '';
            try {
                $sections = json_decode($item->value, true);
                foreach ($sections as $s) {
                    $content .= ($s['heading'] ? '<h3>' . htmlspecialchars($s['heading'], ENT_QUOTES) . '</h3>' : '');
                    $content .= '<p>' . nl2br(htmlspecialchars($s['body'], ENT_QUOTES)) . '</p>';
                }
            } catch (\Exception $e) {
                $content = nl2br(htmlspecialchars($item->value, ENT_QUOTES));
            }
            $html = str_replace('<div class="legal-content" id="privacy-content"></div>', '<div class="legal-content" id="privacy-content">' . $content . '</div>', $html);
        }
    }

    if (basename($file) === 'about.html') {
        $keys = ['about_hero_subtitle','about_who_label','about_section_title','about_story_p1','about_story_p2','about_story_p3','about_cta_title','about_cta_subtitle'];
        $contentItems = \App\Models\SiteContent::whereIn('key', $keys)->get()->keyBy('key');
        $idMap = ['about_hero_subtitle'=>'about-hero-subtitle','about_who_label'=>'about-who-label','about_section_title'=>'about-section-title','about_story_p1'=>'about-p1','about_story_p2'=>'about-p2','about_story_p3'=>'about-p3','about_cta_title'=>'about-cta-title','about_cta_subtitle'=>'about-cta-subtitle'];
        foreach ($keys as $key) {
            if (isset($contentItems[$key]) && isset($idMap[$key])) {
                $val = htmlspecialchars($contentItems[$key]->value, ENT_QUOTES);
                $id  = $idMap[$key];
                $html = preg_replace('/(<[^>]+id="' . $id . '"[^>]*>)([^<]*)(<\/[^>]+>)/i', '$1' . $val . '$3', $html);
            }
        }
    }

    if (basename($file) === 'faq.html') {
        // Inject FAQ items server-side
        $faqs = \App\Models\Faq::where('is_active', true)->orderBy('sort_order')->get();
        if ($faqs->count()) {
            $faqHtml = $faqs->map(function($f, $i) {
                $q = htmlspecialchars($f->question, ENT_QUOTES);
                $a = htmlspecialchars($f->answer, ENT_QUOTES);
                return '<div class="faq-item" id="faq-' . $i . '">'
                    . '<button class="faq-question" onclick="toggleFaq(' . $i . ')">'
                    . '<span>' . $q . '</span>'
                    . '<i class="fas fa-chevron-down"></i>'
                    . '</button>'
                    . '<div class="faq-answer"><p>' . $a . '</p></div>'
                    . '</div>';
            })->join('');
            $html = str_replace('<div class="faq-list" id="faq-list">', '<div class="faq-list" id="faq-list">' . $faqHtml, $html);
        }
    }

    if (basename($file) === 'hampers.html') {
        $products = \App\Models\Product::where('is_active', true)->orderBy('sort_order')->orderByDesc('created_at')->get();
        if ($products->count()) {
            $gridHtml = '';
            foreach ($products as $i => $p) {
                $src    = $p->image_url ?? '';
                $name   = htmlspecialchars($p->name ?? '', ENT_QUOTES);
                $desc   = htmlspecialchars($p->description ?? '', ENT_QUOTES);
                $badge  = $p->badge ?? '';
                $stock  = (int)($p->stock ?? 0);
                $price  = 'MVR ' . number_format((float)($p->price_mvr ?? 0), 0, '.', ',');
                $inStock = $stock > 0;
                $stockLabel = $stock === 0
                    ? '<span class="stock-pill out">Out of Stock</span>'
                    : ($stock <= 3 ? '<span class="stock-pill low">Only ' . $stock . ' left</span>' : '<span class="stock-pill in">' . $stock . ' in stock</span>');
                $badgeHtml = $badge ? '<span class="product-badge">' . htmlspecialchars($badge, ENT_QUOTES) . '</span>' : '';
                $tagHtml   = $badge ? '<div class="product-tag">' . htmlspecialchars($badge, ENT_QUOTES) . '</div>' : '';
                $onclick   = $inStock ? "openModal({$i})" : '';
                $btnOnclick = $inStock ? "event.stopPropagation(); addToCart({$i})" : '';
                $disabled  = $inStock ? '' : 'disabled';
                $btnLabel  = $inStock ? 'Add to Cart' : 'Unavailable';
                $gridHtml .= '<div class="product-card' . (!$inStock ? ' out-of-stock' : '') . '">'
                    . '<div class="product-img"><img src="' . $src . '" alt="' . $name . '" loading="eager" style="width:100%;height:100%;object-fit:cover;display:block;">' . $badgeHtml . '</div>'
                    . '<div class="product-body">' . $tagHtml . '<div class="product-name">' . $name . '</div><div class="product-desc">' . $desc . '</div></div>'
                    . '<div class="product-footer">' . $stockLabel
                    . '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">'
                    . '<span style="font-weight:700;font-size:1rem;color:var(--text-dark);">' . $price . '</span>'
                    . '<button class="btn-add" ' . $disabled . ' onclick="' . $btnOnclick . '">' . $btnLabel . '</button>'
                    . '</div></div></div>';
            }
            $html = str_replace('<div class="products-grid" id="shop-grid"></div>', '<div class="products-grid" id="shop-grid">' . $gridHtml . '</div>', $html);
            // Inject product data for JS (search, cart)
            $productsJson = $products->map->toApiArray()->values()->toJson();
            $html = str_replace('</head>', '<script>window.__SSR_PRODUCTS__=' . $productsJson . ';</script></head>', $html);
        }
    }

    // ── Personalised Hampers SSR ──────────────────────────────────────────
    if (basename($file) === 'personalised-hampers.html') {
        $keys = ['personalised_hero_label','personalised_hero_heading','personalised_hero_sub',
                 'personalised_card1_title','personalised_card1_body',
                 'personalised_card2_title','personalised_card2_body',
                 'personalised_card3_title','personalised_card3_body',
                 'personalised_cta_title','personalised_cta_sub','personalised_cta_email_subject'];
        $items = \App\Models\SiteContent::whereIn('key', $keys)->get()->keyBy('key');
        $inject = '<script>window.__PH__={';
        foreach ($keys as $k) {
            $val = $items[$k]->value ?? '';
            $inject .= json_encode($k) . ':' . json_encode($val) . ',';
        }
        $inject .= '};</script>';
        $html = str_replace('</head>', $inject . '</head>', $html);
    }

    // ── Create Your Own SSR ───────────────────────────────────────────────
    if (basename($file) === 'create-your-own.html') {
        $keys = ['cyo_hero_label','cyo_hero_heading','cyo_hero_sub',
                 'cyo_step1_title','cyo_step1_body','cyo_step2_title','cyo_step2_body',
                 'cyo_step3_title','cyo_step3_body','cyo_step4_title','cyo_step4_body',
                 'cyo_cta_title','cyo_cta_sub','cyo_cta_email_subject'];
        $items = \App\Models\SiteContent::whereIn('key', $keys)->get()->keyBy('key');
        $inject = '<script>window.__CYO__={';
        foreach ($keys as $k) {
            $val = $items[$k]->value ?? '';
            $inject .= json_encode($k) . ':' . json_encode($val) . ',';
        }
        $inject .= '};</script>';
        $html = str_replace('</head>', $inject . '</head>', $html);
    }

    // ── Corporate Orders SSR ──────────────────────────────────────────────
    if (basename($file) === 'corporate-orders.html') {
        $keys = ['corporate_hero_label','corporate_hero_heading','corporate_hero_sub',
                 'corporate_card1_title','corporate_card1_body',
                 'corporate_card2_title','corporate_card2_body',
                 'corporate_card3_title','corporate_card3_body',
                 'corporate_stat1_value','corporate_stat1_label',
                 'corporate_stat2_value','corporate_stat2_label',
                 'corporate_stat3_value','corporate_stat3_label',
                 'corporate_cta_title','corporate_cta_sub','corporate_cta_email_subject'];
        $items = \App\Models\SiteContent::whereIn('key', $keys)->get()->keyBy('key');
        $inject = '<script>window.__CO__={';
        foreach ($keys as $k) {
            $val = $items[$k]->value ?? '';
            $inject .= json_encode($k) . ':' . json_encode($val) . ',';
        }
        $inject .= '};</script>';
        $html = str_replace('</head>', $inject . '</head>', $html);
    }

    return response($html, 200)
        ->header('Content-Type', 'text/html; charset=utf-8')
        ->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=60');
});

Route::get('/sw.js', function () use ($siteDir) {
    $path = $siteDir . '/sw.js';
    if (!file_exists($path)) abort(404);
    return response(file_get_contents($path), 200)
        ->header('Content-Type', 'application/javascript; charset=utf-8')
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
        ->header('Service-Worker-Allowed', '/');
});

Route::get('/css/{file}', function (string $file) use ($siteDir) {
    $file = strtok($file, '?');
    $path = $siteDir . '/frontend/css/' . basename($file);
    if (!file_exists($path)) abort(404);
    $etag    = '"' . md5_file($path) . '"';
    $lastMod = gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT';
    // Return 304 if browser already has the current version
    if (request()->header('If-None-Match') === $etag) {
        return response('', 304)
            ->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age=31536000, immutable');
    }
    $content = file_get_contents($path);
    return response($content, 200)
        ->header('Content-Type', 'text/css; charset=utf-8')
        ->header('Content-Length', strlen($content))
        ->header('Cache-Control', 'public, max-age=31536000, immutable')
        ->header('ETag', $etag)
        ->header('Last-Modified', $lastMod);
});

Route::get('/js/{file}', function (string $file) use ($siteDir) {
    $file = strtok($file, '?');
    $path = $siteDir . '/frontend/js/' . basename($file);
    if (!file_exists($path)) abort(404);
    $etag    = '"' . md5_file($path) . '"';
    $lastMod = gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT';
    if (request()->header('If-None-Match') === $etag) {
        return response('', 304)
            ->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age=31536000, immutable');
    }
    $content = file_get_contents($path);
    return response($content, 200)
        ->header('Content-Type', 'application/javascript; charset=utf-8')
        ->header('Content-Length', strlen($content))
        ->header('Cache-Control', 'public, max-age=31536000, immutable')
        ->header('ETag', $etag)
        ->header('Last-Modified', $lastMod);
});

Route::get('/img/{any}', function (string $any) use ($siteDir) {
    $path = $siteDir . '/img/' . $any;
    if (!file_exists($path)) abort(404);
    return response()->file($path);
})->where('any', '.*');

Route::get('/favicon.ico', function () use ($siteDir) {
    return response()->file($siteDir . '/img/logos/auragiftslogo.jpeg');
});

// ── Admin panel ──────────────────────────────────────────────────────────

$adminDir = $siteDir . '/backend/admin-panel/';

Route::get('/admin', fn() => serveFile($adminDir . 'index.html', true));
Route::get('/admin/', fn() => serveFile($adminDir . 'index.html', true));

Route::get('/admin/{file}', function (string $file) use ($adminDir) {
    $path = $adminDir . $file;
    if (!file_exists($path)) abort(404);
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $mimes = [
        'css'  => 'text/css; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'html' => 'text/html; charset=utf-8',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'svg'  => 'image/svg+xml',
        'woff2'=> 'font/woff2',
    ];
    $headers = isset($mimes[$ext]) ? ['Content-Type' => $mimes[$ext]] : [];
    if ($ext === 'html') {
        $headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
        $headers['Pragma'] = 'no-cache';
    }
    return response()->file($path, $headers);
})->where('file', '.*');

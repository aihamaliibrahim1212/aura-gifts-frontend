<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Models\AdminToken;
use App\Models\Product;
use App\Models\Order;
use App\Models\Review;
use App\Models\Faq;
use App\Models\SiteContent;
use App\Models\Banner;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    private function ok($data, int $status = 200)
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    private function err(string $msg, int $status = 400)
    {
        return response()->json(['success' => false, 'error' => $msg], $status);
    }

    private function cloudinary(): Cloudinary
    {
        return new Cloudinary(
            Configuration::instance([
                'cloud' => [
                    'cloud_name' => config('cloudinary.cloud_name'),
                    'api_key'    => config('cloudinary.api_key'),
                    'api_secret' => config('cloudinary.api_secret'),
                ],
                'url' => ['secure' => true],
            ])
        );
    }

    private function currentUser(): ?\App\Models\User
    {
        $id = request()->get('_admin_user_id');
        return $id ? \App\Models\User::find($id) : null;
    }

    // ── Auth ─────────────────────────────────────────────────────────────

    public function login(Request $request)
    {
        $data = $request->json()->all();
        $login = $data['username'] ?? $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$login || !$password) {
            return $this->err('Username/email and password are required');
        }

        if (strlen((string) $login) > 255 || strlen((string) $password) > 255) {
            return $this->err('Invalid credentials', 401);
        }

        $user = AdminUser::where('username', $login)->orWhere('email', $login)->first();

        if (!$user || !$user->is_active) {
            return $this->err('Invalid credentials', 401);
        }

        if (!Hash::check($password, $user->password_hash)) {
            return $this->err('Invalid credentials', 401);
        }

        // Generate token valid for 12 hours
        $token = bin2hex(random_bytes(32));
        AdminToken::create([
            'user_id'    => $user->id,
            'token'      => $token,
            'expires_at' => now()->addHour(),
        ]);

        // Clean up old tokens for this user
        AdminToken::where('user_id', $user->id)
            ->where('expires_at', '<', now())
            ->delete();

        return $this->ok(['token' => $token, 'user' => $user->toApiArray()]);
    }

    public function logout(Request $request)
    {
        $bearer = $request->bearerToken();
        if ($bearer) {
            AdminToken::where('token', $bearer)->delete();
        }
        return $this->ok(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        $bearer = $request->bearerToken();
        if (!$bearer) return $this->err('Unauthorized', 401);

        $tokenRecord = AdminToken::where('token', $bearer)
            ->where('expires_at', '>', now())
            ->first();

        if (!$tokenRecord) return $this->err('Unauthorized', 401);

        $user = AdminUser::find($tokenRecord->user_id);
        if (!$user) return $this->err('User not found', 404);
        return $this->ok($user->toApiArray());
    }

    // ── Maintenance Mode ───────────────────────────────────────────────────

    public function setMaintenance(Request $request)
    {
        $data = $request->json()->all();
        $enabled = isset($data['enabled']) ? ($data['enabled'] ? '1' : '0') : '0';
        SiteContent::updateOrCreate(
            ['key' => 'maintenance_mode'],
            ['value' => $enabled, 'content_type' => 'text']
        );
        return $this->ok(['maintenance' => $enabled === '1']);
    }

    // ── Dashboard ─────────────────────────────────────────────────────────

    public function dashboard()
    {
        $totalProducts  = Product::where('is_active', true)->count();
        $totalOrders    = Order::count();
        $pendingOrders  = Order::where('status', 'pending')->count();
        $totalRevenue   = Order::where('status', 'delivered')->sum('total_mvr');
        $lowStock       = Product::where('is_active', true)->where('stock', '<=', 3)->get();
        $recentOrders   = Order::orderByDesc('created_at')->limit(5)->get();
        $recentReviews  = Review::orderByDesc('created_at')->limit(5)->get();

        return $this->ok([
            'total_products'    => $totalProducts,
            'total_orders'      => $totalOrders,
            'pending_orders'    => $pendingOrders,
            'total_revenue'     => round((float) $totalRevenue, 2),
            'low_stock_products'=> $lowStock->map->toApiArray()->values(),
            'recent_orders'     => $recentOrders->map->toApiArray()->values(),
            'recent_reviews'    => $recentReviews->map->toApiArray()->values(),
        ]);
    }

    // ── Products ──────────────────────────────────────────────────────────

    public function listProducts()
    {
        $products = Product::orderByDesc('created_at')->get();
        return $this->ok($products->map->toApiArray()->values());
    }

    public function createProduct(Request $request)
    {
        $data = $request->json()->all();
        if (empty($data['name']) || !isset($data['price_mvr'])) {
            return $this->err('name and price_mvr are required');
        }
        $product = Product::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? '',
            'price_mvr'   => $data['price_mvr'],
            'stock'       => $data['stock'] ?? 0,
            'category'    => $data['category'] ?? '',
            'badge'       => $data['badge'] ?? '',
            'image_url'   => $data['image_url'] ?? '',
            'is_active'   => $data['is_active'] ?? true,
        ]);
        return $this->ok($product->toApiArray(), 201);
    }

    public function getProduct(int $id)
    {
        $product = Product::find($id);
        if (!$product) return $this->err('Product not found', 404);
        return $this->ok($product->toApiArray());
    }

    public function updateProduct(Request $request, int $id)
    {
        $product = Product::find($id);
        if (!$product) return $this->err('Product not found', 404);
        $data = $request->json()->all();
        $fields = ['name','description','price_mvr','stock','category','badge','image_url','cloudinary_public_id','is_active'];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) $product->$f = $data[$f];
        }
        $product->save();
        return $this->ok($product->toApiArray());
    }

    public function deleteProduct(int $id)
    {
        $product = Product::find($id);
        if (!$product) return $this->err('Product not found', 404);
        if ($product->cloudinary_public_id) {
            try { $this->cloudinary()->uploadApi()->destroy($product->cloudinary_public_id); } catch (\Exception $e) {}
        }
        $product->delete();
        return $this->ok(['message' => 'Product deleted']);
    }

    public function uploadProductImage(Request $request, int $id)
    {
        $product = Product::find($id);
        if (!$product) return $this->err('Product not found', 404);
        if (!$request->hasFile('image')) return $this->err('No image file provided');

        if ($product->cloudinary_public_id) {
            try { $this->cloudinary()->uploadApi()->destroy($product->cloudinary_public_id); } catch (\Exception $e) {}
        }
        try {
            $result = $this->cloudinary()->uploadApi()->upload(
                $request->file('image')->getRealPath(),
                ['folder' => 'aura-gifts/products']
            );
            $product->image_url = $result['secure_url'];
            $product->cloudinary_public_id = $result['public_id'];
            $product->save();
            return $this->ok(['image_url' => $result['secure_url'], 'public_id' => $result['public_id']]);
        } catch (\Exception $e) {
            return $this->err('Upload failed: ' . $e->getMessage(), 500);
        }
    }

    public function setFeatured(Request $request)
    {
        $data = $request->json()->all();
        if (!is_array($data)) return $this->err('Expected a list');
        Product::query()->update(['featured' => false]);
        foreach (array_slice($data, 0, 6) as $item) {
            $p = Product::find($item['id'] ?? null);
            if ($p) { $p->featured = true; $p->sort_order = $item['sort_order'] ?? 0; $p->save(); }
        }
        return $this->ok(['message' => 'Featured updated']);
    }

    public function reorderProducts(Request $request)
    {
        $data = $request->json()->all();
        if (!is_array($data)) return $this->err('Expected a list');
        foreach ($data as $item) {
            $p = Product::find($item['id'] ?? null);
            if ($p) { $p->sort_order = $item['sort_order'] ?? 0; $p->save(); }
        }
        $products = Product::orderBy('sort_order')->get();
        return $this->ok($products->map->toApiArray()->values());
    }

    // ── Orders ────────────────────────────────────────────────────────────

    public function listOrders(Request $request)
    {
        $validStatuses = ['pending', 'confirmed', 'delivered', 'cancelled'];
        $status    = $request->status;
        $dateFrom  = $request->date_from;
        $dateTo    = $request->date_to;

        if ($status && !in_array($status, $validStatuses)) {
            return $this->err('Invalid status');
        }
        if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            return $this->err('Invalid date_from format');
        }
        if ($dateTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            return $this->err('Invalid date_to format');
        }

        $q = Order::query();
        if ($status)   $q->where('status', $status);
        if ($dateFrom) $q->where('created_at', '>=', $dateFrom);
        if ($dateTo)   $q->where('created_at', '<=', $dateTo . ' 23:59:59');
        $orders = $q->orderByDesc('created_at')->get();
        return $this->ok($orders->map->toApiArray()->values());
    }

    public function getOrder(int $id)
    {
        $order = Order::find($id);
        if (!$order) return $this->err('Order not found', 404);
        return $this->ok($order->toApiArray());
    }

    public function updateOrder(Request $request, int $id)
    {
        $order = Order::find($id);
        if (!$order) return $this->err('Order not found', 404);
        $data = $request->json()->all();
        $valid = ['pending','confirmed','delivered','cancelled'];
        if (isset($data['status'])) {
            if (!in_array($data['status'], $valid)) return $this->err('Invalid status');
            $newStatus = $data['status'];
            $oldStatus = $order->status;

            // Marking as delivered: deduct stock for each item
            if ($newStatus === 'delivered' && $oldStatus !== 'delivered') {
                foreach (($order->items ?? []) as $item) {
                    $name = substr((string)($item['name'] ?? ''), 0, 255);
                    if (!$name) continue;
                    $product = Product::where('name', $name)->first();
                    if ($product) {
                        $product->stock = max(0, $product->stock - (int)($item['qty'] ?? 1));
                        $product->save();
                    }
                }
            }

            // Un-marking delivered: restore stock
            if ($oldStatus === 'delivered' && $newStatus !== 'delivered') {
                foreach (($order->items ?? []) as $item) {
                    $name = substr((string)($item['name'] ?? ''), 0, 255);
                    if (!$name) continue;
                    $product = Product::where('name', $name)->first();
                    if ($product) {
                        $product->stock = $product->stock + (int)($item['qty'] ?? 1);
                        $product->save();
                    }
                }
            }

            $order->status = $newStatus;
        }
        if (isset($data['notes'])) $order->notes = $data['notes'];
        $order->save();
        return $this->ok($order->toApiArray());
    }

    public function deleteOrder(int $id)
    {
        $order = Order::find($id);
        if (!$order) return $this->err('Order not found', 404);

        // If order was delivered, restore stock when deleting
        if ($order->status === 'delivered') {
            foreach (($order->items ?? []) as $item) {
                $name = substr((string)($item['name'] ?? ''), 0, 255);
                if (!$name) continue;
                $product = Product::where('name', $name)->first();
                if ($product) {
                    $product->stock = $product->stock + (int)($item['qty'] ?? 1);
                    $product->save();
                }
            }
        }

        $order->delete();
        return $this->ok(['message' => 'Order deleted']);
    }

    // ── Reviews ───────────────────────────────────────────────────────────

    public function listReviews()
    {
        $reviews = Review::orderByDesc('created_at')->get();
        return $this->ok($reviews->map->toApiArray()->values());
    }

    public function createReview(Request $request)
    {
        $data = $request->json()->all();
        if (empty($data['reviewer_name']) || empty($data['text']) || !isset($data['rating'])) {
            return $this->err('reviewer_name, text, and rating are required');
        }
        $rating = (int) $data['rating'];
        if ($rating < 1 || $rating > 5) return $this->err('Rating must be 1-5');
        $review = Review::create([
            'reviewer_name'     => $data['reviewer_name'],
            'reviewer_location' => $data['reviewer_location'] ?? '',
            'rating'            => $rating,
            'text'              => $data['text'],
            'is_approved'       => $data['is_approved'] ?? true,
        ]);
        return $this->ok($review->toApiArray(), 201);
    }

    public function updateReview(Request $request, int $id)
    {
        $review = Review::find($id);
        if (!$review) return $this->err('Review not found', 404);
        $data = $request->json()->all();
        foreach (['reviewer_name','reviewer_location','text','is_approved'] as $f) {
            if (array_key_exists($f, $data)) $review->$f = $data[$f];
        }
        if (isset($data['rating'])) {
            $r = (int) $data['rating'];
            if ($r < 1 || $r > 5) return $this->err('Rating must be 1-5');
            $review->rating = $r;
        }
        $review->save();
        return $this->ok($review->toApiArray());
    }

    public function deleteReview(int $id)
    {
        $review = Review::find($id);
        if (!$review) return $this->err('Review not found', 404);
        $review->delete();
        return $this->ok(['message' => 'Review deleted']);
    }

    // ── FAQ ───────────────────────────────────────────────────────────────

    public function listFaq()
    {
        $faqs = Faq::orderBy('sort_order')->get();
        return $this->ok($faqs->map->toApiArray()->values());
    }

    public function createFaq(Request $request)
    {
        $data = $request->json()->all();
        if (empty($data['question']) || empty($data['answer'])) {
            return $this->err('question and answer are required');
        }
        $maxOrder = Faq::max('sort_order') ?? 0;
        $faq = Faq::create([
            'question'   => $data['question'],
            'answer'     => $data['answer'],
            'sort_order' => $data['sort_order'] ?? ($maxOrder + 1),
            'is_active'  => $data['is_active'] ?? true,
        ]);
        return $this->ok($faq->toApiArray(), 201);
    }

    public function updateFaq(Request $request, int $id)
    {
        $faq = Faq::find($id);
        if (!$faq) return $this->err('FAQ not found', 404);
        $data = $request->json()->all();
        foreach (['question','answer','sort_order','is_active'] as $f) {
            if (array_key_exists($f, $data)) $faq->$f = $data[$f];
        }
        $faq->save();
        return $this->ok($faq->toApiArray());
    }

    public function deleteFaq(int $id)
    {
        $faq = Faq::find($id);
        if (!$faq) return $this->err('FAQ not found', 404);
        $faq->delete();
        return $this->ok(['message' => 'FAQ deleted']);
    }

    public function reorderFaq(Request $request)
    {
        $data = $request->json()->all();
        if (!is_array($data)) return $this->err('Expected a list');
        foreach ($data as $item) {
            $faq = Faq::find($item['id'] ?? null);
            if ($faq) { $faq->sort_order = $item['sort_order'] ?? 0; $faq->save(); }
        }
        $faqs = Faq::orderBy('sort_order')->get();
        return $this->ok($faqs->map->toApiArray()->values());
    }

    // ── Content ───────────────────────────────────────────────────────────

    public function listContent()
    {
        $items = SiteContent::orderBy('key')->get();
        return $this->ok($items->map->toApiArray()->values());
    }

    public function bulkUpdateContent(Request $request)
    {
        $data = $request->json()->all();
        if (!is_array($data)) return $this->err('Expected a list');
        $user = $this->currentUser();
        $updated = [];
        foreach ($data as $item) {
            $key = $item['key'] ?? null;
            if (!$key) continue;
            $content = SiteContent::firstOrNew(['key' => $key]);
            $content->value        = $item['value'] ?? '';
            $content->content_type = $item['content_type'] ?? $content->content_type ?? 'text';
            $content->updated_by   = $user?->username;
            $content->save();
            $updated[] = $content->toApiArray();
        }
        return $this->ok($updated);
    }

    public function getContentByKey(string $key)
    {
        $item = SiteContent::where('key', $key)->first();
        if (!$item) return $this->err('Content not found', 404);
        return $this->ok($item->toApiArray());
    }

    public function updateContentByKey(Request $request, string $key)
    {
        $data = $request->json()->all();
        $user = $this->currentUser();
        $item = SiteContent::firstOrNew(['key' => $key]);
        $item->value        = $data['value'] ?? '';
        $item->content_type = $data['content_type'] ?? $item->content_type ?? 'text';
        $item->updated_by   = $user?->username;
        $item->save();
        return $this->ok($item->toApiArray());
    }

    // ── Banners ───────────────────────────────────────────────────────────

    public function listBanners()
    {
        $banners = Banner::orderBy('sort_order')->get();
        return $this->ok($banners->map->toApiArray()->values());
    }

    public function createBanner(Request $request)
    {
        $data = $request->json()->all();
        $maxOrder = Banner::max('sort_order') ?? 0;
        $banner = Banner::create([
            'eyebrow'    => $data['eyebrow'] ?? '',
            'title'      => $data['title'] ?? '',
            'subtitle'   => $data['subtitle'] ?? '',
            'image_url'  => $data['image_url'] ?? '',
            'sort_order' => $data['sort_order'] ?? ($maxOrder + 1),
            'is_active'  => $data['is_active'] ?? true,
        ]);
        return $this->ok($banner->toApiArray(), 201);
    }

    public function updateBanner(Request $request, int $id)
    {
        $banner = Banner::find($id);
        if (!$banner) return $this->err('Banner not found', 404);
        $data = $request->json()->all();
        foreach (['eyebrow','title','subtitle','image_url','sort_order','is_active'] as $f) {
            if (array_key_exists($f, $data)) $banner->$f = $data[$f];
        }
        $banner->save();
        return $this->ok($banner->toApiArray());
    }

    public function deleteBanner(int $id)
    {
        $banner = Banner::find($id);
        if (!$banner) return $this->err('Banner not found', 404);
        if ($banner->cloudinary_public_id) {
            try { $this->cloudinary()->uploadApi()->destroy($banner->cloudinary_public_id); } catch (\Exception $e) {}
        }
        $banner->delete();
        return $this->ok(['message' => 'Banner deleted']);
    }

    public function uploadBannerImage(Request $request, int $id)
    {
        $banner = Banner::find($id);
        if (!$banner) return $this->err('Banner not found', 404);
        if (!$request->hasFile('image')) return $this->err('No image file provided');
        if ($banner->cloudinary_public_id) {
            try { $this->cloudinary()->uploadApi()->destroy($banner->cloudinary_public_id); } catch (\Exception $e) {}
        }
        try {
            $result = $this->cloudinary()->uploadApi()->upload(
                $request->file('image')->getRealPath(),
                ['folder' => 'aura-gifts/banners']
            );
            $banner->image_url = $result['secure_url'];
            $banner->cloudinary_public_id = $result['public_id'];
            $banner->save();
            return $this->ok(['image_url' => $result['secure_url'], 'public_id' => $result['public_id']]);
        } catch (\Exception $e) {
            return $this->err('Upload failed: ' . $e->getMessage(), 500);
        }
    }

    // ── Logo Upload ───────────────────────────────────────────────────────

    public function uploadLogo(Request $request, string $type)
    {
        if (!in_array($type, ['logo_square', 'logo_wide'])) {
            return $this->err('Invalid logo type', 400);
        }
        if (!$request->hasFile('image')) return $this->err('No image file provided');

        $user = $this->currentUser();

        // Delete old Cloudinary image if exists
        $existing = SiteContent::where('key', $type . '_public_id')->first();
        if ($existing && $existing->value) {
            try { $this->cloudinary()->uploadApi()->destroy($existing->value); } catch (\Exception $e) {}
        }

        try {
            $result = $this->cloudinary()->uploadApi()->upload(
                $request->file('image')->getRealPath(),
                ['folder' => 'aura-gifts/logos']
            );
            // Save URL
            SiteContent::updateOrCreate(
                ['key' => $type],
                ['value' => $result['secure_url'], 'content_type' => 'text', 'updated_by' => $user?->username]
            );
            // Save public_id for future deletion
            SiteContent::updateOrCreate(
                ['key' => $type . '_public_id'],
                ['value' => $result['public_id'], 'content_type' => 'text', 'updated_by' => $user?->username]
            );
            return $this->ok(['url' => $result['secure_url']]);
        } catch (\Exception $e) {
            return $this->err('Upload failed: ' . $e->getMessage(), 500);
        }
    }

    public function incrementCacheVersions()
    {
        try {
            $basePath = base_path('../');
            $updatedFiles = [];

            // Find all HTML, CSS, and JS files
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($files as $file) {
                if (!$file->isFile()) continue;

                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['html', 'css', 'js', 'php'])) continue;

                // Skip vendor and node_modules
                $path = $file->getPathname();
                if (strpos($path, 'vendor') !== false || strpos($path, 'node_modules') !== false) continue;

                $content = file_get_contents($path);
                $originalContent = $content;

                // Match patterns like ?v=X and increment X
                $content = preg_replace_callback(
                    '/(\?v=|v=)(\d+)/i',
                    function ($matches) {
                        return $matches[1] . ((int)$matches[2] + 1);
                    },
                    $content
                );

                // If content changed, write it back
                if ($content !== $originalContent) {
                    file_put_contents($path, $content);
                    $updatedFiles[] = str_replace($basePath, '', $path);
                }
            }

            return $this->ok([
                'message' => 'Cache versions incremented',
                'files_updated' => count($updatedFiles),
                'files' => array_slice($updatedFiles, 0, 20) // Return first 20 for preview
            ]);
        } catch (\Exception $e) {
            return $this->err('Failed to increment versions: ' . $e->getMessage(), 500);
        }
    }

    // ── Users ─────────────────────────────────────────────────────────────
    {
        $requester = $this->currentUser();
        if (!$requester || $requester->role !== 'superadmin') {
            return $this->err('Superadmin access required', 403);
        }
        $users = \App\Models\User::orderByDesc('created_at')->get();
        return $this->ok($users->map->toApiArray()->values());
    }

    public function createUser(Request $request)
    {
        return $this->err('Users are created via Google Sign-In. Use the role endpoint to grant admin access.', 400);
    }

    public function updateUser(Request $request, int $id)
    {
        $requester = $this->currentUser();
        if (!$requester || $requester->role !== 'superadmin') {
            return $this->err('Superadmin access required', 403);
        }
        $target = \App\Models\User::find($id);
        if (!$target) return $this->err('User not found', 404);

        $data = $request->json()->all();
        $valid = ['customer', 'admin', 'superadmin'];

        if (isset($data['role'])) {
            if (!in_array($data['role'], $valid)) return $this->err('Invalid role');
            if ($requester->id === $id && $data['role'] !== 'superadmin') {
                return $this->err('You cannot remove your own superadmin role');
            }
            $target->role = $data['role'];
        }
        if (isset($data['is_active'])) {
            $target->is_active = (bool) $data['is_active'];
        }
        $target->save();
        return $this->ok($target->toApiArray());
    }

    public function deleteUser(int $id)
    {
        $requester = $this->currentUser();
        if (!$requester || $requester->role !== 'superadmin') {
            return $this->err('Superadmin access required', 403);
        }
        if ($requester->id === $id) return $this->err('Cannot delete your own account');
        $target = \App\Models\User::find($id);
        if (!$target) return $this->err('User not found', 404);
        \App\Models\Order::where('user_id', $id)->update(['user_id' => null]);
        $target->delete();
        return $this->ok(['message' => 'User deleted']);
    }
}

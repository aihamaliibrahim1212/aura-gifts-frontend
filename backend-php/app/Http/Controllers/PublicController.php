<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use App\Models\Faq;
use App\Models\SiteContent;
use App\Models\Order;
use App\Models\Banner;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    private function ok($data, int $status = 200)
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    private function err(string $msg, int $status = 400)
    {
        return response()->json(['success' => false, 'error' => $msg], $status);
    }

    // GET /api/status
    public function status()
    {
        $maintenance = SiteContent::where('key', 'maintenance_mode')->first();
        return $this->ok(['maintenance' => $maintenance && $maintenance->value === '1']);
    }

    // GET /api/products/featured
    public function featuredProducts()
    {
        $products = Product::where('is_active', true)
            ->where('featured', true)
            ->orderBy('sort_order')
            ->limit(6)
            ->get();
        return $this->ok($products->map->toApiArray()->values());
    }

    // GET /api/products
    public function products(Request $request)
    {
        $q = Product::where('is_active', true);
        if ($request->category) {
            $category = substr((string) $request->category, 0, 100);
            $q->where('category', $category);
        }
        $products = $q->orderBy('sort_order')->orderByDesc('created_at')->get();
        return $this->ok($products->map->toApiArray()->values());
    }

    // GET /api/products/{id}
    public function product(int $id)
    {
        $product = Product::where('id', $id)->where('is_active', true)->first();
        if (!$product) return $this->err('Product not found', 404);
        return $this->ok($product->toApiArray());
    }

    // GET /api/reviews
    public function reviews()
    {
        $reviews = Review::where('is_approved', true)
            ->orderByDesc('created_at')->get();
        return $this->ok($reviews->map->toApiArray()->values());
    }

    // GET /api/faq
    public function faq()
    {
        $faqs = Faq::where('is_active', true)->orderBy('sort_order')->get();
        return $this->ok($faqs->map->toApiArray()->values());
    }

    // GET /api/content/{key}
    public function content(string $key)
    {
        if (!preg_match('/^[a-zA-Z0-9_\-]{1,100}$/', $key)) {
            return $this->err('Invalid key', 400);
        }
        $item = SiteContent::where('key', $key)->first();
        if (!$item) return $this->err('Content not found', 404);
        return $this->ok($item->toApiArray());
    }

    // GET /api/banners
    public function banners()
    {
        $banners = Banner::where('is_active', true)->orderBy('sort_order')->get();
        return $this->ok($banners->map->toApiArray()->values());
    }

    // POST /api/orders
    public function createOrder(Request $request)
    {
        $data = $request->json()->all();

        if (empty($data['customer_name']) || empty($data['customer_email']) || empty($data['items'])) {
            return $this->err('customer_name, customer_email, and items are required');
        }

        $items = $data['items'];
        if (!is_array($items) || count($items) === 0) {
            return $this->err('Items must be a non-empty list');
        }

        $total = 0;
        foreach ($items as $item) {
            $total += (float)($item['qty'] ?? 1) * (float)($item['price'] ?? 0);
        }

        // Optionally link this order to a logged-in customer account
        $userId = null;
        $bearerToken = $request->bearerToken();
        if ($bearerToken) {
            $tokenRecord = \App\Models\UserToken::where('token', $bearerToken)
                ->where('expires_at', '>', now())
                ->first();
            if ($tokenRecord) {
                $userId = $tokenRecord->user_id;
            }
        }

        $order = Order::create([
            'user_id'        => $userId,
            'customer_name'  => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_phone' => $data['customer_phone'] ?? '',
            'atoll'          => $data['atoll'] ?? '',
            'island'         => $data['island'] ?? '',
            'address'        => $data['address'] ?? '',
            'boat_name'      => $data['boat_name'] ?? '',
            'boat_number'    => $data['boat_number'] ?? '',
            'items'          => $items,
            'total_mvr'      => round($total, 2),
            'status'         => 'pending',
            'notes'          => $data['notes'] ?? '',
        ]);

        return $this->ok($order->toApiArray(), 201);
    }
}

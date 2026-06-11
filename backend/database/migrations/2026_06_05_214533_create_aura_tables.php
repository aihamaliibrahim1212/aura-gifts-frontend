<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Users
        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 80)->unique();
            $table->string('email', 120)->unique();
            $table->string('password_hash', 256);
            $table->string('role', 20)->default('editor');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Products
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->decimal('price_mvr', 10, 2);
            $table->integer('stock')->default(0);
            $table->string('category', 50)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('featured')->default(false);
            $table->string('badge', 50)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('cloudinary_public_id', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Orders
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name', 200);
            $table->string('customer_email', 200);
            $table->string('customer_phone', 50)->nullable();
            $table->json('items');
            $table->decimal('total_mvr', 10, 2)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Reviews
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('reviewer_name', 200);
            $table->string('reviewer_location', 200)->nullable();
            $table->integer('rating');
            $table->text('text');
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
        });

        // FAQs
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->text('answer');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Site Content
        Schema::create('site_content', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('content_type', 20)->default('text');
            $table->string('updated_by', 80)->nullable();
            $table->timestamps();
        });

        // Banners
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('eyebrow', 200)->nullable();
            $table->string('title', 300)->nullable();
            $table->text('subtitle')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('cloudinary_public_id', 200)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
        Schema::dropIfExists('site_content');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('admin_users');
    }
};

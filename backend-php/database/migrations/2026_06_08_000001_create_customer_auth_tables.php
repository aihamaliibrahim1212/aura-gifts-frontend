<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Customer accounts
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name', 200);
                $table->string('email', 200)->unique();
                $table->string('password_hash', 256)->nullable();
                $table->string('avatar_url', 500)->nullable();
                $table->string('provider', 20)->default('email');
                $table->string('google_id', 100)->nullable()->unique();
                $table->string('role', 20)->default('customer');
                $table->boolean('email_verified')->default(false);
                $table->string('email_verify_token', 64)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('email');
                $table->index('google_id');
            });
        }

        // Customer auth tokens
        if (!Schema::hasTable('user_tokens')) {
            Schema::create('user_tokens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('token', 64)->unique();
                $table->boolean('remember')->default(false);
                $table->timestamp('expires_at');
                $table->timestamps();
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->index(['token', 'expires_at']);
            });
        }

        // Password reset tokens
        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->id();
                $table->string('email', 200)->index();
                $table->string('token', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamps();
            });
        }

        // Saved carts
        if (!Schema::hasTable('saved_carts')) {
            Schema::create('saved_carts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->json('items')->nullable();
                $table->timestamps();
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // Add user_id to orders if not already there
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'user_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'user_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex(['user_id']);
                $table->dropColumn('user_id');
            });
        }
        Schema::dropIfExists('saved_carts');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('user_tokens');
        Schema::dropIfExists('users');
    }
};

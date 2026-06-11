<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('atoll', 100)->nullable()->after('customer_phone');
            $table->string('island', 100)->nullable()->after('atoll');
            $table->string('address', 300)->nullable()->after('island');
            $table->string('boat_name', 100)->nullable()->after('address');
            $table->string('boat_number', 50)->nullable()->after('boat_name');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['atoll', 'island', 'address', 'boat_name', 'boat_number']);
        });
    }
};

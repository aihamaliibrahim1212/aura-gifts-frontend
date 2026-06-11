<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('avatar_url');
            $table->string('atoll', 100)->nullable()->after('phone');
            $table->string('island', 100)->nullable()->after('atoll');
            $table->string('address', 300)->nullable()->after('island');
            $table->string('boat_name', 100)->nullable()->after('address');
            $table->string('boat_number', 50)->nullable()->after('boat_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'atoll', 'island', 'address', 'boat_name', 'boat_number']);
        });
    }
};

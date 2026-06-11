<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create default superadmin if none exists
        if (AdminUser::count() === 0) {
            AdminUser::create([
                'username'      => 'admin',
                'email'         => 'admin@auragifts.mv',
                'password_hash' => Hash::make('AuraAdmin2025!'),
                'role'          => 'superadmin',
                'is_active'     => true,
            ]);
            echo "Default admin created: admin / AuraAdmin2025!\n";
        }

        $this->call(ContentSeeder::class);
    }
}

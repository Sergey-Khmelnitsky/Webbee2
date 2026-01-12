<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Orchid\Support\Facades\Dashboard;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ]
        );
        
        // Set full permissions using Orchid's Dashboard facade
        $permissions = Dashboard::getAllowAllPermission();
        // Also add platform.main permission explicitly
        $permissions['platform.main'] = true;
        $user->permissions = $permissions;
        $user->save();
    }
}

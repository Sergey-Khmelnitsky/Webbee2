<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'name' => 'Men Haircut',
                'description' => 'Professional men\'s haircut service',
                'is_active' => true,
            ],
            [
                'name' => 'Women Haircut',
                'description' => 'Professional women\'s haircut service',
                'is_active' => true,
            ],
            [
                'name' => 'Hair Colouring',
                'description' => 'Professional hair colouring service',
                'is_active' => false,
            ],
        ];

        foreach ($services as $serviceData) {
            Service::updateOrCreate(
                ['name' => $serviceData['name']],
                $serviceData
            );
        }
    }
}

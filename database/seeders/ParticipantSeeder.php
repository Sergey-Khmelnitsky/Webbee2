<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ParticipantSeeder extends Seeder
{
    /**
     * Get list of 20 participants for use in appointments
     * 
     * @return array
     */
    public static function getParticipants(): array
    {
        return [
            ['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john.doe@example.com'],
            ['first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane.smith@example.com'],
            ['first_name' => 'Bob', 'last_name' => 'Johnson', 'email' => 'bob.johnson@example.com'],
            ['first_name' => 'Alice', 'last_name' => 'Williams', 'email' => 'alice.williams@example.com'],
            ['first_name' => 'Emma', 'last_name' => 'Brown', 'email' => 'emma.brown@example.com'],
            ['first_name' => 'Michael', 'last_name' => 'Davis', 'email' => 'michael.davis@example.com'],
            ['first_name' => 'Sarah', 'last_name' => 'Miller', 'email' => 'sarah.miller@example.com'],
            ['first_name' => 'David', 'last_name' => 'Wilson', 'email' => 'david.wilson@example.com'],
            ['first_name' => 'Lisa', 'last_name' => 'Moore', 'email' => 'lisa.moore@example.com'],
            ['first_name' => 'James', 'last_name' => 'Taylor', 'email' => 'james.taylor@example.com'],
            ['first_name' => 'Mary', 'last_name' => 'Anderson', 'email' => 'mary.anderson@example.com'],
            ['first_name' => 'Robert', 'last_name' => 'Thomas', 'email' => 'robert.thomas@example.com'],
            ['first_name' => 'Patricia', 'last_name' => 'Jackson', 'email' => 'patricia.jackson@example.com'],
            ['first_name' => 'William', 'last_name' => 'White', 'email' => 'william.white@example.com'],
            ['first_name' => 'Jennifer', 'last_name' => 'Harris', 'email' => 'jennifer.harris@example.com'],
            ['first_name' => 'Richard', 'last_name' => 'Martin', 'email' => 'richard.martin@example.com'],
            ['first_name' => 'Linda', 'last_name' => 'Thompson', 'email' => 'linda.thompson@example.com'],
            ['first_name' => 'Joseph', 'last_name' => 'Garcia', 'email' => 'joseph.garcia@example.com'],
            ['first_name' => 'Barbara', 'last_name' => 'Martinez', 'email' => 'barbara.martinez@example.com'],
            ['first_name' => 'Thomas', 'last_name' => 'Robinson', 'email' => 'thomas.robinson@example.com'],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $participants = self::getParticipants();
        $this->command->info('Prepared ' . count($participants) . ' participants data for use in appointments');
    }
}

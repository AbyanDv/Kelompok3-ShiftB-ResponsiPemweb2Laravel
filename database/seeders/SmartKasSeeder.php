<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SmartKasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['nim' => 'H1H024050'],
            [
                'name' => 'Bendahara',
                'email' => 'H1H024050@smartkas.local',
                'password' => 'password',
                'role' => 'admin',
                'status' => 'verified',
            ]
        );
    }
}

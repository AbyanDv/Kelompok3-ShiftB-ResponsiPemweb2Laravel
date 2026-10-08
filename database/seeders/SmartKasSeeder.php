<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\KasType;
use App\Models\User;
use Illuminate\Database\Seeder;

class SmartKasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admins = [
            ['nim' => 'ADM001', 'name' => 'Ketua Angkatan'],
        ];

        foreach ($admins as $admin) {
            User::updateOrCreate(
                ['nim' => $admin['nim']],
                [
                    'name' => $admin['name'],
                    'email' => $admin['nim'].'@smartkas.local',
                    'password' => 'password',
                    'role' => 'admin',
                    'status' => 'verified',
                ]
            );
        }

        for ($i = 1; $i <= 5; $i++) {
            User::updateOrCreate(
                ['nim' => 'H1H0240'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)],
                [
                    'name' => 'Anggota '.$i,
                    'email' => 'H1H0240'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'@smartkas.local',
                    'password' => 'password',
                    'role' => 'member',
                    'status' => 'verified',
                ]
            );
        }

        for ($i = 6; $i <= 7; $i++) {
            User::updateOrCreate(
                ['nim' => 'H1H0240'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)],
                [
                    'name' => 'Pendaftar '.$i,
                    'email' => 'H1H0240'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'@smartkas.local',
                    'password' => 'password',
                    'role' => 'member',
                    'status' => 'pending',
                ]
            );
        }

        $admin = User::where('nim', 'ADM001')->first();

        $kas = KasType::updateOrCreate(
            ['name' => 'Kas Oktober 2026'],
            [
                'description' => 'Iuran kas bulan Oktober 2026.',
                'amount' => 10000,
                'due_date' => '2026-10-31',
                'is_active' => true,
                'created_by' => $admin?->id,
            ]
        );

        User::where('role', 'member')->where('status', 'verified')->each(function ($user) use ($kas) {
            Bill::firstOrCreate(
                ['user_id' => $user->id, 'kas_type_id' => $kas->id],
                ['amount' => $kas->amount]
            );
        });
    }
}

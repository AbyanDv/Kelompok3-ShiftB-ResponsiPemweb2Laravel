<?php

namespace Tests;

use App\Models\Bill;
use App\Models\KasType;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function admin(string $nim = 'ADM001'): User
    {
        return User::factory()->create(['nim' => $nim, 'role' => 'admin', 'status' => 'verified']);
    }

    protected function member(string $nim = 'H1H024001'): User
    {
        return User::factory()->create(['nim' => $nim, 'role' => 'member', 'status' => 'verified']);
    }

    protected function paket(User $admin, int $amount = 10000): KasType
    {
        return KasType::create([
            'name' => 'Kas Uji', 'amount' => $amount,
            'due_date' => now()->addMonth()->toDateString(),
            'is_active' => true, 'created_by' => $admin->id,
        ]);
    }

    protected function tagihan(?User $member = null, ?KasType $kas = null, int $amount = 10000): Bill
    {
        $member ??= $this->member();

        return Bill::create([
            'user_id' => $member->id,
            'kas_type_id' => $kas?->id ?? $this->paket($this->admin())->id,
            'amount' => $amount,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebLedgerExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_catat_pengeluaran(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.ledger.store'), [
            'type' => 'expense',
            'category' => 'pengeluaran',
            'amount' => 15000,
            'entry_date' => now()->toDateString(),
            'description' => 'ATK',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('ledger_entries', [
            'type' => 'expense', 'category' => 'pengeluaran', 'amount' => 15000,
        ]);
        $this->assertSame(-15000, LedgerEntry::summary()['balance']);
    }

    public function test_nominal_nol_ditolak(): void
    {
        $this->actingAs($this->admin())->post(route('admin.ledger.store'), [
            'type' => 'expense',
            'category' => 'pengeluaran',
            'amount' => 0,
            'entry_date' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_admin_lihat_riwayat_keterangan(): void
    {
        $admin = $this->admin();
        LedgerEntry::create([
            'type' => 'expense', 'category' => 'pengeluaran', 'amount' => 5000,
            'description' => 'Fotokopi', 'entry_date' => now()->toDateString(),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('admin'))
            ->assertOk()
            ->assertSee('Fotokopi')
            ->assertSee('5.000', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\KasType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebBayarFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_dilempar_login_saat_buat_bayar(): void
    {
        $bill = $this->tagihan();

        $this->post(route('bayar.store', $bill))->assertRedirect(route('login'));
    }

    public function test_alur_kas_sampai_lunas(): void
    {
        $member = User::factory()->create(['role' => 'member', 'status' => 'verified']);
        $bill = $this->tagihan($member);

        // 1. tabel kas ada tombol bayar + link halaman bayar
        $this->actingAs($member)->get(route('kas'))
            ->assertOk()
            ->assertSee('Bayar', false)
            ->assertSee(route('bayar.show', $bill), false);

        // 1b. beranda ada kartu tagihan saya + tombol bayar
        $this->actingAs($member)->get(route('beranda'))
            ->assertOk()
            ->assertSee('Bayar sekarang', false);

        // 2. halaman bayar bisa dibuka
        $this->actingAs($member)->get(route('bayar.show', $bill))->assertOk();

        // 3. buat kode bayar → pending + QR tampil
        $this->actingAs($member)->post(route('bayar.store', $bill))
            ->assertRedirect()
            ->assertSessionHas('status');
        $payment = $bill->payments()->where('status', 'pending')->firstOrFail();
        $this->actingAs($member)->get(route('bayar.show', $bill))
            ->assertOk()
            ->assertSee($payment->order_id)
            ->assertSee('sbx-qr', false);

        // 4. simulasi → lunas + ledger
        $this->actingAs($member)->post(route('bayar.simulate', $payment))
            ->assertRedirect(route('bayar.show', $bill));
        $this->assertEquals('paid', $bill->fresh()->status);
        $this->assertTrue($payment->ledgerEntry()->exists());
    }

    public function test_paket_nonaktif_tidak_bisa_bayar(): void
    {
        $member = User::factory()->create(['role' => 'member', 'status' => 'verified']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'verified']);
        $kas = KasType::create([
            'name' => 'Kas Mati', 'amount' => 10000,
            'due_date' => now()->addMonth()->toDateString(),
            'is_active' => false, 'created_by' => $admin->id,
        ]);
        $bill = Bill::create(['user_id' => $member->id, 'kas_type_id' => $kas->id, 'amount' => 10000]);

        $this->actingAs($member)->post(route('bayar.store', $bill))
            ->assertRedirect()
            ->assertSessionHasErrors('bill');
        $this->assertEquals(0, $bill->payments()->count());

        $this->actingAs($member)->get(route('bayar.show', $bill))
            ->assertOk()
            ->assertSee('Nonaktif', false);
    }

    public function test_tamu_lihat_kas_tanpa_error(): void
    {
        $this->tagihan();
        $this->get(route('kas'))->assertOk();
    }

    private function tagihan(?User $member = null): Bill
    {
        $member ??= User::factory()->create(['role' => 'member', 'status' => 'verified']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'verified']);
        $kas = KasType::create([
            'name' => 'Kas Uji', 'amount' => 10000,
            'due_date' => now()->addMonth()->toDateString(),
            'is_active' => true, 'created_by' => $admin->id,
        ]);

        return Bill::create(['user_id' => $member->id, 'kas_type_id' => $kas->id, 'amount' => 10000]);
    }
}

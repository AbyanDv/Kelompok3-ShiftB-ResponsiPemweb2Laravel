<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\User;
use App\Services\SandboxPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TahapDuaTest extends TestCase
{
    use RefreshDatabase;

    public function test_buat_paket_menerbitkan_tagihan(): void
    {
        $admin = $this->admin();
        $this->member();
        $this->member('H1H024002');

        $res = $this->actingAs($admin, 'sanctum')->postJson('/api/kas-types', [
            'name' => 'Kas Oktober', 'amount' => 10000, 'due_date' => now()->addMonth()->toDateString(),
        ])->assertCreated()->assertJsonPath('success', true);

        $this->assertEquals(2, Bill::count());
        $this->assertTrue(Bill::where('amount', 10000)->count() === 2);
        $res->assertJsonPath('data.progress.total_bills', 2);
    }

    public function test_bayar_penuh_sampai_kas(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $kas = $this->paket($admin);
        $bill = Bill::create(['user_id' => $member->id, 'kas_type_id' => $kas->id, 'amount' => 10000]);

        $pay = $this->actingAs($member, 'sanctum')
            ->postJson("/api/bills/{$bill->id}/payments")
            ->assertCreated()->assertJsonPath('success', true);

        $paymentId = $pay->json('data.id');
        $this->assertEquals(11000, $pay->json('data.total_amount'));
        $this->assertNotEmpty($pay->json('data.qr_string'));

        // bayar dua kali tagihan lunas? belum lunas. tapi pending dipakai ulang:
        $reuse = $this->actingAs($member, 'sanctum')
            ->postJson("/api/bills/{$bill->id}/payments")
            ->assertOk();
        $this->assertEquals($paymentId, $reuse->json('data.id'));

        // simulasi bayar (mock)
        $this->actingAs($member, 'sanctum')
            ->postJson("/api/payments/{$paymentId}/simulate")
            ->assertOk()->assertJsonPath('data.status', 'paid');

        // tagihan lunas + kas bertambah nominal (tanpa fee)
        $this->assertEquals('paid', $bill->fresh()->status);
        $summary = $this->actingAs($member, 'sanctum')->getJson('/api/ledger-summary')->assertOk();
        $summary->assertJsonPath('data.total_income', 10000);
        $summary->assertJsonPath('data.balance', 10000);

        // bayar lagi tagihan lunas -> 409
        $this->actingAs($member, 'sanctum')
            ->postJson("/api/bills/{$bill->id}/payments")
            ->assertConflict();
    }

    public function test_bayar_tagihan_orang_lain_ditolak(): void
    {
        $admin = $this->admin();
        $a = $this->member('H1H024001');
        $b = $this->member('H1H024002');
        $kas = $this->paket($admin);
        $bill = Bill::create(['user_id' => $a->id, 'kas_type_id' => $kas->id, 'amount' => 10000]);

        $this->actingAs($b, 'sanctum')
            ->postJson("/api/bills/{$bill->id}/payments")
            ->assertForbidden();
    }

    public function test_pembayaran_manual_admin(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $kas = $this->paket($admin);
        $bill = Bill::create(['user_id' => $member->id, 'kas_type_id' => $kas->id, 'amount' => 10000]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/bills/{$bill->id}/manual-payments", ['note' => 'Tunai'])
            ->assertCreated()->assertJsonPath('data.status', 'paid');

        $this->assertEquals('paid', $bill->fresh()->status);
        $this->assertEquals(10000, $this->actingAs($member, 'sanctum')->getJson('/api/ledger-summary')->json('data.balance'));
    }

    public function test_nominal_dikunci_setelah_ada_yang_lunas(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $kas = $this->paket($admin);
        $bill = Bill::create(['user_id' => $member->id, 'kas_type_id' => $kas->id, 'amount' => 10000, 'status' => 'paid']);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/kas-types/{$kas->id}", ['amount' => 20000])
            ->assertConflict();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/kas-types/{$kas->id}")
            ->assertConflict();
    }

    public function test_entri_otomatis_tidak_bisa_diubah(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $kas = $this->paket($admin);
        $bill = Bill::create(['user_id' => $member->id, 'kas_type_id' => $kas->id, 'amount' => 10000]);
        $this->actingAs($admin, 'sanctum')->postJson("/api/bills/{$bill->id}/manual-payments");

        $entryId = LedgerEntry::whereNotNull('payment_id')->first()->id;

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/ledger-entries/{$entryId}", ['amount' => 1])
            ->assertStatus(422);
        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/ledger-entries/{$entryId}")
            ->assertStatus(422);
    }

    public function test_belum_verifikasi_tidak_bisa_bayar(): void
    {
        $this->admin();
        $pending = User::factory()->create(['nim' => 'H1H024009', 'role' => 'member', 'status' => 'pending']);

        $this->actingAs($pending, 'sanctum')
            ->getJson('/api/bills')
            ->assertForbidden();
    }

    public function test_webhook_valid_idempoten_dan_palsu_ditolak(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $kas = $this->paket($admin);
        $bill = Bill::create(['user_id' => $member->id, 'kas_type_id' => $kas->id, 'amount' => 10000]);

        $pay = $this->actingAs($member, 'sanctum')->postJson("/api/bills/{$bill->id}/payments")->assertCreated();
        $orderId = $pay->json('data.order_id');
        $total = $pay->json('data.total_amount');

        $gateway = new SandboxPaymentGateway;
        $payload = ['order_id' => $orderId, 'status' => 'paid', 'total_amount' => $total];
        $sig = $gateway->signWebhook($payload);

        $this->postJson('/api/webhooks/payment', $payload, ['X-Signature' => $sig])
            ->assertOk()->assertJsonPath('data.status', 'paid');

        // kirim ulang -> tetap satu pemasukan (idempoten)
        $this->postJson('/api/webhooks/payment', $payload, ['X-Signature' => $sig])->assertOk();
        $this->assertEquals(1, LedgerEntry::where('type', 'income')->count());

        // signature palsu
        $this->postJson('/api/webhooks/payment', $payload, ['X-Signature' => 'palsu'])
            ->assertUnauthorized();

        // nominal tidak cocok
        $salah = ['order_id' => $orderId, 'status' => 'paid', 'total_amount' => 1];
        $this->postJson('/api/webhooks/payment', $salah, ['X-Signature' => $gateway->signWebhook($salah)])
            ->assertStatus(422);
    }

    public function test_pengingat_tanpa_webhook_gagal_jelas(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $kas = $this->paket($admin);
        Bill::create(['user_id' => $member->id, 'kas_type_id' => $kas->id, 'amount' => 10000]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/reminders')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_pengeluaran_dan_saldo(): void
    {
        $admin = $this->admin();
        $member = $this->member();

        $this->actingAs($admin, 'sanctum')->postJson('/api/ledger-entries', [
            'type' => 'income', 'category' => 'saldo_awal', 'amount' => 50000,
            'description' => 'Saldo awal', 'entry_date' => now()->toDateString(),
        ])->assertCreated();

        $this->actingAs($admin, 'sanctum')->postJson('/api/ledger-entries', [
            'type' => 'expense', 'category' => 'pengeluaran', 'amount' => 15000,
            'description' => 'Beli spanduk', 'entry_date' => now()->toDateString(),
        ])->assertCreated();

        $this->actingAs($member, 'sanctum')->getJson('/api/ledger-summary')->assertOk()
            ->assertJsonPath('data.balance', 35000);
    }

    public function test_batal_tagihan_mematikan_pending(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $kas = $this->paket($admin);
        $bill = Bill::create(['user_id' => $member->id, 'kas_type_id' => $kas->id, 'amount' => 10000]);

        $pay = $this->actingAs($member, 'sanctum')->postJson("/api/bills/{$bill->id}/payments")->assertCreated();
        $orderId = $pay->json('data.order_id');
        $total = $pay->json('data.total_amount');

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/bills/{$bill->id}")->assertOk();
        $this->assertEquals('cancelled', Payment::where('order_id', $orderId)->first()->status);

        $gateway = new SandboxPaymentGateway;
        $payload = ['order_id' => $orderId, 'status' => 'paid', 'total_amount' => $total];
        $this->postJson('/api/webhooks/payment', $payload, ['X-Signature' => $gateway->signWebhook($payload)])
            ->assertStatus(422);

        $this->assertEquals('cancelled', $bill->fresh()->status);
        $this->assertEquals(0, LedgerEntry::where('type', 'income')->count());
    }

    public function test_verifikasi_menerbitkan_tagihan_aktif(): void
    {
        $admin = $this->admin();
        $kas = $this->paket($admin);
        $pending = User::factory()->create(['nim' => 'H1H024010', 'role' => 'member', 'status' => 'pending']);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/users/{$pending->id}", ['status' => 'verified'])
            ->assertOk();

        $this->assertTrue(Bill::where('user_id', $pending->id)->where('kas_type_id', $kas->id)->exists());
    }

    public function test_discord_gagal_tidak_ngaku_terkirim(): void
    {
        config()->set('services.discord.webhook_url', 'https://discord.test/hook');
        Http::fake(['*' => Http::response('err', 500)]);

        $admin = $this->admin();
        $member = $this->member();
        $kas = $this->paket($admin);
        Bill::create(['user_id' => $member->id, 'kas_type_id' => $kas->id, 'amount' => 10000]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/reminders')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}

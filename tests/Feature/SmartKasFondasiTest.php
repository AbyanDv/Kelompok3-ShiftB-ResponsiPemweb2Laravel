<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SmartKasFondasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_pending_dan_role_tidak_bisa_diisi(): void
    {
        $res = $this->postJson('/api/auth/register', [
            'nim' => 'H1H024001',
            'name' => 'Anggota Satu',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
            'status' => 'verified',
        ]);

        $res->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'member')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_login_nim_dan_me(): void
    {
        $this->admin();

        $login = $this->postJson('/api/auth/login', [
            'nim' => 'ADM001',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('success', true);

        $token = $login->json('data.token');
        $this->assertNotEmpty($token);

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.nim', 'ADM001');
    }

    public function test_admin_verifikasi_anggota(): void
    {
        $admin = $this->admin();
        $pending = User::factory()->create([
            'nim' => 'H1H024002',
            'role' => 'member',
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/users/{$pending->id}", ['status' => 'verified'])
            ->assertOk()
            ->assertJsonPath('data.status', 'verified');
    }

    public function test_member_bukan_admin_403(): void
    {
        $member = User::factory()->create([
            'nim' => 'H1H024003',
            'role' => 'member',
            'status' => 'verified',
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_admin_terakhir_tidak_boleh_hilang(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/users/{$admin->id}", ['role' => 'member'])
            ->assertConflict();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/users/{$admin->id}")
            ->assertConflict();
    }

    public function test_update_dengan_nim_sama_lolos(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create([
            'nim' => 'H1H024004',
            'role' => 'member',
            'status' => 'verified',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/users/{$member->id}", [
                'nim' => 'H1H024004',
                'name' => 'Nama Baru',
            ])
            ->assertOk()
            ->assertJsonPath('data.nim', 'H1H024004')
            ->assertJsonPath('data.name', 'Nama Baru');
    }

    public function test_command_bawaan_tetap_ada(): void
    {
        $this->artisan('inspire')->assertOk();
        $this->assertTrue(Route::has('users.index'));
    }
}

# Penjelasan Tahap 2 Smart-Kas — Panduan Teknis untuk Pemula

> Dokumentasi lengkap Tahap 2: paket kas, tagihan otomatis, pembayaran QRIS, buku kas, dan pengingat Discord.
> Semua istilah teknis dijelaskan dengan analogi ringkas, lalu langsung ke implementasi.

---

## Daftar Isi

- [Bab 0: Peta Perjalanan](#bab-0-peta-perjalanan)
- [Bab 1: Model KasType (Paket Kas)](#bab-1-model-kastype-paket-kas)
- [Bab 2: Service PaymentService](#bab-2-service-paymentservice)
- [Bab 3: Controller KasType](#bab-3-controller-kastype)
- [Bab 4: Controller Bill](#bab-4-controller-bill)
- [Bab 5: PaymentGateway Interface & Sandbox](#bab-5-paymentgateway-interface--sandbox)
- [Bab 6: Controller Payment](#bab-6-controller-payment)
- [Bab 7: Konfirmasi Pembayaran (Sandbox)](#bab-7-konfirmasi-pembayaran-sandbox)
- [Bab 8: Controller LedgerEntry (Buku Kas)](#bab-8-controller-ledgerentry-buku-kas)
- [Bab 9: Service DiscordReminder](#bab-9-service-discordreminder)
- [Bab 10: Pemicu Pengingat Discord (Manual)](#bab-10-pemicu-pengingat-discord-manual)
- [Bab 11: Route & Middleware](#bab-11-route--middleware)

---

## Bab 0: Peta Perjalanan

```
KAMIS PAGI                KAMIS SIANG               KAMIS SORE                KAMIS MALAM
     │                         │                         │                         │
     ▼                         ▼                         ▼                         ▼
Model KasType           PaymentService           Controller Payment       Simulasi Bayar
+ Migration             (issue bills,            (QRIS + tunai)          + Discord manual
(Bab 1)                 confirm paid)            (Bab 6)                  (Bab 7, 9-10)
                                   │
                                   ▼
                          Controller KasType
                          + Bill
                          (Bab 3-4)
```

**Hasil Akhir Tahap 2:**
- Admin buat paket → Tagihan otomatis untuk semua anggota verified
- Anggota bayar QRIS (sandbox) → Konfirmasi lewat simulasi → Buku kas terisi
- Admin catat tunai → Langsung lunas + buku kas
- Admin catat pengeluaran via form web → Kartu kas ikut berubah
- Buku kas real-time: saldo, pemasukan, pengeluaran
- Admin tekan tombol → pengingat tagihan belum bayar terkirim ke Discord

---

## Bab 1: Model KasType (Paket Kas)

### Migration

```php
// database/migrations/2026_10_07_204134_create_kas_types_table.php

Schema::create('kas_types', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->text('description')->nullable();
    $table->unsignedBigInteger('amount');
    $table->date('due_date');
    $table->boolean('is_active')->default(true);
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

**Penjelasan per kolom:**

```php
$table->unsignedBigInteger('amount');
```
Kolom di atas untuk → Simpan nominal dalam integer rupiah (tanpa desimal).  
Kenapa integer? Float punya error pembulatan. 10000.0 bisa jadi 9999.999999.

```php
$table->date('due_date');
```
Kolom di atas untuk → Tanggal jatuh tempo (tanpa jam).

```php
$table->boolean('is_active')->default(true);
```
Kolom di atas untuk → Saklar aktif/nonaktif. Paket dinonaktifkan, bukan dihapus, jika sudah ada yang bayar.

```php
$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
```
Kolom di atas untuk → Relasi ke admin pembuat.  
`nullOnDelete()` → Kalau admin dihapus, paket tetap ada, kolom jadi NULL.

### Model KasType

```php
// app/Models/KasType.php

class KasType extends Model
{
    protected $fillable = ['name', 'description', 'amount', 'due_date', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bills', 'kas_type_id', 'user_id')
            ->withPivot(['amount', 'status', 'paid_at'])
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

**Penjelasan per bagian:**

```php
public function scopeActive($query)
{
    return $query->where('is_active', true);
}
```
Method `scopeActive` di atas untuk → Query scope klasik Laravel.  
Pemakaian: `KasType::active()->get()` → hanya paket aktif.

**Kenapa prefix `scope`, bukan attribute?**

Cara klasik tetap didukung penuh dan dipakai di seluruh codebase ini (satu pola, mudah dicari).

```php
public function users(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'bills', 'kas_type_id', 'user_id')
        ->withPivot(['amount', 'status', 'paid_at'])
        ->withTimestamps();
}
```
Relasi many-to-many via tabel `bills`.  
Argumen 2-4 wajib karena tidak mengikuti konvensi penamaan default.

---

## Bab 2: Service PaymentService

**Konsep Service:** Class yang berisi business logic kompleks, dipisah dari controller.

### Method issueBillsForKas

```php
// app/Services/PaymentService.php

public static function issueBillsForKas(KasType $kas): int
{
    $n = 0;
    User::verifiedMember()->chunkById(100, function ($users) use ($kas, &$n) {
        foreach ($users as $u) {
            $bill = Bill::firstOrCreate(
                ['user_id' => $u->id, 'kas_type_id' => $kas->id],
                ['amount' => $kas->amount]
            );
            if ($bill->wasRecentlyCreated) {
                $n++;
            }
        }
    });

    return $n;
}
```

**Penjelasan per baris:**

```php
User::verifiedMember()->chunkById(100, function ($users) use ($kas, &$n) {
```
Method `chunkById(size, callback)` di atas untuk → Proses data dalam batch.

**Kenapa chunk, bukan `get()` semua?**

Kalau anggota 10.000 orang, `get()` load semua ke memory → crash.  
`chunkById(100)` → ambil 100, proses, ambil 100 berikutnya.

```php
$bill = Bill::firstOrCreate(
    ['user_id' => $u->id, 'kas_type_id' => $kas->id],
    ['amount' => $kas->amount]
);
```
Method `firstOrCreate(kriteria, data)` di atas untuk → Cari atau buat baru.

- Argumen 1: WHERE clause
- Argumen 2: Data untuk INSERT jika tidak ditemukan

**Hasil:**
- User sudah punya tagihan untuk paket ini → return existing
- User belum punya → create baru dengan `amount` dari paket

```php
if ($bill->wasRecentlyCreated) {
    $n++;
}
```
Property `wasRecentlyCreated` → `true` jika model baru dibuat di query ini.  
Dipakai untuk hitung berapa tagihan baru yang benar-benar dibuat.

**Idempotency:** Method ini bisa dipanggil berkali-kali tanpa dobel tagihan.

### Method issueBillsForUser

```php
public static function issueBillsForUser(User $user): int
{
    $n = 0;
    KasType::active()->get(['id', 'amount'])->each(function ($kas) use ($user, &$n) {
        $bill = Bill::firstOrCreate(
            ['user_id' => $user->id, 'kas_type_id' => $kas->id],
            ['amount' => $kas->amount]
        );
        if ($bill->wasRecentlyCreated) {
            $n++;
        }
    });

    return $n;
}
```

**Kapan dipanggil?**

Saat admin verifikasi anggota baru (`UserController@update`).

### Method confirmPaid

```php
public static function confirmPaid(Payment $payment, ?array $gatewayPayload = null): Payment
{
    return DB::transaction(function () use ($payment, $gatewayPayload) {
        $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

        if ($payment->status === 'paid') {
            return $payment;
        }

        $bill = $payment->bill()->lockForUpdate()->firstOrFail();

        if ($bill->status === 'cancelled') {
            abort(422, 'Tagihan sudah dibatalkan.');
        }

        if ($payment->channel === 'qris'
            && $payment->status === 'pending'
            && $payment->expires_at
            && $payment->expires_at->isPast()
        ) {
            $payment->update(['status' => 'expired']);
            abort(422, 'Pembayaran sudah kedaluwarsa.');
        }

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'gateway_payload' => $gatewayPayload ?? $payment->gateway_payload,
        ]);

        $bill->update(['status' => 'paid', 'paid_at' => now()]);

        LedgerEntry::firstOrCreate(
            ['payment_id' => $payment->id],
            [
                'type' => 'income',
                'category' => 'iuran',
                'amount' => $payment->amount,
                'description' => 'Iuran '.$bill->kasType->name.' — '.$bill->user->name,
                'entry_date' => now()->toDateString(),
                'created_by' => $payment->recorded_by ?? $bill->user_id,
            ]
        );

        return $payment->fresh();
    });
}
```

**Penjelasan per bagian:**

```php
return DB::transaction(function () use ($payment, $gatewayPayload) {
```
Transaction di atas untuk → Menjamin semua operasi berhasil atau semua dibatalkan.  
Kalau gagal di tengah, rollback otomatis.

```php
$payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
```
Method `lockForUpdate()` di atas untuk → Lock record agar tidak diubah proses lain.  
Penting untuk mencegah race condition saat konfirmasi datang dua kali bersamaan.

```php
if ($payment->status === 'paid') {
    return $payment;
}
```
Cek idempotency → kalau sudah paid, skip. Konfirmasi bisa dipanggil berkali-kali.

```php
$bill = $payment->bill()->lockForUpdate()->firstOrFail();
```
Lock bill juga → cegah kondisi race antara konfirmasi dan pembatalan.

```php
if ($payment->channel === 'qris' && ... && $payment->expires_at->isPast()) {
    $payment->update(['status' => 'expired']);
    abort(422, 'Pembayaran sudah kedaluwarsa.');
}
```
Validasi khusus QRIS → cek apakah sudah expired.  
Method `isPast()` dari Carbon → return `true` jika waktu sudah lewat.

```php
LedgerEntry::firstOrCreate(
    ['payment_id' => $payment->id],
    [...]
);
```
Buat entri buku kas dengan `firstOrCreate` → idempotent.  
Kalau `confirmPaid` dipanggil 2x, entri tetap 1.

**Flow lengkap konfirmasi:**

```
confirmPaid(masuk)  — lewat simulasi API, tombol web, atau webhook nanti
  ↓
Lock payment + bill
  ↓
Cek sudah paid? → skip
  ↓
Cek bill cancelled? → abort
  ↓
Cek expired? → abort
  ↓
Update payment = paid
  ↓
Update bill = paid
  ↓
Create ledger entry
  ↓
Commit transaction
```

### Method createPendingCharge

```php
public static function createPendingCharge(Bill $bill, PaymentGateway $gateway, string $prefix = 'SK-'): array
{
    self::assertPayable($bill);

    $existing = $bill->payments()
        ->where('status', 'pending')
        ->where('expires_at', '>', now())
        ->latest()
        ->first();

    if ($existing) {
        return [$existing, false];
    }

    $orderId = $prefix.$bill->id.'-'.Str::upper(Str::random(8));
    $charge = $gateway->createCharge($bill, $orderId);

    $payment = $bill->payments()->create([
        'order_id' => $orderId,
        'channel' => 'qris',
        'amount' => $bill->amount,
        'fee' => $charge['fee'],
        'total_amount' => $charge['total_amount'],
        'status' => 'pending',
        'gateway_ref' => $charge['gateway_ref'],
        'qr_string' => $charge['qr_string'],
        'expires_at' => $charge['expires_at'],
    ]);

    return [$payment, true];
}
```

**Penjelasan per bagian:**

```php
$existing = $bill->payments()
    ->where('status', 'pending')
    ->where('expires_at', '>', now())
    ->latest()
    ->first();

if ($existing) {
    return [$existing, false];
}
```
Cek apakah ada payment pending yang masih valid.  
Kalau ada → return yang lama, jangan buat baru.  
Hemat resource dan mencegah QRIS dobel.

```php
$orderId = $prefix.$bill->id.'-'.Str::upper(Str::random(8));
```
Generate order ID unik: `SK-7-ABC123XY`.  
Format: `{prefix}{bill_id}-{random}`.

```php
$charge = $gateway->createCharge($bill, $orderId);
```
Panggil gateway untuk membuat QRIS.  
Return: `gateway_ref`, `qr_string`, `expires_at`, `fee`, `total_amount`.

**Return value:**

```php
return [$payment, true];  // baru dibuat
return [$existing, false]; // pakai yang lama
```
Array dengan 2 elemen:
- Index 0: Payment object
- Index 1: Boolean apakah baru dibuat

**Kenapa return array, bukan hanya payment?**

Controller perlu tahu apakah baru dibuat untuk menampilkan message berbeda.

### Method recordCash

```php
public static function recordCash(Bill $bill, User $recorder, ?string $note = null): Payment
{
    return DB::transaction(function () use ($bill, $recorder, $note) {
        $bill = Bill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
        self::assertPayable($bill);

        $created = $bill->payments()->create([
            'order_id' => 'CASH-'.$bill->id.'-'.Str::upper(Str::random(8)),
            'channel' => 'cash',
            'amount' => $bill->amount,
            'fee' => 0,
            'total_amount' => $bill->amount,
            'status' => 'pending',
            'recorded_by' => $recorder->id,
            'note' => $note,
        ]);

        return self::confirmPaid($created);
    });
}
```

**Flow:**

1. Lock bill
2. Cek payable
3. Create payment dengan channel `cash` dan fee `0`
4. Langsung konfirmasi (tanpa webhook)

---

## Bab 3: Controller KasType

### Index

```php
// app/Http/Controllers/Api/KasTypeController.php

public function index(Request $request): JsonResponse
{
    $perPage = max(1, min((int) $request->query('per_page', 15), 100));

    $kas = KasType::query()
        ->withCount('bills')
        ->withCount(['bills as paid_bills_count' => fn ($q) => $q->where('status', 'paid')])
        ->withSum(['bills as collected_sum' => fn ($q) => $q->where('status', 'paid')], 'amount')
        ->when($request->query('is_active'), fn ($q, $v) => $q->where('is_active', filter_var($v, FILTER_VALIDATE_BOOLEAN)))
        ->when($request->query('q'), function ($q, $v) {
            $v = $this->escapeLike($v);
            $q->where(fn ($qq) => $qq->where('name', 'like', "%{$v}%")->orWhere('description', 'like', "%{$v}%"));
        })
        ->latest()
        ->paginate($perPage);

    return response()->json([...]);
}
```

**Penjelasan query baru:**

```php
->withCount('bills')
```
Load count total bills. Property: `$kas->bills_count`.

```php
->withCount(['bills as paid_bills_count' => fn ($q) => $q->where('status', 'paid')])
```
Load count bills dengan kondisi. Custom property name.

```php
->withSum(['bills as collected_sum' => fn ($q) => $q->where('status', 'paid')], 'amount')
```
Load SUM kolom `amount` dari relasi bills yang status `paid`.  
Property: `$kas->collected_sum`.

**SQL yang dihasilkan:**

```sql
SELECT kas_types.*,
       (SELECT COUNT(*) FROM bills WHERE bills.kas_type_id = kas_types.id) as bills_count,
       (SELECT COUNT(*) FROM bills WHERE bills.kas_type_id = kas_types.id AND status = 'paid') as paid_bills_count,
       (SELECT SUM(amount) FROM bills WHERE bills.kas_type_id = kas_types.id AND status = 'paid') as collected_sum
FROM kas_types
```

```php
filter_var($v, FILTER_VALIDATE_BOOLEAN)
```
Function di atas untuk → Konversi string ke boolean.  
`"true"` → `true`, `"false"` → `false`, `"1"` → `true`, `"0"` → `false`.

### Store

```php
public function store(StoreKasTypeRequest $request): JsonResponse
{
    [$kas, $generated] = DB::transaction(function () use ($request) {
        $kas = KasType::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return [$kas, PaymentService::issueBillsForKas($kas)];
    });

    $kas->loadCount('bills');

    return response()->json([
        'success' => true,
        'message' => "Paket kas dibuat. {$generated} tagihan diterbitkan.",
        'data' => new KasTypeResource($kas),
    ], 201);
}
```

**Penjelasan per bagian:**

```php
[$kas, $generated] = DB::transaction(function () use ($request) {
```
Destructuring assignment → ambil 2 nilai dari return array.  
Transaction menjamin: paket dan tagihan lahir bersamaan atau tidak sama sekali.

```php
$kas = KasType::create([
    ...$request->validated(),
    'created_by' => $request->user()->id,
]);
```
Spread operator `...` → unpack array validated ke dalam array create.  
Tambah `created_by` manual karena tidak ada di form.

```php
return [$kas, PaymentService::issueBillsForKas($kas)];
```
Return array: [Model, jumlah tagihan baru].

**Flow lengkap:**

```
Admin buat paket
  ↓
INSERT kas_types
  ↓
PaymentService::issueBillsForKas()
  ↓
For each verified member:
  - Cek apakah sudah ada tagihan
  - Jika belum → INSERT bills
  ↓
Commit transaction
  ↓
Return: "Paket kas dibuat. 50 tagihan diterbitkan."
```

### Update

```php
public function update(UpdateKasTypeRequest $request, KasType $kasType): JsonResponse
{
    $data = $request->validated();

    if (array_key_exists('amount', $data)
        && (int) $data['amount'] !== (int) $kasType->amount
        && $kasType->bills()->where('status', 'paid')->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Nominal tidak boleh diubah karena sudah ada tagihan lunas. Buat paket baru.',
        ], 409);
    }

    $kasType->update($data);

    return response()->json([...]);
}
```

**Validasi bisnis:**

```php
if (array_key_exists('amount', $data) && ... && $kasType->bills()->where('status', 'paid')->exists())
```
Cek tiga kondisi:
1. Field `amount` dikirim dalam request
2. Nilai baru berbeda dengan nilai lama
3. Sudah ada tagihan yang lunas

Jika semua true → tolak dengan 409 Conflict.

**Kenapa nominal tidak boleh diubah?**

Kalau nominal diubah setelah ada yang bayar:
- Anggota A bayar 10.000 (nominal lama)
- Anggota B belum bayar, lihat nominal 15.000 (baru)
- Tidak adil dan membingungkan

Solusi: nonaktifkan paket lama, buat paket baru.

### Destroy

```php
public function destroy(KasType $kasType): JsonResponse
{
    if ($kasType->bills()->where('status', 'paid')->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Paket tidak boleh dihapus karena sudah ada yang membayar. Nonaktifkan saja.',
        ], 409);
    }

    $kasType->delete();

    return response()->json([...]);
}
```

Aturan sama dengan update: tidak boleh hapus kalau ada yang sudah bayar.

### Bills

```php
public function bills(Request $request, KasType $kasType): JsonResponse
{
    $perPage = max(1, min((int) $request->query('per_page', 15), 100));

    $bills = $kasType->bills()->with('user')
        ->when($request->query('status'), fn ($q, $v) => $q->where('bills.status', $v))
        ->when($request->query('q'), function ($q, $v) {
            $v = $this->escapeLike($v);
            $q->whereHas('user', fn ($qq) => $qq->where('name', 'like', "%{$v}%")->orWhere('nim', 'like', "%{$v}%"));
        })
        ->latest()
        ->paginate($perPage);

    return response()->json([...]);
}
```

Endpoint: `GET /api/kas-types/{id}/bills`  
Purpose: Lihat status bayar semua anggota untuk satu paket.

---

## Bab 4: Controller Bill

### Index

```php
// app/Http/Controllers/Api/BillController.php

public function index(Request $request): JsonResponse
{
    $perPage = max(1, min((int) $request->query('per_page', 15), 100));
    $user = $request->user();

    $bills = Bill::query()->with(['user', 'kasType'])
        ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
        ->when($request->query('status'), fn ($q, $v) => $q->where('bills.status', $v))
        ->when($request->query('kas_type_id'), fn ($q, $v) => $q->where('kas_type_id', $v))
        ->when($request->query('q') && $user->isAdmin(), function ($q, $v) {
            $v = $this->escapeLike($v);
            $q->whereHas('user', fn ($qq) => $qq->where('name', 'like', "%{$v}%")->orWhere('nim', 'like', "%{$v}%"));
        })
        ->latest()
        ->paginate($perPage);

    return response()->json([...]);
}
```

**Authorization:**

```php
->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
```
Jika bukan admin → filter hanya tagihan milik sendiri.  
Admin → lihat semua tagihan.

```php
->when($request->query('q') && $user->isAdmin(), function ($q, $v) {
```
Pencarian hanya untuk admin. Anggota biasa tidak perlu search karena hanya punya beberapa tagihan.

### Show

```php
public function show(Request $request, Bill $bill): JsonResponse
{
    $user = $request->user();
    if (! $user->isAdmin() && $bill->user_id !== $user->id) {
        return response()->json([
            'success' => false,
            'message' => 'Tagihan ini bukan milikmu.',
        ], 403);
    }

    $bill->load(['user', 'kasType']);

    return response()->json([...]);
}
```

Authorization manual → cek kepemilikan sebelum tampilkan detail.

### Destroy

```php
public function destroy(Bill $bill): JsonResponse
{
    if ($bill->status === 'paid') {
        return response()->json([
            'success' => false,
            'message' => 'Tagihan lunas tidak boleh dibatalkan.',
        ], 409);
    }

    DB::transaction(function () use ($bill) {
        $bill->payments()->where('status', 'pending')->update(['status' => 'cancelled']);
        $bill->update(['status' => 'cancelled']);
    });

    return response()->json([...]);
}
```

**Flow pembatalan:**

1. Cek apakah sudah lunas → tolak
2. Dalam transaction:
   - Cancel semua payment pending
   - Set status bill = cancelled

**Kenapa perlu transaction?**

Kalau proses gagal di tengah (misal setelah cancel payments, sebelum update bill), data tidak konsisten.

---

## Bab 5: PaymentGateway Interface & Sandbox

### Interface

```php
// app/Services/PaymentGateway.php

interface PaymentGateway
{
    /** @return array{gateway_ref: string, qr_string: string, expires_at: \DateTimeInterface, fee: int, total_amount: int} */
    public function createCharge(Bill $bill, string $orderId): array;
}
```

**Kenapa pakai interface?**

1. **Testability** → Bisa inject mock di testing
2. **Swappability** → Ganti gateway tanpa ubah code logic
3. **Contract** → Pastikan semua gateway implement method yang sama

**PHPDoc `@return` array shape:**

```php
/** @return array{gateway_ref: string, qr_string: string, ...} */
```

Menjelaskan struktur array return → IDE bisa autocomplete.

### Sandbox Implementation

```php
// app/Services/SandboxPaymentGateway.php

class SandboxPaymentGateway implements PaymentGateway
{
    public function createCharge(Bill $bill, string $orderId): array
    {
        $fee = max(1000, (int) round($bill->amount * 0.007));
        $minutes = max(5, (int) config('services.payment.sandbox_expires', 30));

        return [
            'gateway_ref' => 'SBX-'.$orderId,
            'qr_string' => 'SANDBOX-QRIS:'.$orderId.':'.($bill->amount + $fee),
            'expires_at' => new DateTimeImmutable('+'.$minutes.' minutes'),
            'fee' => $fee,
            'total_amount' => $bill->amount + $fee,
        ];
    }
}
```

**Penjelasan per baris:**

```php
$fee = max(1000, (int) round($bill->amount * 0.007));
```
Hitung biaya admin: minimal Rp 1.000 atau 0.7% dari nominal.

```php
$minutes = max(5, (int) config('services.payment.sandbox_expires', 30));
```
Ambil konfigurasi expiry, minimal 5 menit.  
`config('key', default)` → ambil dari `.env` atau pakai default.

```php
'qr_string' => 'SANDBOX-QRIS:'.$orderId.':'.($bill->amount + $fee),
```
Generate string QRIS dummy untuk testing.  
Format: `SANDBOX-QRIS:{order_id}:{total}`.

```php
'expires_at' => new DateTimeImmutable('+'.$minutes.' minutes'),
```
Buat objek waktu kedaluwarsa.  
`DateTimeImmutable` → tidak bisa diubah setelah dibuat (lebih aman).

### Binding di ServiceProvider

```php
// app/Providers/AppServiceProvider.php

public function register(): void
{
    $this->app->bind(PaymentGateway::class, SandboxPaymentGateway::class);
}
```

Tanpa kondisi → sandbox dipakai di semua environment. Ganti ke gateway sungguhan nanti → cukup ubah satu baris ini.

**Dependency Injection:**

```php
private function gateway(): PaymentGateway
{
    return app(PaymentGateway::class);
}
```

Controller tidak tahu gateway mana yang dipakai.  
Container Laravel yang menentukan berdasarkan binding.

---

## Bab 6: Controller Payment

### Index

```php
// app/Http/Controllers/Api/PaymentController.php

public function index(Request $request): JsonResponse
{
    $perPage = max(1, min((int) $request->query('per_page', 15), 100));
    $user = $request->user();

    $payments = Payment::query()->with(['bill.user', 'bill.kasType'])
        ->when(! $user->isAdmin(), fn ($q) => $q->whereHas('bill', fn ($qq) => $qq->where('user_id', $user->id)))
        ->when($request->query('status'), fn ($q, $v) => $q->where('payments.status', $v))
        ->when($request->query('channel'), fn ($q, $v) => $q->where('channel', $v))
        ->when($request->query('date_from'), fn ($q, $v) => $q->whereDate('payments.created_at', '>=', $v))
        ->when($request->query('date_to'), fn ($q, $v) => $q->whereDate('payments.created_at', '<=', $v))
        ->latest()
        ->paginate($perPage);

    return response()->json([...]);
}
```

**Filter authorization:**

```php
->when(! $user->isAdmin(), fn ($q) => $q->whereHas('bill', fn ($qq) => $qq->where('user_id', $user->id)))
```
Anggota → hanya lihat payment milik sendiri (lewat relasi bill).

### StoreForBill (QRIS)

```php
public function storeForBill(Request $request, Bill $bill): JsonResponse
{
    $user = $request->user();

    if ($bill->user_id !== $user->id) {
        return response()->json([
            'success' => false,
            'message' => 'Kamu hanya bisa membayar tagihanmu sendiri.',
        ], 403);
    }

    [$payment, $baru] = PaymentService::createPendingCharge($bill, $this->gateway());

    if (! $baru) {
        return response()->json([
            'success' => true,
            'message' => 'Masih ada pembayaran menunggu. Pakai yang ini.',
            'data' => new PaymentResource($payment),
        ]);
    }

    return response()->json([
        'success' => true,
        'message' => 'Pembayaran dibuat. Pindai QRIS sebelum kedaluwarsa.',
        'data' => new PaymentResource($payment),
    ], 201);
}
```

**Flow:**

1. Cek kepemilikan tagihan
2. Panggil `createPendingCharge`
3. Jika ada payment pending yang masih valid → return yang lama
4. Jika baru → return dengan message berbeda

### StoreManual (Tunai)

```php
public function storeManual(ManualPaymentRequest $request, Bill $bill): JsonResponse
{
    $payment = PaymentService::recordCash($bill, $request->user(), $request->input('note'));

    return response()->json([
        'success' => true,
        'message' => 'Pembayaran tunai dicatat. Tagihan lunas.',
        'data' => new PaymentResource($payment),
    ], 201);
}
```

Satu baris → langsung lunas, tanpa QRIS.

### Destroy

```php
public function destroy(Request $request, Payment $payment): JsonResponse
{
    $user = $request->user();
    $payment->load('bill');

    if (! $user->isAdmin() && $payment->bill->user_id !== $user->id) {
        return response()->json([
            'success' => false,
            'message' => 'Pembayaran ini bukan milikmu.',
        ], 403);
    }

    if ($payment->status !== 'pending') {
        return response()->json([
            'success' => false,
            'message' => 'Hanya pembayaran menunggu yang bisa dibatalkan.',
        ], 409);
    }

    $payment->update(['status' => 'cancelled']);

    return response()->json([...]);
}
```

Hanya payment pending yang bisa dibatalkan.  
Paid → tidak bisa di-cancel (sudah ada di buku kas).

### Simulate

```php
public function simulate(Request $request, Payment $payment): JsonResponse
{
    if (app()->environment('production')) {
        return response()->json([
            'success' => false,
            'message' => 'Simulasi dimatikan di production.',
        ], 403);
    }

    // ... validasi ...

    $payment = PaymentService::confirmPaid($payment, [
        'simulated' => true,
        'order_id' => $payment->order_id,
        'status' => 'paid',
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Simulasi berhasil. Tagihan lunas.',
        'data' => new PaymentResource($payment),
    ]);
}
```

Endpoint khusus untuk testing → status `paid` tanpa gateway sungguhan.  
Cek environment di dalam method, bukan di route.

---

## Bab 7: Konfirmasi Pembayaran (Sandbox)

**Status jujur:** webhook gateway sungguhan **belum ada** di codebase ini. Yang ada:

1. `SandboxPaymentGateway` → palsu QRIS, tidak ada callback dari luar (sudah di Bab 5)
2. `confirmPaid()` → satu-satunya pintu masuk status `paid`, idempoten
3. Dua cara memanggilnya: **simulasi API** dan **tombol web**

### Binding di AppServiceProvider

```php
// app/Providers/AppServiceProvider.php

public function register(): void
{
    $this->app->bind(PaymentGateway::class, SandboxPaymentGateway::class);
}
```

### Cara 1: Simulasi API

```php
// app/Http/Controllers/Api/PaymentController.php

public function simulate(Request $request, Payment $payment): JsonResponse
{
    if (app()->environment('production')) {
        return response()->json([
            'success' => false,
            'message' => 'Simulasi dimatikan di production.',
        ], 403);
    }

    // ... cek kepemilikan, status pending, bill tidak cancelled ...

    $payment = PaymentService::confirmPaid($payment, [
        'simulated' => true,
        'order_id' => $payment->order_id,
        'status' => 'paid',
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Simulasi berhasil. Tagihan lunas.',
        'data' => new PaymentResource($payment),
    ]);
}
```

Endpoint `POST /api/payments/{payment}/simulate`.  
Cek environment di dalam method (route tetap terdaftar, production selalu 403).

### Cara 2: Tombol Web

```php
// routes/web.php

Route::post('/bayar/payment/{payment}/simulasi', function (Request $request, Payment $payment) use ($ownPayment) {
    $ownPayment($request, $payment);

    if (app()->environment('production')) {
        abort(403, 'Simulasi dimatikan di production.');
    }

    if ($payment->status !== 'pending') {
        return back()->withErrors(['payment' => 'Hanya pembayaran menunggu yang bisa disimulasikan.']);
    }

    PaymentService::confirmPaid($payment, ['simulated' => true, 'order_id' => $payment->order_id, 'status' => 'paid']);

    return redirect()->route('bayar.show', $payment->bill_id)->with('status', 'Simulasi berhasil. Tagihan lunas.');
})->name('bayar.simulate');
```

Sama-sama masuk lewat `confirmPaid` → perilaku identik di API dan web.

**Kenapa aman:**

1. **Idempotency** → `confirmPaid` cek status, jika sudah `paid` → skip, tidak dobel ledger
2. **Amount validation** → `confirmPaid` tidak percaya input, pakai data payment dari DB
3. **Production lock** → kedua jalur cek `app()->environment('production')`

Kalau nanti pakai gateway sungguhan: tambah route webhook + verifikasi signature → panggil `confirmPaid` yang sama. Tidak ada yang berubah di sisi buku kas.

---

## Bab 8: Controller LedgerEntry (Buku Kas)

### Index

```php
// app/Http/Controllers/Api/LedgerEntryController.php

public function index(Request $request): JsonResponse
{
    $perPage = max(1, min((int) $request->query('per_page', 15), 100));

    $entries = LedgerEntry::query()->with('creator')
        ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
        ->when($request->query('category'), fn ($q, $v) => $q->where('category', $v))
        ->when($request->query('date_from'), fn ($q, $v) => $q->whereDate('entry_date', '>=', $v))
        ->when($request->query('date_to'), fn ($q, $v) => $q->whereDate('entry_date', '<=', $v))
        ->when($request->query('q'), function ($q, $v) {
            $v = $this->escapeLike($v);
            $q->where(fn ($qq) => $qq->where('description', 'like', "%{$v}%")->orWhere('category', 'like', "%{$v}%"));
        })
        ->orderByDesc('entry_date')->latest()
        ->paginate($perPage);

    return response()->json([...]);
}
```

**Filter lengkap:**

- `type` → income/expense
- `category` → iuran, saldo_awal, pengeluaran, dll
- `date_from` & `date_to` → rentang tanggal
- `q` → pencarian di description atau category

**Sorting:**

```php
->orderByDesc('entry_date')->latest()
```
Urutkan berdasarkan tanggal entry, baru created_at untuk tie-breaker.

### Store

```php
public function store(StoreLedgerEntryRequest $request): JsonResponse
{
    $entry = LedgerEntry::create([
        ...$request->validated(),
        'created_by' => $request->user()->id,
    ]);

    return response()->json([...], 201);
}
```

Admin mencatat pengeluaran, saldo awal, atau pemasukan lain.

### Update

```php
public function update(UpdateLedgerEntryRequest $request, LedgerEntry $ledgerEntry): JsonResponse
{
    if ($ledgerEntry->payment_id !== null) {
        return response()->json([
            'success' => false,
            'message' => 'Entri otomatis dari pembayaran tidak boleh diubah.',
        ], 422);
    }

    $ledgerEntry->update($request->validated());

    return response()->json([...]);
}
```

**Proteksi:**

Entri dari pembayaran (`payment_id !== null`) tidak boleh diubah manual.  
Kalau perlu koreksi → hapus payment, buat ulang.

### Destroy

```php
public function destroy(LedgerEntry $ledgerEntry): JsonResponse
{
    if ($ledgerEntry->payment_id !== null) {
        return response()->json([
            'success' => false,
            'message' => 'Entri otomatis dari pembayaran tidak boleh dihapus.',
        ], 422);
    }

    $ledgerEntry->delete();

    return response()->json([...]);
}
```

Model sudah pakai `SoftDeletes` → hapus hanya isi `deleted_at`.

### Summary

```php
// app/Http/Controllers/Api/LedgerEntryController.php

public function summary(): JsonResponse
{
    $data = LedgerEntry::summary();

    return response()->json([
        'success' => true,
        'message' => 'Ringkasan saldo.',
        'data' => [
            'total_income' => $data['income'],
            'total_expense' => $data['expense'],
            'balance' => $data['balance'],
        ],
    ]);
}
```

Hitungan ada di model, bukan controller:

```php
// app/Models/LedgerEntry.php

public static function summary(): array
{
    $income = (int) static::where('type', 'income')->sum('amount');
    $expense = (int) static::where('type', 'expense')->sum('amount');

    return ['income' => $income, 'expense' => $expense, 'balance' => $income - $expense];
}
```

Baris di atas untuk → 2 query `SUM(amount)`, saldo = income - expense.  
Metode model yang sama dipakai halaman web `/beranda` dan `/kas` → API dan web tidak bisa beda angka.

**Saldo dihitung real-time:**

```sql
SELECT SUM(amount) FROM ledger_entries WHERE type = 'income';
SELECT SUM(amount) FROM ledger_entries WHERE type = 'expense';
```

Tidak disimpan di kolom → selalu akurat.

### Jalur Web (Form Pengeluaran)

Pengeluaran tidak lewat API. Admin isi form di halaman `/admin`:

```php
// routes/web.php

Route::post('/ledger', function (StoreLedgerEntryRequest $request) use ($admin) {
    $admin($request);

    LedgerEntry::create([
        ...$request->validated(),
        'type' => 'expense',
        'category' => 'pengeluaran',
        'created_by' => $request->user()->id,
    ]);

    return back()->with('status', 'Pengeluaran dicatat.');
})->name('ledger.store');
```

**Penjelasan:**

- `...$request->validated()` → spread field yang lolos validasi (amount, description, entry_date)
- `type` dipaksa `expense` → form web tidak bisa nyolong `income`
- `category` dipaksa `pengeluaran`
- `created_by` → siapa yang mencatat

**Yang menerima `cash`:**

```php
// routes/web.php — route /beranda dan /kas

'cash' => LedgerEntry::summary(),
```

Ringkasan `LedgerEntry::summary()` → dua kartu di beranda/kas: Pengeluaran + Sisa Kas.

**Riwayat pengeluaran** → 10 entri `expense` terbaru ditampilkan di bawah form admin.

**Tesnya:**

```php
// tests/Feature/WebLedgerExpenseTest.php

public function test_admin_catat_pengeluaran(): void
public function test_nominal_nol_ditolak(): void
public function test_admin_lihat_riwayat_keterangan(): void
```

3 tes → form menulis, validasi `amount > 0`, riwayat menampilkan keterangan.

---

## Bab 9: Service DiscordReminder

```php
// app/Services/DiscordReminder.php

class DiscordReminder
{
    /** @return array{sent: bool, message: string, unpaid_count: int} */
    public function send(): array
    {
        $kasTypes = KasType::where('is_active', true)
            ->with(['bills' => fn ($q) => $q->unpaid()->with('user')])
            ->get();

        $lines = [];
        foreach ($kasTypes as $kas) {
            foreach ($kas->bills as $bill) {
                if (! $bill->user || ! $bill->user->isVerified()) {
                    continue;
                }
                $lines[] = '- '.$bill->user->name.' ('.$bill->user->nim.') — '.$kas->name.' Rp '.number_format($bill->amount, 0, ',', '.');
            }
        }

        if ($lines === []) {
            return ['sent' => true, 'message' => 'Semua tagihan lunas. Tidak ada yang diingatkan.', 'unpaid_count' => 0];
        }

        $webhook = (string) config('services.discord.webhook_url', '');
        if ($webhook === '') {
            return ['sent' => false, 'message' => 'DISCORD_WEBHOOK_URL belum dikonfigurasi.', 'unpaid_count' => count($lines)];
        }

        $header = '**Smart-Kas**: '.count($lines).' tagihan belum lunas menjelang akhir bulan:';
        $chunks = $this->chunks($header, $lines);
        $failed = false;

        foreach ($chunks as $chunk) {
            try {
                $response = Http::timeout(10)->post($webhook, ['content' => $chunk]);
                if (! $response->successful()) {
                    Log::warning('Discord reminder ditolak: HTTP '.$response->status());
                    $failed = true;
                    break;
                }
            } catch (\Throwable $e) {
                Log::warning('Discord reminder gagal: '.$e->getMessage());
                $failed = true;
                break;
            }
        }

        if ($failed) {
            return ['sent' => false, 'message' => 'Gagal mengirim ke Discord, sudah dicatat di log.', 'unpaid_count' => count($lines)];
        }

        return ['sent' => true, 'message' => 'Pengingat terkirim ke Discord ('.count($lines).' tagihan).', 'unpaid_count' => count($lines)];
    }

    /** @param string[] $lines @return string[] */
    private function chunks(string $header, array $lines): array
    {
        $chunks = [];
        $current = $header;
        foreach ($lines as $line) {
            if (strlen($current) + strlen($line) + 1 > 2000) {
                $chunks[] = $current;
                $current = $header;
            }
            $current .= "\n".$line;
        }
        $chunks[] = $current;

        return $chunks;
    }
}
```

**Penjelasan per bagian:**

```php
$kasTypes = KasType::where('is_active', true)
    ->with(['bills' => fn ($q) => $q->unpaid()->with('user')])
    ->get();
```
Eager load bills unpaid + user.  
Scope `unpaid()` → `where('status', 'unpaid')`.

```php
$lines[] = '- '.$bill->user->name.' ('.$bill->user->nim.') — '.$kas->name.' Rp '.number_format($bill->amount, 0, ',', '.');
```
Format: `- Budi (H1H024001) — Kas Oktober Rp 10.000`

```php
if ($lines === []) {
    return ['sent' => true, 'message' => 'Semua tagihan lunas. Tidak ada yang diingatkan.', 'unpaid_count' => 0];
}
```
Kalau semua lunas → return early, tidak kirim webhook.

```php
$chunks = $this->chunks($header, $lines);
```

**Kenapa perlu chunk?**

Discord membatasi pesan maksimal 2000 karakter.  
Kalau 100 anggota belum bayar → 1 pesan bisa lebih dari 2000 karakter.

```php
private function chunks(string $header, array $lines): array
{
    $chunks = [];
    $current = $header;
    foreach ($lines as $line) {
        if (strlen($current) + strlen($line) + 1 > 2000) {
            $chunks[] = $current;
            $current = $header;
        }
        $current .= "\n".$line;
    }
    $chunks[] = $current;

    return $chunks;
}
```

Algoritma:
1. Mulai dengan header
2. Tambah baris satu per satu
3. Jika panjang > 2000 → simpan chunk, mulai baru
4. Return array of strings

```php
foreach ($chunks as $chunk) {
    try {
        $response = Http::timeout(10)->post($webhook, ['content' => $chunk]);
```

Kirim setiap chunk ke Discord.  
`timeout(10)` → batas waktu 10 detik.  
`post(url, data)` → HTTP POST.

```php
Log::warning('Discord reminder ditolak: HTTP '.$response->status());
```

Log error tapi jangan throw exception → lanjutkan proses lain.

---

## Bab 10: Pemicu Pengingat Discord (Manual)

**Status jujur:** tidak ada `app/Console/Commands/`, tidak ada scheduler, tidak ada cron.  
Pengingat dikirim **saat admin menekan tombol**.

### Pemicu Web

```php
// routes/web.php

Route::post('/reminder', function (Request $request, DiscordReminder $reminder) use ($admin) {
    $admin($request);
    $result = $reminder->send();

    return back()->with($result['sent'] ? 'status' : 'warning', $result['message']);
})->name('reminder');
```

**Penjelasan per baris:**

```php
$admin($request);
```
Guard → hanya role admin. Anggota biasa → 403.

```php
$result = $reminder->send();
```
Kirim (lihat Bab 9). Return `['sent' => bool, 'message' => string, 'unpaid_count' => int]`.

```php
return back()->with($result['sent'] ? 'status' : 'warning', $result['message']);
```
Kembali ke halaman sebelumnya. Sukses → flash `status`, gagal → flash `warning`. Pesan Discord dikirim, bukan dilempar exception.

### Pemicu Lain: Tautan Discord di Env

Kalau `DISCORD_WEBHOOK_URL` belum diisi → `send()` return `sent: false` + pesan jelas, tanpa crash.

### Kenapa Manual, Bukan Cron?

Cron butuh akses server dan `php artisan schedule:run` tiap menit — setup deployment. Pengingat kas bulanan cukup 1x/bulan → admin tekan tombol, selesai.

**Kalau nanti mau otomatis:**

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

plus console command + `Schedule::command()` — semuanya belum ada, dan tidak dibutuhkan sekarang.

---

## Bab 11: Route & Middleware

### Routes

```php
// routes/api.php

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('admin')->apiResource('users', UserController::class);

    Route::middleware('verified')->group(function () {
        Route::get('/kas-types', [KasTypeController::class, 'index']);
        Route::get('/kas-types/{kasType}', [KasTypeController::class, 'show']);
        Route::get('/kas-types/{kasType}/bills', [KasTypeController::class, 'bills']);
        Route::get('/bills', [BillController::class, 'index']);
        Route::get('/bills/{bill}', [BillController::class, 'show']);
        Route::post('/bills/{bill}/payments', [PaymentController::class, 'storeForBill']);
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);
        Route::delete('/payments/{payment}', [PaymentController::class, 'destroy']);
        Route::post('/payments/{payment}/simulate', [PaymentController::class, 'simulate']);
        Route::get('/ledger-entries', [LedgerEntryController::class, 'index']);
        Route::get('/ledger-entries/{ledgerEntry}', [LedgerEntryController::class, 'show']);
        Route::get('/ledger-summary', [LedgerEntryController::class, 'summary']);

        Route::middleware('admin')->group(function () {
            Route::post('/kas-types', [KasTypeController::class, 'store']);
            Route::put('/kas-types/{kasType}', [KasTypeController::class, 'update']);
            Route::delete('/kas-types/{kasType}', [KasTypeController::class, 'destroy']);
            Route::delete('/bills/{bill}', [BillController::class, 'destroy']);
            Route::post('/bills/{bill}/manual-payments', [PaymentController::class, 'storeManual']);
            Route::post('/ledger-entries', [LedgerEntryController::class, 'store']);
            Route::put('/ledger-entries/{ledgerEntry}', [LedgerEntryController::class, 'update']);
            Route::delete('/ledger-entries/{ledgerEntry}', [LedgerEntryController::class, 'destroy']);
        });
    });
});
```

**Struktur middleware:**

1. `throttle:10,1` → 10 percobaan per menit (login/register)
2. `auth:sanctum` → harus login
3. `admin` → harus role admin
4. `verified` → harus status verified (atau admin)

**Simulasi tanpa conditional route:**

```php
Route::post('/payments/{payment}/simulate', [PaymentController::class, 'simulate']);
```
Route selalu terdaftar. Guard ada di dalam method:

```php
if (app()->environment('production')) { ... 403 ... }
```
Satu guard di satu tempat, bukan duplikasi di route dan controller.

**Pengingat Discord tidak lewat API** → tombol web `POST /admin/reminder` (Bab 10).

### Middleware Alias

```php
// bootstrap/app.php

->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'admin' => \App\Http\Middleware\EnsureAdmin::class,
        'verified' => \App\Http\Middleware\EnsureVerified::class,
    ]);
})
```

---

## Ringkasan Tahap 2

### File yang Dibuat/Dimodifikasi

| Tipe | Path | Fungsi |
|------|------|--------|
| Controller | `app/Http/Controllers/Api/KasTypeController.php` | CRUD paket + bills list |
| Controller | `app/Http/Controllers/Api/BillController.php` | List & detail tagihan |
| Controller | `app/Http/Controllers/Api/PaymentController.php` | QRIS + manual payment |
| Controller | `app/Http/Controllers/Api/LedgerEntryController.php` | Buku kas |
| Service | `app/Services/PaymentService.php` | Business logic pembayaran |
| Service | `app/Services/PaymentGateway.php` | Interface gateway |
| Service | `app/Services/SandboxPaymentGateway.php` | Implementasi sandbox |
| Service | `app/Services/DiscordReminder.php` | Pengingat Discord |
| Resource | `app/Http/Resources/KasTypeResource.php` | Output paket |
| Resource | `app/Http/Resources/BillResource.php` | Output tagihan |
| Resource | `app/Http/Resources/PaymentResource.php` | Output pembayaran |
| Resource | `app/Http/Resources/LedgerEntryResource.php` | Output buku kas |

### API Endpoint Baru

| Metode | Endpoint | Keterangan |
|--------|----------|------------|
| GET | `/api/kas-types` | Daftar paket |
| POST | `/api/kas-types` | Buat paket + terbitkan tagihan |
| GET | `/api/kas-types/{id}` | Detail paket |
| PUT | `/api/kas-types/{id}` | Update paket |
| DELETE | `/api/kas-types/{id}` | Hapus paket |
| GET | `/api/kas-types/{id}/bills` | Status bayar per paket |
| GET | `/api/bills` | Daftar tagihan |
| GET | `/api/bills/{id}` | Detail tagihan |
| DELETE | `/api/bills/{id}` | Batalkan tagihan |
| POST | `/api/bills/{id}/payments` | Buat QRIS payment |
| POST | `/api/bills/{id}/manual-payments` | Catat tunai (admin) |
| GET | `/api/payments` | Daftar pembayaran |
| GET | `/api/payments/{id}` | Detail pembayaran |
| DELETE | `/api/payments/{id}` | Batalkan payment pending |
| POST | `/api/payments/{id}/simulate` | Simulasi lunas (non-production) |
| GET | `/api/ledger-entries` | Buku kas |
| POST | `/api/ledger-entries` | Catat entri manual |
| PUT | `/api/ledger-entries/{id}` | Update entri manual |
| DELETE | `/api/ledger-entries/{id}` | Hapus entri manual |
| GET | `/api/ledger-summary` | Ringkasan saldo |

### Endpoint Web

| Metode | Endpoint | Keterangan |
|--------|----------|------------|
| POST | `/bayar/{bill}` | Buat QRIS payment |
| POST | `/bayar/payment/{id}/simulasi` | Simulasi lunas (non-production) |
| POST | `/admin/ledger` | Catat pengeluaran (admin) |
| POST | `/admin/reminder` | Kirim pengingat Discord (admin) |

### Perintah Artisan

```bash
# Test
php artisan test

# Route
php artisan route:list
```

Scheduler/console command pengingat tidak ada — lihat Bab 10.

### Environment Variables

```env
# .env

# Payment Gateway
PAYMENT_SANDBOX_EXPIRES=30

# Discord Webhook
DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/...

# Timezone
APP_TIMEZONE=Asia/Jakarta
```

---

**Tahap 2 selesai.** Seluruh fitur inti berjalan: paket kas, tagihan otomatis, pembayaran sandbox + tunai, buku kas real-time (termasuk form pengeluaran web), pengingat Discord manual. Webhook gateway sungguhan menyusul saat integrasi payment production.

<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\KasType;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    /**
     * Tandai pembayaran lunas + tagihan lunas + pemasukan kas.
     * Idempoten: dipanggil berulang untuk payment yang sama = tidak dobel.
     * Menolak (422) bila tagihan dibatalkan atau pembayaran kedaluwarsa.
     */
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

    /** Tolak bila tagihan tak bisa dibayar. Dipakai semua jalur bayar. */
    private static function assertPayable(Bill $bill): void
    {
        if ($bill->status === 'paid') {
            abort(409, 'Tagihan sudah lunas.');
        }

        if ($bill->status === 'cancelled') {
            abort(409, 'Tagihan sudah dibatalkan.');
        }

        $bill->loadMissing('kasType');

        if ($bill->kasType && ! $bill->kasType->is_active) {
            abort(409, 'Paket kas sudah nonaktif. Tidak bisa dibayar.');
        }
    }

    /** Catat tunai admin: buat payment + lunaskan. Sama dipakai API + web. */
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

    /** Buat charge QRIS pending, atau kembalikan yang masih berlaku. Return [payment, baru?]. */
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

    /** Terbitkan tagihan paket aktif untuk 1 user. Return jumlah baru. */
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

    /** Terbitkan tagihan 1 paket untuk semua anggota verified. Return jumlah baru. */
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
}

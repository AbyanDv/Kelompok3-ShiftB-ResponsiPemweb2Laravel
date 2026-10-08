<?php

namespace App\Services;

use App\Models\Bill;
use DateTimeImmutable;

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

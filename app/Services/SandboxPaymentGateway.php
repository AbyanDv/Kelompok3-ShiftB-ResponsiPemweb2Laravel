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

    public function signWebhook(array $payload): string
    {
        return hash_hmac('sha256', $this->message($payload), $this->secret());
    }

    public function verifyWebhook(array $payload, string $signature): bool
    {
        return hash_equals($this->signWebhook($payload), $signature);
    }

    private function message(array $payload): string
    {
        return ($payload['order_id'] ?? '').'|'.($payload['status'] ?? '').'|'.($payload['total_amount'] ?? '');
    }

    private function secret(): string
    {
        return (string) config('services.payment.secret', 'smartkas-mock-secret');
    }
}

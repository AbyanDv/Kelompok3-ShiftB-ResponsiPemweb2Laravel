<?php

namespace App\Services;

use App\Models\Bill;

interface PaymentGateway
{
    /** @return array{gateway_ref: string, qr_string: string, expires_at: \DateTimeInterface, fee: int, total_amount: int} */
    public function createCharge(Bill $bill, string $orderId): array;
}

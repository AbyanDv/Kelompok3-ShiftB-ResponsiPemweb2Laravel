<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\ManualPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Bill;
use App\Models\Payment;
use App\Services\PaymentGateway;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    private function gateway(): PaymentGateway
    {
        return app(PaymentGateway::class);
    }

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

        return response()->json([
            'success' => true,
            'message' => 'Daftar pembayaran.',
            'data' => PaymentResource::collection($payments),
        ]);
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        $user = $request->user();
        $payment->load(['bill.user', 'bill.kasType']);

        if (! $user->isAdmin() && $payment->bill->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran ini bukan milikmu.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail pembayaran.',
            'data' => new PaymentResource($payment),
        ]);
    }

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

    public function storeManual(ManualPaymentRequest $request, Bill $bill): JsonResponse
    {
        $payment = PaymentService::recordCash($bill, $request->user(), $request->input('note'));

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran tunai dicatat. Tagihan lunas.',
            'data' => new PaymentResource($payment),
        ], 201);
    }

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

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran dibatalkan.',
            'data' => new PaymentResource($payment->fresh()),
        ]);
    }

    public function simulate(Request $request, Payment $payment): JsonResponse
    {
        if (app()->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => 'Simulasi dimatikan di production.',
            ], 403);
        }

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
                'message' => 'Hanya pembayaran menunggu yang bisa disimulasikan.',
            ], 409);
        }

        if ($payment->bill->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Tagihan sudah dibatalkan.',
            ], 422);
        }

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

    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->only(['order_id', 'status', 'total_amount']);
        $signature = (string) $request->header('X-Signature', '');

        if (! $this->gateway()->verifyWebhook($payload, $signature)) {
            return response()->json([
                'success' => false,
                'message' => 'Signature tidak valid.',
            ], 401);
        }

        $payment = Payment::where('order_id', $payload['order_id'] ?? '')->first();
        if (! $payment) {
            return response()->json([
                'success' => false,
                'message' => 'Order tidak dikenal.',
            ], 404);
        }

        $payment->load('bill');

        if ($payment->bill->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Tagihan sudah dibatalkan.',
            ], 422);
        }

        if ((int) ($payload['total_amount'] ?? 0) !== (int) $payment->total_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Nominal tidak cocok.',
            ], 422);
        }

        $status = $payload['status'] ?? '';
        if ($status === 'paid') {
            PaymentService::confirmPaid($payment, $request->all());
        } elseif (in_array($status, ['failed', 'expired'], true)) {
            if ($payment->status === 'pending') {
                $payment->update(['status' => $status, 'gateway_payload' => $request->all()]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook diterima.',
            'data' => new PaymentResource($payment->fresh()),
        ]);
    }
}

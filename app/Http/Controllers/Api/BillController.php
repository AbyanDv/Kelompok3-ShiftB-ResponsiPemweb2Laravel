<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\EscapesLike;
use App\Http\Controllers\Controller;
use App\Http\Resources\BillResource;
use App\Models\Bill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillController extends Controller
{
    use EscapesLike;

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

        return response()->json([
            'success' => true,
            'message' => 'Daftar tagihan.',
            'data' => BillResource::collection($bills),
            'meta' => [
                'current_page' => $bills->currentPage(),
                'per_page' => $bills->perPage(),
                'total' => $bills->total(),
                'last_page' => $bills->lastPage(),
            ],
        ]);
    }

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

        return response()->json([
            'success' => true,
            'message' => 'Detail tagihan.',
            'data' => new BillResource($bill),
        ]);
    }

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

        return response()->json([
            'success' => true,
            'message' => 'Tagihan dibatalkan.',
            'data' => new BillResource($bill->fresh()),
        ]);
    }
}

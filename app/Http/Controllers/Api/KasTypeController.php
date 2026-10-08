<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\EscapesLike;
use App\Http\Controllers\Controller;
use App\Http\Requests\KasType\StoreKasTypeRequest;
use App\Http\Requests\KasType\UpdateKasTypeRequest;
use App\Http\Resources\BillResource;
use App\Http\Resources\KasTypeResource;
use App\Models\KasType;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KasTypeController extends Controller
{
    use EscapesLike;

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

        return response()->json([
            'success' => true,
            'message' => 'Daftar paket kas.',
            'data' => KasTypeResource::collection($kas),
            'meta' => [
                'current_page' => $kas->currentPage(),
                'per_page' => $kas->perPage(),
                'total' => $kas->total(),
                'last_page' => $kas->lastPage(),
            ],
        ]);
    }

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

    public function show(KasType $kasType): JsonResponse
    {
        $kasType->loadCount('bills')
            ->loadCount(['bills as paid_bills_count' => fn ($q) => $q->where('status', 'paid')])
            ->loadSum(['bills as collected_sum' => fn ($q) => $q->where('status', 'paid')], 'amount');

        return response()->json([
            'success' => true,
            'message' => 'Detail paket kas.',
            'data' => new KasTypeResource($kasType),
        ]);
    }

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

        return response()->json([
            'success' => true,
            'message' => 'Paket kas diperbarui.',
            'data' => new KasTypeResource($kasType->fresh()),
        ]);
    }

    public function destroy(KasType $kasType): JsonResponse
    {
        if ($kasType->bills()->where('status', 'paid')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Paket tidak boleh dihapus karena sudah ada yang membayar. Nonaktifkan saja.',
            ], 409);
        }

        $kasType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Paket kas dihapus.',
            'data' => null,
        ]);
    }

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

        return response()->json([
            'success' => true,
            'message' => 'Status bayar anggota paket '.$kasType->name.'.',
            'data' => BillResource::collection($bills),
            'meta' => [
                'current_page' => $bills->currentPage(),
                'per_page' => $bills->perPage(),
                'total' => $bills->total(),
                'last_page' => $bills->lastPage(),
            ],
        ]);
    }
}

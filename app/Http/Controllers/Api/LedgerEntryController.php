<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\EscapesLike;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\StoreLedgerEntryRequest;
use App\Http\Requests\Ledger\UpdateLedgerEntryRequest;
use App\Http\Resources\LedgerEntryResource;
use App\Models\LedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerEntryController extends Controller
{
    use EscapesLike;

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

        return response()->json([
            'success' => true,
            'message' => 'Buku kas.',
            'data' => LedgerEntryResource::collection($entries),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
                'last_page' => $entries->lastPage(),
            ],
        ]);
    }

    public function show(LedgerEntry $ledgerEntry): JsonResponse
    {
        $ledgerEntry->load('creator');

        return response()->json([
            'success' => true,
            'message' => 'Detail entri buku kas.',
            'data' => new LedgerEntryResource($ledgerEntry),
        ]);
    }

    public function store(StoreLedgerEntryRequest $request): JsonResponse
    {
        $entry = LedgerEntry::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Entri buku kas dicatat.',
            'data' => new LedgerEntryResource($entry),
        ], 201);
    }

    public function update(UpdateLedgerEntryRequest $request, LedgerEntry $ledgerEntry): JsonResponse
    {
        if ($ledgerEntry->payment_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Entri otomatis dari pembayaran tidak boleh diubah.',
            ], 422);
        }

        $ledgerEntry->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Entri buku kas diperbarui.',
            'data' => new LedgerEntryResource($ledgerEntry->fresh()),
        ]);
    }

    public function destroy(LedgerEntry $ledgerEntry): JsonResponse
    {
        if ($ledgerEntry->payment_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Entri otomatis dari pembayaran tidak boleh dihapus.',
            ], 422);
        }

        $ledgerEntry->delete();

        return response()->json([
            'success' => true,
            'message' => 'Entri buku kas dihapus.',
            'data' => null,
        ]);
    }

    public function summary(): JsonResponse
    {
        $income = (int) LedgerEntry::where('type', 'income')->sum('amount');
        $expense = (int) LedgerEntry::where('type', 'expense')->sum('amount');

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan saldo.',
            'data' => [
                'total_income' => $income,
                'total_expense' => $expense,
                'balance' => $income - $expense,
            ],
        ]);
    }
}

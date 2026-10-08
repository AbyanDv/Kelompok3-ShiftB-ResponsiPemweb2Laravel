<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\EscapesLike;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use EscapesLike;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 100));

        $users = User::query()
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('role'), fn ($q, $v) => $q->where('role', $v))
            ->when($request->query('q'), function ($q, $v) {
                $v = $this->escapeLike($v);
                $q->where(
                    fn ($qq) => $qq->where('name', 'like', "%{$v}%")->orWhere('nim', 'like', "%{$v}%")
                );
            })
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar anggota.',
            'data' => UserResource::collection($users),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'nim' => $request->nim,
            'name' => $request->name,
            'email' => $request->nim.'@smartkas.local',
            'password' => $request->password,
            'role' => $request->input('role', 'member'),
            'status' => $request->input('status', 'verified'),
            'discord_id' => $request->discord_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Anggota ditambahkan.',
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): JsonResponse
    {
        $user->loadCount([
            'bills',
            'bills as unpaid_bills_count' => fn ($q) => $q->where('status', 'unpaid'),
            'bills as paid_bills_count' => fn ($q) => $q->where('status', 'paid'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Detail anggota.',
            'data' => [
                'user' => new UserResource($user),
                'bills_summary' => [
                    'total' => $user->bills_count,
                    'unpaid' => $user->unpaid_bills_count,
                    'paid' => $user->paid_bills_count,
                ],
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('role', $data) && $data['role'] !== 'admin' && $user->isAdmin()) {
            if (User::where('role', 'admin')->count() <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin terakhir tidak boleh kehilangan perannya.',
                ], 409);
            }
        }

        if (isset($data['nim'])) {
            $data['email'] = $data['nim'].'@smartkas.local';
        }

        $user->update($data);
        $user = $user->fresh();

        if (($data['status'] ?? null) === 'verified') {
            PaymentService::issueBillsForUser($user);
        }

        return response()->json([
            'success' => true,
            'message' => 'Anggota diperbarui.',
            'data' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus akun sendiri.',
            ], 409);
        }

        if ($user->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Admin terakhir tidak boleh dihapus.',
            ], 409);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Anggota dihapus.',
            'data' => null,
        ]);
    }
}

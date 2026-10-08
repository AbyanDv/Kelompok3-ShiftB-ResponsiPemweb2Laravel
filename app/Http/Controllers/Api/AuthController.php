<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'nim' => $request->nim,
            'name' => $request->name,
            'email' => $request->nim.'@smartkas.local',
            'password' => $request->password,
            'role' => 'member',
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran berhasil. Menunggu verifikasi admin.',
            'data' => new UserResource($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('nim', $request->nim)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'NIM atau password salah.',
            ], 401);
        }

        $abilities = $user->isAdmin() ? ['admin', 'member'] : ['member'];
        $token = $user->createToken('api', $abilities)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
            'data' => null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Profil pengguna.',
            'data' => new UserResource($request->user()),
        ]);
    }
}

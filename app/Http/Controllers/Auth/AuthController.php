<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
class AuthController extends Controller {
    public function login(Request $request): JsonResponse {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) return response()->json(['message' => 'Email atau kata sandi tidak valid.'], 401);
        return response()->json(['user' => $user, 'token' => $user->createToken('auth-token')->plainTextToken]);
    }
    public function register(Request $request): JsonResponse {
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users', 'password' => 'required|string|min:8|confirmed']);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'is_admin' => false]);
        return response()->json(['user' => $user, 'token' => $user->createToken('auth-token')->plainTextToken], 201);
    }
    public function logout(Request $request): JsonResponse {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Berhasil keluar.']);
    }
    public function me(Request $request): JsonResponse { return response()->json($request->user()); }
}

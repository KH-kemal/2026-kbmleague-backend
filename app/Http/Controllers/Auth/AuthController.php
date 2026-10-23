<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
class AuthController extends Controller {
    public function login(Request $request): JsonResponse {
        $data = $request->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:1024']);
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) return response()->json(['message' => 'Email atau kata sandi tidak valid.'], 401);
        return $this->authenticatedResponse($user);
    }
    public function register(Request $request): JsonResponse {
        if (!config('security.public_registration')) {
            return response()->json(['message' => 'Pendaftaran akun publik sedang dinonaktifkan. Hubungi pengelola untuk akun baru.'], 403);
        }
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users', 'password' => 'required|string|min:12|max:72|confirmed', 'is_admin' => 'prohibited', 'role' => 'prohibited', 'admin' => 'prohibited', 'email_verified_at' => 'prohibited']);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        return $this->authenticatedResponse($user, 201);
    }
    private function authenticatedResponse(User $user, int $status = 200): JsonResponse {
        $expiresAt = now()->addMinutes((int) config('sanctum.expiration'));
        $token = $user->createToken('auth-token', ['*'], $expiresAt);
        return response()->json([
            'user' => $user,
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ], $status)->header('Cache-Control', 'private, no-store');
    }

    public function logout(Request $request): JsonResponse {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Berhasil keluar.']);
    }
    public function me(Request $request): JsonResponse { return response()->json($request->user()); }
}

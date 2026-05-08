<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class IntegrationTokenController extends Controller
{
    public function store(Request $request)
    {
        // validasi hanya email + password
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credential salah.'],
            ]);
        }

        // pakai nama default (tanpa device_name dari request)
        $tokenName = 'integration-token';

        // optional: hapus token lama dengan nama yang sama
        $user->tokens()->where('name', $tokenName)->delete();

        // buat token baru
        $token = $user->createToken(
            $tokenName,
            ['customer:read'],
            now()->addMinutes(30)
        );

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => now()->addMinutes(30)->toDateTimeString(),
        ]);
    }
}

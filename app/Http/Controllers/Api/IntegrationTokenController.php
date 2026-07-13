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

        // Check if user has an active integration token in personal_access_tokens and in users table
        $hasToken = $user->tokens()->where('name', $tokenName)->exists();

        if ($hasToken && $user->integration_token) {
            $plainToken = $user->integration_token;
        } else {
            // hapus token lama dengan nama yang sama jika ada ketidaksesuaian/stale
            $user->tokens()->where('name', $tokenName)->delete();

            // buat token baru selamanya (tanpa expiration)
            $token = $user->createToken(
                $tokenName,
                ['customer:read']
            );

            $plainToken = $token->plainTextToken;

            // simpan plain text token ke user
            $user->integration_token = $plainToken;
            $user->save();
        }

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $plainToken,
            'expires_at' => null,
        ]);
    }
}

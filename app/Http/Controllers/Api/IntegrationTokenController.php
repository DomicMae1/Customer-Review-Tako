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

        $tokenNamePrefix = 'integration-token|';

        // Check if there is an existing token for this user with a name starting with 'integration-token|'
        $existingToken = $user->tokens()->where('name', 'like', $tokenNamePrefix . '%')->first();

        if ($existingToken) {
            // Extract the plain text token from the name
            $plainToken = str_replace($tokenNamePrefix, '', $existingToken->name);
        } else {
            // Cleanup any old integration tokens (if any exist under old naming or placeholders)
            $user->tokens()->where('name', 'integration-token')->delete();
            $user->tokens()->where('name', 'like', $tokenNamePrefix . '%')->delete();

            // Try to create a new token without expiration (null).
            // If the database has a NOT NULL constraint on expires_at, we fall back to setting a far-future date (100 years).
            try {
                $token = $user->createToken(
                    'integration-token-temp',
                    ['customer:read']
                );
            } catch (\Throwable $e) {
                $token = $user->createToken(
                    'integration-token-temp',
                    ['customer:read'],
                    now()->addYears(100)
                );
            }

            $plainToken = $token->plainTextToken;

            // Save the plain-text token inside the name column so it can be retrieved on subsequent calls
            $token->accessToken->forceFill([
                'name' => $tokenNamePrefix . $plainToken,
            ])->save();
        }

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $plainToken,
            'expires_at' => null,
        ]);
    }
}

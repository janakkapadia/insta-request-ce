<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    /**
     * List all personal access tokens for the authenticated user.
     *
     * GET /api/v1/tokens
     */
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()
            ->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'abilities' => $t->abilities,
                'last_used' => $t->last_used_at?->toIso8601String(),
                'created_at' => $t->created_at->toIso8601String(),
                'expires_at' => $t->expires_at?->toIso8601String(),
            ]);

        return response()->json($tokens);
    }

    /**
     * Create a new personal access token.
     *
     * POST /api/v1/tokens
     * { "name": "CI Deploy Token" }
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $token = $request->user()->createToken($request->input('name'));

        return response()->json([
            'id' => $token->accessToken->id,
            'name' => $token->accessToken->name,
            // Plain-text token — shown only once on creation
            'token' => $token->plainTextToken,
            'created_at' => $token->accessToken->created_at->toIso8601String(),
        ], 201);
    }

    /**
     * Revoke a personal access token.
     *
     * DELETE /api/v1/tokens/{tokenId}
     */
    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $deleted = $request->user()
            ->tokens()
            ->where('id', $tokenId)
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Token not found.'], 404);
        }

        return response()->json(['message' => 'Token revoked.']);
    }
}

<?php

use App\Http\Controllers\Api\V1\CollectionController;
use App\Http\Controllers\Api\V1\DocumentationController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — v1
|--------------------------------------------------------------------------
|
| All routes here are token-authenticated via Laravel Sanctum.
| Clients must send:  Authorization: Bearer {personal_access_token}
|
| Generate a token via:
|   php artisan api:token {email} --name="CI Token" [--team={slug}]
|
*/

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // ── Auth & identity ───────────────────────────────────────────────────
    Route::get('user', fn (Request $r) => response()->json([
        'id' => $r->user()->id,
        'name' => $r->user()->name,
        'email' => $r->user()->email,
    ]));

    // ── Personal access token management ─────────────────────────────────
    Route::get('tokens', [TokenController::class, 'index']);
    Route::post('tokens', [TokenController::class, 'store']);
    Route::delete('tokens/{tokenId}', [TokenController::class, 'destroy']);

    // ── Collections ───────────────────────────────────────────────────────
    // List accessible collections (resolve collection_id from name in CI)
    Route::get('collections', [CollectionController::class, 'index']);

    // Update documentation visibility / publish settings
    Route::put('collections/{collection}/documentation', [DocumentationController::class, 'update']);

    // ── Imports ───────────────────────────────────────────────────────────
    // One-shot import: parse + confirm in a single request
    // Supports: file upload, raw content, or URL fetch
    Route::post('imports', [ImportController::class, 'store']);
});

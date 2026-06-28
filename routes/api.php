<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

use App\Http\Controllers\Api\SettingsController;

Route::get('/test-auth-header', function (Request $request) {
    return response()->json([
        'auth_header' => $request->header('Authorization'),
        'bearer_token' => $request->bearerToken(),
    ]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/email/resend', [AuthController::class, 'resendVerification']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::patch('/user/profile', [SettingsController::class, 'updateProfile']);
    Route::put('/user/password', [SettingsController::class, 'updatePassword']);

    // Environments API
    Route::get('/environments', [\App\Domains\Environments\Controllers\EnvironmentsController::class, 'apiList']);
    Route::post('/environments', [\App\Domains\Environments\Controllers\EnvironmentsController::class, 'store']);
    Route::put('/environments/{environment}', [\App\Domains\Environments\Controllers\EnvironmentsController::class, 'update']);
    Route::delete('/environments/{environment}', [\App\Domains\Environments\Controllers\EnvironmentsController::class, 'destroy']);

    // Collections API
    Route::get('/collections', [\App\Domains\Collections\Controllers\CollectionsController::class, 'apiList']);
    Route::get('/collections/{collection}/details', [\App\Domains\Collections\Controllers\CollectionsController::class, 'details']);
    Route::post('/collections', [\App\Domains\Collections\Controllers\CollectionsController::class, 'store']);
    Route::patch('/collections/{collection}', [\App\Domains\Collections\Controllers\CollectionsController::class, 'updateCollection']);
    Route::delete('/collections/{collection}', [\App\Domains\Collections\Controllers\CollectionsController::class, 'destroyCollection']);

    // Folders API
    Route::post('/collections/{collection}/folders', [\App\Domains\Collections\Controllers\CollectionsController::class, 'storeFolder']);
    Route::patch('/folders/{folder}', [\App\Domains\Collections\Controllers\CollectionsController::class, 'updateFolder']);
    Route::delete('/folders/{folder}', [\App\Domains\Collections\Controllers\CollectionsController::class, 'destroyFolder']);

    // Requests API
    Route::post('/collections/{collection}/requests', [\App\Domains\Collections\Controllers\CollectionsController::class, 'storeRequest']);
    Route::patch('/requests/{apiRequest}', [\App\Domains\Collections\Controllers\CollectionsController::class, 'updateRequest']);
    Route::delete('/requests/{apiRequest}', [\App\Domains\Collections\Controllers\CollectionsController::class, 'destroyRequest']);
    Route::delete('/requests-batch', [\App\Domains\Collections\Controllers\CollectionsController::class, 'destroyRequestsBatch']);
    Route::post('/requests/{apiRequest}/clone', [\App\Domains\Collections\Controllers\CollectionsController::class, 'cloneRequest']);



    // Request Execution API
    Route::post('/requests/execute', [\App\Domains\Requests\Controllers\RequestExecutionController::class, 'execute']);
    Route::post('/requests/resolve', [\App\Domains\Requests\Controllers\RequestExecutionController::class, 'resolve']);
    Route::post('/requests/save-history', [\App\Domains\Requests\Controllers\RequestExecutionController::class, 'saveHistory']);

    Route::get('/history', [\App\Domains\History\Controllers\HistoryController::class, 'apiIndex']);


    // Import API
    Route::post('/import/upload', [\App\Domains\ImportExport\Controllers\ImportController::class, 'upload']);
    Route::post('/import/{import}/confirm', [\App\Domains\ImportExport\Controllers\ImportController::class, 'confirm']);
});



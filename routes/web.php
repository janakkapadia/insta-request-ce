<?php

use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

// Redirect front pages to login
Route::redirect('postman-alternative', '/login')->name('postman-alternative');
Route::redirect('api-monitoring', '/login')->name('api-monitoring');
Route::redirect('api-collaboration', '/login')->name('api-collaboration');

Route::get('email/verify/{id}/{hash}', [\App\Http\Controllers\Api\AuthController::class, 'verifyEmail'])
    ->middleware(['signed'])
    ->name('verification.verify');

Route::redirect('request-builder', '/login')->name('request-builder');
Route::redirect('realtime-api-workspace', '/login')->name('realtime-api-workspace');

// Legal Pages
Route::inertia('terms-of-service', 'TermsOfService')->name('terms');
Route::inertia('privacy-policy', 'PrivacyPolicy')->name('privacy');

Route::middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
        Route::get('api/search', [\App\Domains\Search\Controllers\SearchController::class, 'index'])->name('api.search');
        Route::post('requests/execute', [\App\Domains\Requests\Controllers\RequestExecutionController::class, 'execute'])->name('requests.execute');
        Route::post('requests/resolve', [\App\Domains\Requests\Controllers\RequestExecutionController::class, 'resolve'])->name('requests.resolve');
        Route::post('requests/save-history', [\App\Domains\Requests\Controllers\RequestExecutionController::class, 'saveHistory'])->name('requests.save-history');
        Route::get('requests/history', [\App\Domains\History\Controllers\HistoryController::class, 'apiIndex'])->name('requests.history');


        // Collections & Requests
        Route::get('collections/{collection}/requests/{apiRequest}', [\App\Domains\Collections\Controllers\CollectionsController::class, 'index'])->name('collections.requests.show');
        Route::get('collections/{collection}/details', [\App\Domains\Collections\Controllers\CollectionsController::class, 'details'])->name('collections.details');
        Route::get('collections/{collection}', [\App\Domains\Collections\Controllers\CollectionsController::class, 'index'])->name('collections.show');
        Route::get('collections', [\App\Domains\Collections\Controllers\CollectionsController::class, 'index'])->name('collections.index');
        Route::post('collections', [\App\Domains\Collections\Controllers\CollectionsController::class, 'store'])->name('collections.store');
        Route::post('collections/{collection}/folders', [\App\Domains\Collections\Controllers\CollectionsController::class, 'storeFolder'])->name('collections.folders.store');
        Route::post('collections/{collection}/requests', [\App\Domains\Collections\Controllers\CollectionsController::class, 'storeRequest'])->name('collections.requests.store');
        Route::patch('requests/{apiRequest}', [\App\Domains\Collections\Controllers\CollectionsController::class, 'updateRequest'])->name('requests.update');
        Route::patch('collections/{collection}', [\App\Domains\Collections\Controllers\CollectionsController::class, 'updateCollection'])->name('collections.update');
        Route::delete('collections/{collection}', [\App\Domains\Collections\Controllers\CollectionsController::class, 'destroyCollection'])->name('collections.destroy')->middleware('team_permission:collection:delete');
        Route::patch('folders/{folder}', [\App\Domains\Collections\Controllers\CollectionsController::class, 'updateFolder'])->name('folders.update');
        Route::delete('folders/{folder}', [\App\Domains\Collections\Controllers\CollectionsController::class, 'destroyFolder'])->name('folders.destroy')->middleware('team_permission:folder:delete');
        Route::delete('requests-batch', [\App\Domains\Collections\Controllers\CollectionsController::class, 'destroyRequestsBatch'])->name('requests.destroy-batch')->middleware('team_permission:request:batch_delete');
        Route::delete('requests/{apiRequest}', [\App\Domains\Collections\Controllers\CollectionsController::class, 'destroyRequest'])->name('requests.destroy')->middleware('team_permission:request:delete');
        Route::post('requests/{apiRequest}/clone', [\App\Domains\Collections\Controllers\CollectionsController::class, 'cloneRequest'])->name('requests.clone');

        Route::get('environments', [\App\Domains\Environments\Controllers\EnvironmentsController::class, 'index'])->name('environments.index');
        Route::get('api/environments', [\App\Domains\Environments\Controllers\EnvironmentsController::class, 'apiList'])->name('api.environments.list');
        Route::post('environments', [\App\Domains\Environments\Controllers\EnvironmentsController::class, 'store'])->name('environments.store');
        Route::post('api/environments', [\App\Domains\Environments\Controllers\EnvironmentsController::class, 'store'])->name('api.environments.store');
        Route::put('environments/{environment}', [\App\Domains\Environments\Controllers\EnvironmentsController::class, 'update'])->name('environments.update');
        Route::put('api/environments/{environment}', [\App\Domains\Environments\Controllers\EnvironmentsController::class, 'update'])->name('api.environments.update');
        Route::delete('environments/{environment}', [\App\Domains\Environments\Controllers\EnvironmentsController::class, 'destroy'])->name('environments.destroy')->middleware('team_permission:environment:delete');
        Route::delete('api/environments/{environment}', [\App\Domains\Environments\Controllers\EnvironmentsController::class, 'destroy'])->name('api.environments.destroy')->middleware('team_permission:environment:delete');
        Route::post('environments/{environment}/replace-value', [\App\Domains\Environments\Controllers\EnvironmentsController::class, 'replaceValue'])->name('environments.replaceValue')->middleware('team_permission:environment:manage_variables');

        // History logs
        Route::get('history', [\App\Domains\History\Controllers\HistoryController::class, 'index'])->name('history.index');
        Route::delete('history', [\App\Domains\History\Controllers\HistoryController::class, 'destroy'])->name('history.destroy');

        // Import / Export
        Route::post('import/upload', [\App\Domains\ImportExport\Controllers\ImportController::class, 'upload'])->name('import.upload');
        Route::get('import/{import}/preview', [\App\Domains\ImportExport\Controllers\ImportController::class, 'preview'])->name('import.preview');
        Route::post('import/{import}/confirm', [\App\Domains\ImportExport\Controllers\ImportController::class, 'confirm'])->name('import.confirm');
        Route::post('export', [\App\Domains\ImportExport\Controllers\ExportController::class, 'export'])->name('export.create');
        Route::get('export/{export}/download', [\App\Domains\ImportExport\Controllers\ExportController::class, 'download'])->name('export.download');

        // Documentation Dashboard & APIs (Internal)
        Route::get('documentation', [\App\Domains\Documentation\Controllers\DocumentationController::class, 'index'])->name('documentation.index');
        Route::get('api/documentation/collection/{collection}', [\App\Domains\Documentation\Controllers\DocumentationController::class, 'getDoc'])->name('api.documentation.show');
        Route::post('api/documentation/collection/{collection}', [\App\Domains\Documentation\Controllers\DocumentationController::class, 'saveDoc'])->name('api.documentation.save');
        Route::post('api/documentation/request/{apiRequest}/response-examples', [\App\Domains\Documentation\Controllers\DocumentationController::class, 'storeExample'])->name('api.documentation.example.store');
        Route::delete('api/documentation/response-examples/{example}', [\App\Domains\Documentation\Controllers\DocumentationController::class, 'destroyExample'])->name('api.documentation.example.destroy');

        Route::get('members', [\App\Domains\Teams\Controllers\MembersController::class, 'index'])->name('teams.members');
        Route::put('teams/{team}/permissions', [\App\Domains\Teams\Controllers\TeamPermissionsController::class, 'update'])->name('teams.permissions.update');
    });

// Public Documentation Portal
Route::get('docs/{slug}', [\App\Domains\Documentation\Controllers\DocumentationController::class, 'viewPublic'])->name('documentation.public');

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
});

require __DIR__ . '/settings.php';

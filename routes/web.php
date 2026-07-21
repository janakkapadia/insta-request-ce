<?php

use App\Domains\Collections\Controllers\CollectionsController;
use App\Domains\Documentation\Controllers\DocumentationController;
use App\Domains\Documentation\Models\CollectionDocumentation;
use App\Domains\Environments\Controllers\EnvironmentsController;
use App\Domains\History\Controllers\HistoryController;
use App\Domains\ImportExport\Controllers\ExportController;
use App\Domains\ImportExport\Controllers\ImportController;
use App\Domains\Requests\Controllers\RequestExecutionController;
use App\Domains\Search\Controllers\SearchController;
use App\Domains\Teams\Controllers\MembersController;
use App\Domains\Teams\Controllers\TeamPermissionsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    if (CollectionDocumentation::where('is_public', true)->exists()) {
        return redirect()->route('documentation.public.index');
    }

    return redirect()->route('login');
})->name('home');

// Redirect front pages to login
Route::redirect('postman-alternative', '/login')->name('postman-alternative');
Route::redirect('api-monitoring', '/login')->name('api-monitoring');
Route::redirect('api-collaboration', '/login')->name('api-collaboration');

Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed'])
    ->name('verification.verify');

Route::redirect('request-builder', '/login')->name('request-builder');
Route::redirect('realtime-api-workspace', '/login')->name('realtime-api-workspace');

// Legal Pages
Route::inertia('terms-of-service', 'TermsOfService')->name('terms');
Route::inertia('privacy-policy', 'PrivacyPolicy')->name('privacy');

Route::middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('api/search', [SearchController::class, 'index'])->name('api.search');
        Route::post('requests/execute', [RequestExecutionController::class, 'execute'])->name('requests.execute');
        Route::post('requests/resolve', [RequestExecutionController::class, 'resolve'])->name('requests.resolve');
        Route::post('requests/save-history', [RequestExecutionController::class, 'saveHistory'])->name('requests.save-history');
        Route::get('requests/history', [HistoryController::class, 'apiIndex'])->name('requests.history');

        // Collections & Requests
        Route::get('collections/{collection}/requests/{apiRequest}', [CollectionsController::class, 'index'])->name('collections.requests.show');
        Route::get('collections/{collection}/details', [CollectionsController::class, 'details'])->name('collections.details');
        Route::get('collections/{collection}', [CollectionsController::class, 'index'])->name('collections.show');
        Route::get('collections', [CollectionsController::class, 'index'])->name('collections.index');
        Route::post('collections', [CollectionsController::class, 'store'])->name('collections.store');
        Route::post('collections/{collection}/folders', [CollectionsController::class, 'storeFolder'])->name('collections.folders.store');
        Route::post('collections/{collection}/requests', [CollectionsController::class, 'storeRequest'])->name('collections.requests.store');
        Route::patch('requests/{apiRequest}', [CollectionsController::class, 'updateRequest'])->name('requests.update');
        Route::patch('collections/{collection}', [CollectionsController::class, 'updateCollection'])->name('collections.update');
        Route::delete('collections/{collection}', [CollectionsController::class, 'destroyCollection'])->name('collections.destroy')->middleware('team_permission:collection:delete');
        Route::patch('folders/{folder}', [CollectionsController::class, 'updateFolder'])->name('folders.update');
        Route::delete('folders/{folder}', [CollectionsController::class, 'destroyFolder'])->name('folders.destroy')->middleware('team_permission:folder:delete');
        Route::delete('requests-batch', [CollectionsController::class, 'destroyRequestsBatch'])->name('requests.destroy-batch')->middleware('team_permission:request:batch_delete');
        Route::delete('requests/{apiRequest}', [CollectionsController::class, 'destroyRequest'])->name('requests.destroy')->middleware('team_permission:request:delete');
        Route::post('requests/{apiRequest}/clone', [CollectionsController::class, 'cloneRequest'])->name('requests.clone');

        Route::get('environments', [EnvironmentsController::class, 'index'])->name('environments.index');
        Route::get('api/environments', [EnvironmentsController::class, 'apiList'])->name('api.environments.list');
        Route::post('environments', [EnvironmentsController::class, 'store'])->name('environments.store');
        Route::post('api/environments', [EnvironmentsController::class, 'store'])->name('api.environments.store');
        Route::put('environments/{environment}', [EnvironmentsController::class, 'update'])->name('environments.update');
        Route::put('api/environments/{environment}', [EnvironmentsController::class, 'update'])->name('api.environments.update');
        Route::delete('environments/{environment}', [EnvironmentsController::class, 'destroy'])->name('environments.destroy')->middleware('team_permission:environment:delete');
        Route::delete('api/environments/{environment}', [EnvironmentsController::class, 'destroy'])->name('api.environments.destroy')->middleware('team_permission:environment:delete');
        Route::post('environments/{environment}/replace-value', [EnvironmentsController::class, 'replaceValue'])->name('environments.replaceValue')->middleware('team_permission:environment:manage_variables');
        Route::get('environments/{environment}/export', [EnvironmentsController::class, 'export'])->name('environments.export');
        Route::post('environments/import', [EnvironmentsController::class, 'import'])->name('environments.import');
        Route::get('environments/{environment}', [EnvironmentsController::class, 'show'])->name('environments.show');

        // History logs
        Route::get('history', [HistoryController::class, 'index'])->name('history.index');
        Route::delete('history', [HistoryController::class, 'destroy'])->name('history.destroy');

        // Import / Export
        Route::post('import/upload', [ImportController::class, 'upload'])->name('import.upload');
        Route::get('import/{import}/preview', [ImportController::class, 'preview'])->name('import.preview');
        Route::post('import/{import}/confirm', [ImportController::class, 'confirm'])->name('import.confirm');
        Route::post('export', [ExportController::class, 'export'])->name('export.create');
        Route::get('export/{export}/download', [ExportController::class, 'download'])->name('export.download');

        // Documentation Dashboard & APIs (Internal)
        Route::get('documentation', [DocumentationController::class, 'index'])->name('documentation.index');
        Route::post('documentation/collection/{collection}', [DocumentationController::class, 'saveDoc'])->name('documentation.save');
        Route::post('documentation/request/{apiRequest}/response-examples', [DocumentationController::class, 'storeExample'])->name('documentation.example.store');
        Route::delete('documentation/response-examples/{example}', [DocumentationController::class, 'destroyExample'])->name('documentation.example.destroy');

        Route::get('members', [MembersController::class, 'index'])->name('teams.members');
        Route::put('teams/{team}/permissions', [TeamPermissionsController::class, 'update'])->name('teams.permissions.update');
    });

// Public Documentation Portal
Route::get('docs', [DocumentationController::class, 'publicIndex'])->name('documentation.public.index');
Route::get('docs/{collection}/{slug}', [DocumentationController::class, 'viewPublic'])->name('documentation.public');

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
});

require __DIR__.'/settings.php';

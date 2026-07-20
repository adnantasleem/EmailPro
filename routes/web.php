<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SmtpConfigController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\RecipientController;
use App\Http\Controllers\ContactListController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\SubjectLineController;
use App\Http\Controllers\BodyTemplateController;
use App\Http\Controllers\PublicUnsubscribeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Public unsubscribe routes (no auth required)
Route::get('/unsubscribe/{token}', [PublicUnsubscribeController::class, 'show'])->name('public.unsubscribe');
Route::post('/unsubscribe/{token}', [PublicUnsubscribeController::class, 'process'])->name('public.unsubscribe.process');

// Email tracking pixel (no auth required)
Route::get('/track/open/{token}', [\App\Http\Controllers\TrackingController::class, 'trackOpen'])->name('track.open');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // SMTP Configuration
    Route::resource('smtp', SmtpConfigController::class)->parameters(['smtp' => 'smtp']);
    Route::post('/smtp/{smtp}/toggle', [SmtpConfigController::class, 'toggle'])->name('smtp.toggle');
    Route::post('/smtp/{smtp}/reset-counter', [SmtpConfigController::class, 'resetCounter'])->name('smtp.reset-counter');
    Route::post('/smtp/{smtp}/start-warmup', [SmtpConfigController::class, 'startWarmup'])->name('smtp.start-warmup');
    Route::post('/smtp/{smtp}/end-warmup', [SmtpConfigController::class, 'endWarmup'])->name('smtp.end-warmup');
    Route::post('/smtp/{smtp}/resume', [SmtpConfigController::class, 'resume'])->name('smtp.resume');
    Route::post('/smtp/test-connection', [SmtpConfigController::class, 'testConnection'])->name('smtp.test-connection');
    Route::post('/smtp/{smtp}/test', [SmtpConfigController::class, 'test'])->name('smtp.test');

    // Contact Lists
    Route::resource('contact-lists', ContactListController::class);
    Route::post('/contact-lists/{contact_list}/add-contacts', [ContactListController::class, 'addContacts'])->name('contact-lists.add-contacts');
    
    // Import staging routes
    Route::get('/contact-lists/{contact_list}/review-import/{import_id}', [ContactListController::class, 'reviewImport'])->name('contact-lists.review-import');
    Route::post('/contact-lists/{contact_list}/commit-import/{import_id}', [ContactListController::class, 'commitImport'])->name('contact-lists.commit-import');
    Route::get('/contact-lists/{contact_list}/download-report/{import_id}', [ContactListController::class, 'downloadImportReport'])->name('contact-lists.download-report');
    
    Route::delete('/contact-lists/{contact_list}/contacts/{contact}', [ContactListController::class, 'deleteContact'])->name('contact-lists.delete-contact');
    Route::delete('/contact-lists/{contact_list}/delete-invalid', [ContactListController::class, 'deleteInvalidContacts'])->name('contact-lists.delete-invalid');
    Route::get('/contact-lists/{contact_list}/export', [ContactListController::class, 'export'])->name('contact-lists.export');
    Route::get('/contact-lists/download-skipped', [ContactListController::class, 'downloadSkippedEmails'])->name('contact-lists.download-skipped');

    // Subject Lines
    Route::resource('subjects', SubjectLineController::class)->parameters(['subjects' => 'subject']);

    // Subject Groups
    Route::resource('subject-groups', \App\Http\Controllers\SubjectGroupController::class)
        ->except(['create', 'edit'])
        ->parameters(['subject-groups' => 'subjectGroup']);
    Route::post('/subject-groups/{subjectGroup}/add-subjects', [\App\Http\Controllers\SubjectGroupController::class, 'addSubjects'])->name('subject-groups.add-subjects');
    Route::delete('/subject-groups/{subjectGroup}/subjects/{subject}/remove', [\App\Http\Controllers\SubjectGroupController::class, 'removeSubject'])->name('subject-groups.remove-subject');
    Route::delete('/subject-groups/{subjectGroup}/subjects/{subject}/delete', [\App\Http\Controllers\SubjectGroupController::class, 'deleteSubject'])->name('subject-groups.delete-subject');

    // Body Templates
    Route::resource('bodies', BodyTemplateController::class)->parameters(['bodies' => 'body']);

    // Body Groups
    Route::resource('body-groups', \App\Http\Controllers\BodyGroupController::class)
        ->except(['create', 'edit'])
        ->parameters(['body-groups' => 'bodyGroup']);
    Route::post('/body-groups/{bodyGroup}/add-bodies', [\App\Http\Controllers\BodyGroupController::class, 'addBodies'])->name('body-groups.add-bodies');
    Route::delete('/body-groups/{bodyGroup}/bodies/{body}/remove', [\App\Http\Controllers\BodyGroupController::class, 'removeBody'])->name('body-groups.remove-body');
    Route::delete('/body-groups/{bodyGroup}/bodies/{body}/delete', [\App\Http\Controllers\BodyGroupController::class, 'deleteBody'])->name('body-groups.delete-body');

    // Unsubscribes
    Route::get('/unsubscribes', [UnsubscribeController::class, 'index'])->name('unsubscribes.index');
    Route::get('/unsubscribes/export', [UnsubscribeController::class, 'export'])->name('unsubscribes.export');
    Route::post('/unsubscribes', [UnsubscribeController::class, 'store'])->name('unsubscribes.store');
    Route::post('/unsubscribes/import', [UnsubscribeController::class, 'import'])->name('unsubscribes.import');
    Route::delete('/unsubscribes/{unsubscribe}', [UnsubscribeController::class, 'destroy'])->name('unsubscribes.destroy');

    // Disposable Domains (Blocked)
    Route::get('/blocked', [\App\Http\Controllers\DisposableDomainController::class, 'index'])->name('disposable-domains.index');
    Route::post('/blocked', [\App\Http\Controllers\DisposableDomainController::class, 'store'])->name('disposable-domains.store');
    Route::post('/blocked/import', [\App\Http\Controllers\DisposableDomainController::class, 'import'])->name('disposable-domains.import');
    Route::delete('/blocked/{disposableDomain}', [\App\Http\Controllers\DisposableDomainController::class, 'destroy'])->name('disposable-domains.destroy');
    Route::delete('/blocked', [\App\Http\Controllers\DisposableDomainController::class, 'destroyAll'])->name('disposable-domains.destroy-all');

    // Campaigns
    Route::resource('campaigns', CampaignController::class);
    Route::post('/campaigns/{campaign}/start', [CampaignController::class, 'start'])->name('campaigns.start');
    Route::post('/campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');
    Route::post('/campaigns/{campaign}/resume', [CampaignController::class, 'resume'])->name('campaigns.resume');
    Route::post('/campaigns/{campaign}/stop', [CampaignController::class, 'stop'])->name('campaigns.stop');
    Route::get('/campaigns/{campaign}/export', [CampaignController::class, 'export'])->name('campaigns.export');
    Route::get('/campaigns/{campaign}/report', [CampaignController::class, 'report'])->name('campaigns.report');
    Route::post('/campaigns/{campaign}/restart', [CampaignController::class, 'restart'])->name('campaigns.restart');
    Route::post('/campaigns/{campaign}/retry-failed', [CampaignController::class, 'retryFailed'])->name('campaigns.retry-failed');
    Route::post('/campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate'])->name('campaigns.duplicate');
    Route::post('/campaigns/{campaign}/test-email', [CampaignController::class, 'sendTestEmail'])->name('campaigns.test-email');

    // Recipients
    Route::get('/campaigns/{campaign}/recipients', [RecipientController::class, 'index'])->name('recipients.index');
    Route::post('/campaigns/{campaign}/recipients/import', [RecipientController::class, 'import'])->name('recipients.import');
    Route::post('/campaigns/{campaign}/recipients/bulk-import', [RecipientController::class, 'bulkImport'])->name('recipients.bulk-import');
    Route::delete('/campaigns/{campaign}/recipients/{recipient}', [RecipientController::class, 'destroy'])->name('recipients.destroy');
    Route::post('/campaigns/{campaign}/recipients/bulk-delete', [RecipientController::class, 'bulkDelete'])->name('recipients.bulk-delete');
    Route::get('/campaigns/{campaign}/recipients/export', [RecipientController::class, 'export'])->name('recipients.export');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Leave Impersonation
    Route::post('/impersonate/leave', [\App\Http\Controllers\UserController::class, 'leaveImpersonation'])->name('impersonate.leave');

    // Admin: User Management
    Route::middleware([\App\Http\Middleware\AdminMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [\App\Http\Controllers\UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [\App\Http\Controllers\UserController::class, 'create'])->name('users.create');
        Route::post('/users', [\App\Http\Controllers\UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [\App\Http\Controllers\UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [\App\Http\Controllers\UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/impersonate', [\App\Http\Controllers\UserController::class, 'impersonate'])->name('users.impersonate');
    });
});

require __DIR__.'/auth.php';

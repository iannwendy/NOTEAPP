<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\UserPreferenceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\NoteShareController;
use App\Http\Controllers\NotificationController;

// Welcome page - redirect to login or notes based on auth status
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('notes.index');
    }
    return redirect()->route('login');
});

// Authentication routes
Auth::routes(['verify' => true]);

// Home route - redirect to notes
Route::get('/home', function() {
    return redirect()->route('notes.index');
})->name('home')->middleware(['auth']);

// Note routes - all require auth
Route::middleware(['auth'])->group(function () {
    // Notes
    Route::resource('notes', NoteController::class);
    Route::patch('/notes/{note}/toggle-pin', [NoteController::class, 'togglePin'])->name('notes.toggle-pin');
    Route::post('/notes/{note}/real-time-update', [NoteController::class, 'realTimeUpdate'])->name('notes.real-time-update');
    Route::post('/notes/{note}/leave-edit-session', [NoteController::class, 'leaveEditSession'])->name('notes.leave-edit-session');
    Route::post('/notes/{note}/heartbeat', [NoteController::class, 'heartbeat'])->name('notes.heartbeat');
    Route::get('/notes/{note}/json', [NoteController::class, 'showJson'])->name('notes.show-json');
    
    // Labels
    Route::resource('labels', LabelController::class)->except(['show']);
    Route::post('/labels/add-to-note', [LabelController::class, 'addToNote'])->name('labels.add-to-note');
    Route::post('/labels/remove-from-note', [LabelController::class, 'removeFromNote'])->name('labels.remove-from-note');
    Route::get('/labels/get-all', [LabelController::class, 'getAllLabels'])->name('labels.get-all');
    
    // Attachments
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::resource('notes.attachments', AttachmentController::class)->except(['index', 'show', 'edit', 'update']);
    
    // User preferences
    Route::get('/preferences', [UserPreferenceController::class, 'edit'])->name('preferences.edit');
    Route::put('/preferences', [UserPreferenceController::class, 'update'])->name('preferences.update');

    // Note sharing
    Route::get('shares', [NoteShareController::class, 'index'])->name('notes.shares.index');
    Route::get('notes/{note}/shares', [NoteShareController::class, 'show'])->name('notes.shares.show');
    Route::get('notes/{note}/shares/create', [NoteShareController::class, 'create'])->name('notes.shares.create');
    Route::post('notes/{note}/shares', [NoteShareController::class, 'store'])->name('notes.shares.store');
    Route::patch('notes/{note}/shares/{share}', [NoteShareController::class, 'update'])->name('notes.shares.update');
    Route::delete('notes/{note}/shares/{share}', [NoteShareController::class, 'destroy'])->name('notes.shares.destroy');
    
    // Notifications
    Route::post('notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');
    Route::post('notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllAsRead');
});

// User Settings Routes
Route::prefix('user')->name('user.')->middleware('auth')->group(function () {
    Route::get('/profile', [UserSettingsController::class, 'profile'])->name('profile');
    Route::get('/profile/edit', [UserSettingsController::class, 'editProfile'])->name('edit-profile');
    Route::patch('/profile', [UserSettingsController::class, 'updateProfile'])->name('update-profile');
    Route::get('/avatar/edit', [UserSettingsController::class, 'editAvatar'])->name('edit-avatar');
    Route::patch('/avatar', [UserSettingsController::class, 'updateAvatar'])->name('update-avatar');
    Route::get('/password/edit', [UserSettingsController::class, 'editPassword'])->name('edit-password');
    Route::patch('/password', [UserSettingsController::class, 'updatePassword'])->name('update-password');
});

// Password protection for notes
Route::middleware(['auth'])->group(function () {
    Route::post('notes/{note}/verify-password', [App\Http\Controllers\NoteController::class, 'verifyPassword'])->name('notes.verify-password');
    Route::get('notes/{note}/password-protection', [App\Http\Controllers\NoteController::class, 'showPasswordProtection'])->name('notes.password-protection');
    Route::post('notes/{note}/password-protection', [App\Http\Controllers\NoteController::class, 'updatePasswordProtection'])->name('notes.update-password-protection');
});

// Offline fallback page
Route::get('/offline', function () {
    return view('offline');
});

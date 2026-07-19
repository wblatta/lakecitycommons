<?php

use App\Http\Controllers\Admin\MemberController as AdminMemberController;
use App\Http\Controllers\Admin\PostController as AdminPostController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExchangeRequestController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\WaitlistController;
use Illuminate\Support\Facades\Route;

// Public site
Route::get('/', HomeController::class)->name('home');
Route::view('/news', 'home')->name('news.index');           // replaced in Task 10
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events.ics', fn () => abort(404))->name('events.ics'); // replaced in Task 7
Route::get('/directory', [DirectoryController::class, 'index'])->name('directory.index');
Route::view('/submit', 'home')->name('submissions.create'); // replaced in Task 8

// Referral registration
Route::middleware(['feature:community', 'referral'])->group(function () {
    Route::get('/register/{token}', [\App\Http\Controllers\Auth\RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register/{token}', [\App\Http\Controllers\Auth\RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,1');
});

// Authenticated + active user routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('feature:community')->group(function () {
        // Member profiles
        Route::get('/users/{user}', [UserProfileController::class, 'show'])->name('users.show');

        // Skills
        Route::resource('skills', SkillController::class);
        Route::patch('/skills/{skill}/toggle', [SkillController::class, 'toggle'])->name('skills.toggle');

        // Items
        Route::resource('items', ItemController::class);
        Route::patch('/items/{item}/toggle', [ItemController::class, 'toggle'])->name('items.toggle');

        // Referrals (invite)
        Route::get('/invite', [ReferralController::class, 'index'])->name('invite.index');
        Route::post('/invite', [ReferralController::class, 'store'])->name('invite.store');

        // Exchange Requests
        Route::resource('requests', ExchangeRequestController::class)->except(['index']);
        Route::post('/requests/{request}/confirm', [ExchangeRequestController::class, 'confirm'])->name('requests.confirm');
        Route::post('/requests/{request}/transition', [ExchangeRequestController::class, 'transition'])->name('requests.transition');
        Route::get('/my-requests', [ExchangeRequestController::class, 'index'])->name('requests.index');

        // Messages
        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/{thread}', [MessageController::class, 'show'])->name('messages.show');
        Route::post('/messages/{thread}', [MessageController::class, 'store'])->name('messages.store');
        Route::get('/messages/{thread}/poll', [MessageController::class, 'poll'])
            ->middleware('throttle:message-poll')
            ->name('messages.poll');
        Route::get('/messages/unread-count', [MessageController::class, 'unreadCount'])->name('messages.unread');

        // Waitlist
        Route::post('/waitlist', [WaitlistController::class, 'store'])->name('waitlist.store');
        Route::delete('/waitlist/{entry}', [WaitlistController::class, 'destroy'])->name('waitlist.destroy');
    });
});

// Admin
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/members', [AdminMemberController::class, 'index'])->name('members.index');
    Route::patch('/members/{user}/status', [AdminMemberController::class, 'updateStatus'])->name('members.status');
    Route::post('/members/{user}/adjust-credits', [AdminMemberController::class, 'adjustCredits'])->name('members.credits');

    Route::resource('posts', AdminPostController::class)->except(['show']);
    Route::resource('organizations', \App\Http\Controllers\Admin\OrganizationController::class)->except(['show']);
    Route::get('/audit-log', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit-log.index');
});

require __DIR__.'/auth.php';

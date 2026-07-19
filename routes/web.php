<?php

use App\Http\Controllers\Admin\MemberController as AdminMemberController;
use App\Http\Controllers\Admin\PostController as AdminPostController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SourceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExchangeRequestController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\WaitlistController;
use Illuminate\Support\Facades\Route;

// Public site
Route::get('/', HomeController::class)->name('home');
Route::get('/news', [\App\Http\Controllers\NewsController::class, 'index'])->name('news.index');
Route::get('/news/{post:slug}', [\App\Http\Controllers\NewsController::class, 'show'])->name('news.show');
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events.ics', [EventController::class, 'ics'])->name('events.ics');
Route::get('/directory', [DirectoryController::class, 'index'])->name('directory.index');
Route::get('/submit', [SubmissionController::class, 'create'])->name('submissions.create');
Route::post('/submit', [SubmissionController::class, 'store'])->name('submissions.store');
Route::get('/feed', [FeedController::class, 'rss'])->name('feed');
Route::get('/sitemap.xml', [FeedController::class, 'sitemap'])->name('sitemap');

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
    Route::post('/posts/{post}/publish', [AdminPostController::class, 'publish'])->name('posts.publish');
    Route::get('/posts/{post}/email', [AdminPostController::class, 'email'])->name('posts.email');
    Route::resource('organizations', \App\Http\Controllers\Admin\OrganizationController::class)->except(['show']);
    Route::resource('sources', SourceController::class)->except(['show']);
    Route::get('/audit-log', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit-log.index');

    Route::get('/review', [\App\Http\Controllers\Admin\ReviewController::class, 'index'])->name('review.index');
    Route::post('/review/submissions/{submission}/approve', [\App\Http\Controllers\Admin\ReviewController::class, 'approveSubmission'])->name('review.submissions.approve');
    Route::post('/review/submissions/{submission}/reject', [\App\Http\Controllers\Admin\ReviewController::class, 'rejectSubmission'])->name('review.submissions.reject');
    Route::post('/review/events/{event}/approve', [\App\Http\Controllers\Admin\ReviewController::class, 'approveEvent'])->name('review.events.approve');
    Route::post('/review/events/{event}/reject', [\App\Http\Controllers\Admin\ReviewController::class, 'rejectEvent'])->name('review.events.reject');
});

require __DIR__.'/auth.php';

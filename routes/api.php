<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BalanceTransferController;
use App\Http\Controllers\CreditCardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\LenderController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SolutionsController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// All routes are served under /api/v1 (see bootstrap/app.php).

Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('register', 'register')->middleware('throttle:register');
    Route::post('login', 'login')->middleware('throttle:login');
    Route::post('login/verify-otp', 'verifyLoginOtp')->middleware('throttle:otp-verify');
    Route::post('logout', 'logout')->middleware('auth:sanctum');
});

Route::prefix('lenders')->controller(LenderController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('{id}', 'show');
});

Route::prefix('posts')->controller(PostController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('{slug}', 'show')->where('slug', '[a-z0-9-]+');
});

Route::post('inquiries', [InquiryController::class, 'store'])->middleware('throttle:10,1');

// `idempotent`: POSTs honour an Idempotency-Key header (safe retries)
Route::middleware(['auth:sanctum', 'idempotent'])->group(function () {
    Route::prefix('users')->controller(UserController::class)->group(function () {
        Route::get('me', 'show');
        Route::patch('me', 'update');
        Route::delete('me', 'destroy');
        Route::get('me/export', 'export')->middleware('throttle:account-export');
        Route::post('me/phone/send-code', 'sendPhoneCode');
        Route::post('me/phone/verify', 'verifyPhone')->middleware('throttle:otp-verify');
    });

    Route::prefix('credit-cards')->controller(CreditCardController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('{id}', 'show');
        Route::patch('{id}', 'update');
        Route::delete('{id}', 'destroy');
    });

    Route::prefix('loans')->controller(LoanController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('{id}', 'show');
        Route::get('{id}/analysis', 'analysis');
        Route::patch('{id}', 'update');
        Route::delete('{id}', 'destroy');
    });

    Route::prefix('bt')->controller(BalanceTransferController::class)->group(function () {
        Route::post('eligibility', 'eligibility');
        Route::get('offers', 'offers');
        Route::post('applications', 'apply');
        Route::get('applications', 'applications');
    });

    Route::prefix('dashboard')->controller(DashboardController::class)->group(function () {
        Route::get('summary', 'summary');
        Route::get('history', 'history');
        Route::get('full', 'full');
    });

    Route::prefix('solutions')->controller(SolutionsController::class)->group(function () {
        Route::get('avalanche', 'avalanche');
        Route::get('snowball', 'snowball');
        Route::get('eligibility', 'eligibility');
        Route::get('amortization/{loanId}', 'amortization');
        Route::get('cibil-tips', 'cibilTips');
    });

    Route::post('notifications/reminder', [NotificationController::class, 'reminder'])->middleware('throttle:reminders');

    Route::prefix('statements')->controller(StatementController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store')->middleware('throttle:uploads');
        Route::post('presigned-url', 'presignedUrl')->middleware('throttle:uploads');
        Route::get('{id}', 'show');
        Route::get('{id}/download', 'download');
        Route::delete('{id}', 'destroy');
    });
});

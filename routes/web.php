<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GroupwareController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\WebmailController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - ApexMail Collaboration Suite
|--------------------------------------------------------------------------
*/

// Authentication Routes (Unified Login Page untuk semua user dan admin)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// CAPTCHA Dynamic Generator & Reload Route
Route::get('/captcha/image', [AuthController::class, 'captchaImage'])->name('captcha.image');

// Two-Factor Authentication (Google Authenticator) Login Challenge Routes
Route::get('/two-factor-challenge', [AuthController::class, 'showTwoFactorChallenge'])->name('two-factor.challenge');
Route::post('/two-factor-challenge', [AuthController::class, 'verifyTwoFactorChallenge'])->name('two-factor.verify');
Route::post('/two-factor-challenge/cancel', [AuthController::class, 'cancelTwoFactorChallenge'])->name('two-factor.cancel');

// Device Verification Challenge (New Device / Location OTP) Routes
Route::get('/device-verification-challenge', [AuthController::class, 'showDeviceVerificationChallenge'])->name('device.challenge');
Route::post('/device-verification-challenge', [AuthController::class, 'verifyDeviceVerificationChallenge'])->name('device.verify');
Route::post('/device-verification-challenge/resend', [AuthController::class, 'resendDeviceVerificationOtp'])->name('device.resend');
Route::post('/device-verification-challenge/cancel', [AuthController::class, 'cancelDeviceVerificationChallenge'])->name('device.cancel');

// Password Reset via Recovery Email & OTP Routes
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.forgot');
Route::post('/forgot-password', [AuthController::class, 'sendResetOtp'])->name('password.send-otp');
Route::get('/forgot-password/verify', [AuthController::class, 'showVerifyOtp'])->name('password.verify-otp');
Route::post('/forgot-password/reset', [AuthController::class, 'resetPasswordWithOtp'])->name('password.reset-otp');

// Webmail & Groupware Client Routes (Protected by Auth)
Route::middleware(['auth'])->group(function () {
    Route::get('/', [WebmailController::class, 'index'])->name('webmail.index');
    Route::get('/webmail/check-updates', [WebmailController::class, 'checkUpdates'])->name('webmail.check-updates');
    Route::post('/webmail/send', [WebmailController::class, 'send'])->name('webmail.send');
    Route::post('/webmail/quick-reply', [WebmailController::class, 'quickReply'])->name('webmail.quick-reply');
    Route::post('/webmail/batch-action', [WebmailController::class, 'batchAction'])->name('webmail.batch-action');
    Route::get('/webmail/attachments/{id}/download', [WebmailController::class, 'downloadAttachment'])->name('webmail.attachment.download');
    Route::get('/webmail/attachments/{id}/inline', [WebmailController::class, 'inlineAttachment'])->name('webmail.attachment.inline');
    Route::post('/webmail/{id}/toggle-star', [WebmailController::class, 'toggleStar'])->name('webmail.toggle-star');
    Route::post('/webmail/{id}/toggle-read', [WebmailController::class, 'toggleRead'])->name('webmail.toggle-read');
    Route::delete('/webmail/{id}', [WebmailController::class, 'destroy'])->name('webmail.destroy');

    Route::get('/contacts', [GroupwareController::class, 'contacts'])->name('groupware.contacts');
    Route::get('/calendar', [GroupwareController::class, 'calendar'])->name('groupware.calendar');

    // Two-Factor Authentication Settings & Management
    Route::get('/security/two-factor', [TwoFactorController::class, 'show'])->name('security.two-factor');
    Route::post('/security/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('security.two-factor.confirm');
    Route::post('/security/two-factor/disable', [TwoFactorController::class, 'disable'])->name('security.two-factor.disable');

    // Email Forwarding Settings (Penerusan Email)
    Route::get('/settings/forwarding', [WebmailController::class, 'showForwarding'])->name('webmail.forwarding');
    Route::post('/settings/forwarding', [WebmailController::class, 'updateForwarding'])->name('webmail.forwarding.update');
});

// Admin Web Console Routes (Protected by Auth & Admin Role)
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard', [AdminController::class, 'dashboard']);

    // Password Security Policy Settings
    Route::post('/settings/password-policy', [AdminController::class, 'updatePasswordPolicy'])->name('password-policy.update');

    // Manage Accounts (CRUD, Reset Password, Suspend, Update)
    Route::get('/accounts', [AdminController::class, 'accounts'])->name('accounts');
    Route::post('/accounts', [AdminController::class, 'storeAccount'])->name('accounts.store');
    Route::put('/accounts/{id}', [AdminController::class, 'updateAccount'])->name('accounts.update');
    Route::post('/accounts/{id}/toggle-status', [AdminController::class, 'toggleAccountStatus'])->name('accounts.toggle-status');
    Route::post('/accounts/{id}/reset-password', [AdminController::class, 'resetPassword'])->name('accounts.reset-password');
    Route::delete('/accounts/{id}', [AdminController::class, 'destroyAccount'])->name('accounts.destroy');

    // Manage Aliases & Distribution Lists
    Route::get('/aliases', [AdminController::class, 'aliases'])->name('aliases');
    Route::post('/aliases', [AdminController::class, 'storeAlias'])->name('aliases.store');
    Route::delete('/aliases/{id}', [AdminController::class, 'destroyAlias'])->name('aliases.destroy');

    // Manage Domains & DNS Wizard
    Route::get('/domains', [AdminController::class, 'domains'])->name('domains');
    Route::post('/domains', [AdminController::class, 'storeDomain'])->name('domains.store');
    Route::post('/domains/{id}/dkim/regenerate', [AdminController::class, 'regenerateDkim'])->name('domains.dkim.regenerate');
    Route::post('/domains/{id}/check-dns', [AdminController::class, 'checkDomainDns'])->name('domains.check-dns');
    Route::get('/domains/{id}/export-dns', [AdminController::class, 'exportDomainDns'])->name('domains.export-dns');
    Route::delete('/domains/{id}', [AdminController::class, 'destroyDomain'])->name('domains.destroy');

    // SMTP Relays & Direct MX Outbound Delivery
    Route::get('/relays', [AdminController::class, 'relays'])->name('relays');
    Route::put('/relays/{id}', [AdminController::class, 'updateRelay'])->name('relays.update');
    Route::post('/relays/{id}/test', [AdminController::class, 'testRelay'])->name('relays.test');
    Route::post('/relays/test-direct', [AdminController::class, 'testDirectMx'])->name('relays.test-direct');

    // Mail Queue Inspector & Outgoing Delivery Logs
    Route::get('/queue', [AdminController::class, 'queue'])->name('queue');
    Route::post('/queue/flush', [AdminController::class, 'flushQueue'])->name('queue.flush');
    Route::post('/queue/{id}/retry', [AdminController::class, 'retryQueueItem'])->name('queue.retry');
    Route::post('/queue/{id}/toggle-hold', [AdminController::class, 'toggleHoldQueueItem'])->name('queue.toggle-hold');
    Route::delete('/queue/{id}', [AdminController::class, 'deleteQueueItem'])->name('queue.delete');

    // Inbound Mail & Webhook Management
    Route::get('/inbound', [AdminController::class, 'inbound'])->name('inbound');
    Route::post('/inbound/simulate', [AdminController::class, 'simulateInbound'])->name('inbound.simulate');

    // Mailbox & User Migration Suite (Zimbra / EML / CSV / Live IMAP)
    Route::get('/migration', [AdminController::class, 'migration'])->name('migration');
    Route::post('/migration/mailbox', [AdminController::class, 'importMailbox'])->name('migration.mailbox');
    Route::post('/migration/accounts', [AdminController::class, 'importAccounts'])->name('migration.accounts');
    Route::post('/migration/imap-test', [AdminController::class, 'testImap'])->name('migration.imap-test');
});


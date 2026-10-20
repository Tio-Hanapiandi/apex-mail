<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

use App\Http\Controllers\InboundMailController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Inbound Mail Webhook Routes (Brevo Inbound Parsing / Cloudflare Routing)
Route::post('/mail/inbound', [InboundMailController::class, 'handle'])->name('api.mail.inbound');
Route::post('/mail/inbound/simulate', [InboundMailController::class, 'simulate'])->name('api.mail.inbound.simulate');

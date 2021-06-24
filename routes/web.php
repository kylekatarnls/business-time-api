<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthorizationController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\HookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware(['auth:sanctum', 'verified'])->group(static function () {
    Route::get('/increase-limit/{ipOrDomain}', [Controller::class, 'increaseLimit'])->name('increase-limit');
    Route::get('/home', [Controller::class, 'home'])->name('home');
    Route::get('/dashboard', [Controller::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard/{userId}', [Controller::class, 'dashboard'])->name('user-dashboard');
    Route::post('/authorization', [AuthorizationController::class, 'create'])->name('add-authorization');
    Route::delete('/authorization', [AuthorizationController::class, 'delete'])->name('remove-authorization');
    Route::get('/authorization/verification-file/{ipOrDomain}', [AuthorizationController::class, 'getVerifyToken'])->name('authorization-verification');
    Route::get('/authorization/verify/{type}/{value}', [AuthorizationController::class, 'verify'])->name('verify-authorization');
    Route::get('/plan', [Controller::class, 'plan'])->name('plan');
    Route::post('/plan/confirm-intent', [Controller::class, 'confirmIntent'])->name('confirm-intent');
    Route::post('/plan/reject-intent', [Controller::class, 'rejectIntent'])->name('reject-intent');
    Route::get('/plan/exonerate', [Controller::class, 'exonerate'])->name('exonerate');
    Route::post('/subscribe', [Controller::class, 'subscribe'])->name('subscribe');
    Route::post('/subscribe/{plan}', [Controller::class, 'subscribePlan'])->name('subscribe-plan');
    Route::post('/subscribe/{plan}/cancel', [Controller::class, 'cancelSubscribe'])->name('subscribe-cancel');
    Route::get('/billing', [Controller::class, 'billingPortal'])->name('billing');
    Route::get('/billing/autorenew', [Controller::class, 'autorenew'])->name('autorenew');
});

Route::middleware(['admin'])->group(static function () {
    Route::get('/admin-panel/errors', [AdminController::class, 'errors'])->name('admin-errors');
    Route::get('/admin-panel/users', [AdminController::class, 'users'])->name('admin-users');
    Route::get('/admin-panel/user/{id}', [AdminController::class, 'user'])->name('admin-user')->whereNumber('id');
});

Route::redirect('/.well-known/change-password', '/user/profile#change-password');
Route::get('/verify-ip/{email}/{token}.html', [AuthorizationController::class, 'verifyIp'])
    ->name('verify-ip-token');
Route::get('/verify-ip/{email}/{token}/{ip}.html', [AuthorizationController::class, 'verifyIp'])
    ->where('ip', '\d+(\.\d+){3}')
    ->name('verify-ip');
Route::get('/contact', [Controller::class, 'contact'])->name('contact');
Route::post('/hook/jlV2H_hndjbH2VTVDgvUFTZVGHJdhgVGZCVzDE2', [HookController::class, 'deploy'])->name('hook');

Route::middleware(['throttle:53,10'])->group(function () {
    Route::post('/contact', [Controller::class, 'postContact'])->name('post-contact');
});

$staticData = [
    'appName' => config('app.name'),
    'url' => config('app.url'),
];
Route::view('/', 'index', $staticData)->name('index');
Route::view('/billing/terms', 'terms', $staticData)->name('terms');
Route::view('/billing/privacy', 'privacy', $staticData)->name('privacy');

if (config('app.debug')) {
    Route::get('/phpinfo', function () {
        phpinfo();

        exit;
    });
}

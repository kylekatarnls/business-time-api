<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('product/webhook', [ProductController::class, 'webhooks']);

Route::get('/api/calendar/{region}/{year}/events', [ApiController::class, 'events'])
    ->where('region', '[a-zA-Z._-]+')
    ->where('year', '\d{4}')
    ->name('events');

Route::middleware('auth:sanctum')->get('/user', static fn (Request $request) => $request->user());

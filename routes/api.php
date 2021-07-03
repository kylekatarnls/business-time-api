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

Route::get('calendar/{type}/{language}/{region}/{year}/events', [ApiController::class, 'events'])
    ->where('type', '(?:community|official)')
    ->where('language', '[a-zA-Z_-]+')
    ->where('region', '[a-zA-Z_-]+')
    ->where('year', '\d{4}')
    ->name('events');

Route::middleware('auth:sanctum')->get('/user', static fn (Request $request) => $request->user());

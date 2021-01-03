<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthorizationController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
    Route::post('/authorization', [AuthorizationController::class, 'create'])->name('add-authorization');
    Route::delete('/authorization', [AuthorizationController::class, 'delete'])->name('remove-authorization');
    Route::get('/authorization/verification-file/{ipOrDomain}', [AuthorizationController::class, 'getVerifyToken'])->name('authorization-verification');
    Route::get('/authorization/verify/{type}/{value}', [AuthorizationController::class, 'verify'])->name('verify-authorization');
    Route::get('/plan', [Controller::class, 'plan'])->name('plan');
    Route::post('/plan/confirm-intent', [Controller::class, 'confirmIntent'])->name('confirm-intent');
    Route::post('/plan/reject-intent', [Controller::class, 'rejectIntent'])->name('reject-intent');
    Route::get('/plan/exonerate', [Controller::class, 'exonerate'])->name('exonerate');
    Route::post('/subscribe/', [Controller::class, 'subscribe'])->name('subscribe');
    Route::post('/subscribe/{plan}', [Controller::class, 'subscribePlan'])->name('subscribe-plan');
    Route::post('/subscribe/{plan}/cancel', [Controller::class, 'cancelSubscribe'])->name('subscribe-cancel');
    Route::get('/billing', [Controller::class, 'billingPortal'])->name('billing');
});

Route::middleware(['admin'])->group(static function () {
    Route::get('/admin-panel/errors', [AdminController::class, 'errors'])->name('admin-errors');
    Route::get('/admin-panel/users', [AdminController::class, 'users'])->name('admin-users');
    Route::get('/admin-panel/user/{id}', [AdminController::class, 'user'])->name('admin-user')->whereNumber('id');
});

Route::redirect('/.well-known/change-password', '/user/profile#change-password');
Route::get('/verify-ip/{email}/{token}.html', [AuthorizationController::class, 'verifyIp'])->name('verify-ip');
Route::get('/contact', [Controller::class, 'contact'])->name('contact');

Route::middleware(['throttle:53,10'])->group(function () {
    Route::post('/contact', [Controller::class, 'postContact'])->name('post-contact');
});

$staticData = [
    'appName' => config('app.name'),
    'url' => config('app.url'),
];
Route::view('/billing/terms', 'terms', $staticData)->name('terms');
Route::view('/billing/privacy', 'privacy', $staticData)->name('privacy');

if (config('app.debug')) {
    Route::get('/', function () {
        require __DIR__.'/../index.php';

        exit;
    });

    Route::get('/phpinfo', function () {
        phpinfo();

        exit;
    });

    $files = [
        'index.js' => [
            'Content-Type' => 'application/javascript',
        ],
        'index.css' => [
            'Content-Type' => 'text/css',
        ],
        'city.jpg' => [
            'Content-Type' => 'image/jpeg',
        ],
    ];

    foreach ($files as $file => $headers) {
        Route::get("/$file", function () use ($file, $headers) {
            foreach ($headers as $name => $value) {
                header("$name: $value");
            }

            readfile(__DIR__."/../$file");

            exit;
        });
    }

    Route::any( '{path}', static function (Request $request) {
        $uri = ltrim($request->getRequestUri(), '/');

        if (preg_match('`^/?admin(?:/.*)?$`', $uri)) {
            if (preg_match('`^admin/([^/]*\.(js|css|png|jpe?g))$`', $uri, $match)) {
                header('Content-type: ' . ($match[2] === 'js' ? 'text/javascript; charset=utf-8' : 'image/' . $match[2]));
                readfile(__DIR__ . '/../admin/' . $match[1]);

                exit;
            }

            $_SERVER['HTTPS'] = 'on';

            require __DIR__.'/../admin/index.php';

            exit;
        }

        preg_match_all(
            '`RewriteRule (\^.*\$)\s+index\.php\?(.+)\s+\[`',
            file_get_contents(__DIR__ . '/../.htaccess'),
            $routes,
            PREG_SET_ORDER,
        );

        foreach ($routes as [, $regexp, $params]) {
            if (preg_match("`$regexp`", $uri, $uriMatches)) {
                $params = explode('&', $params);

                foreach ($params as $param) {
                    [$key, $value] = explode('=', $param);

                    $_GET[$key] = preg_replace_callback('/\$(\d+)/', static function ($variable) use ($uriMatches) {
                        return $uriMatches[(int) $variable[1]];
                    }, $value);
                }

                require __DIR__.'/../index.php';

                exit;
            }
        }

        throw new NotFoundHttpException();
    })->where('path', '.*');
}

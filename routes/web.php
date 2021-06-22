<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
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

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

// Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('/route', [UserController::class, 'route'])->name('route');
Route::get('/entry', [UserController::class, 'entry']);
Route::get('/save_demographics', [UserController::class, 'save_demographics']);
Route::get('/comples', [UserController::class, 'completes']);
Route::get('/so', [UserController::class, 'so']);
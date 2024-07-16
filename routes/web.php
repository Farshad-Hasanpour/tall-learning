<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Passwords\Confirm;
use App\Livewire\Auth\Passwords\Email;
use App\Livewire\Auth\Passwords\Reset;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\Verify;
use App\Livewire\TodoList;
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
Route::view('/', 'home')->name('home');
Route::permanentRedirect('home', '/');

Route::middleware('guest')->group(function () {
    Route::get('login', Login::class)
        ->name('login');

    Route::get('register', Register::class)
        ->name('register');

	Route::get('password/reset', Email::class)
		->name('password.request');

	Route::get('password/reset/{token}', Reset::class)
		->name('password.reset');
});

Route::middleware('auth')->group(function () {
	Route::get('/todo-list', TodoList::class)->name('todo-list');

    Route::get('email/verify', Verify::class)
        ->middleware('throttle:6,1')
        ->name('verification.notice');

    Route::get('password/confirm', Confirm::class)
        ->name('password.confirm');

	Route::get('email/verify/{id}/{hash}', EmailVerificationController::class)
		->middleware('signed')
		->name('verification.verify');

	Route::post('logout', LogoutController::class)
		->name('logout');
});

Route::fallback(function(){
	return view('404')->with([
		'msg' => 'Page Not Found'
	]);
});

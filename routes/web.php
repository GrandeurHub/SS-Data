<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IndexedFileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Who can do what
|--------------------------------------------------------------------------
| Guest         (not logged in)  see the file list only; cannot open or download files
| Client        (logged in)      everything a guest can, plus open and download (single file or ZIP)
| Administrator (logged in)      everything a client can, plus add / edit / delete / restore
|                                files, and manage / delete / restore accounts
*/

// Open to everyone: the list only (titles, tags, periods ...), never the files themselves.
Route::get('/', [IndexedFileController::class, 'landing'])->name('files.landing');
Route::get('/files', [IndexedFileController::class, 'index'])->name('files.index');
Route::get('/files/stats', [IndexedFileController::class, 'stats'])->name('files.stats');

// Login. The "login" route name is what Laravel's auth middleware redirects guests to.
Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Clients and administrators.
Route::middleware('auth')->group(function () {
    Route::get('/files/{file}/preview', [IndexedFileController::class, 'preview'])->name('files.preview');
    Route::get('/files/{file}/download', [IndexedFileController::class, 'download'])->name('files.download');
    Route::post('/files/download-selected', [IndexedFileController::class, 'downloadSelected'])->name('files.download-selected');
});

// Administrators only.
Route::middleware(['auth', 'can:administer'])->group(function () {
    Route::post('/files', [IndexedFileController::class, 'store'])->name('files.store');
    Route::put('/files/{file}', [IndexedFileController::class, 'update'])->name('files.update');
    Route::delete('/files/{file}', [IndexedFileController::class, 'destroy'])->name('files.destroy');
    Route::post('/files/{id}/restore', [IndexedFileController::class, 'restore'])->whereNumber('id')->name('files.restore');

    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::put('/accounts/{user}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('/accounts/{user}', [AccountController::class, 'destroy'])->name('accounts.destroy');
    Route::post('/accounts/{id}/restore', [AccountController::class, 'restore'])->whereNumber('id')->name('accounts.restore');
});

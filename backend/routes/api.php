<?php

use App\Http\Controllers\Api\Admin\UrlEntryController as AdminUrlEntryController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UrlEntryController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // 認証
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::middleware('auth:sanctum')->post('logout', [AuthController::class, 'logout']);
    });

    // URL エントリ（認証必須）
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('urls', [UrlEntryController::class, 'index']);
        Route::post('urls', [UrlEntryController::class, 'store']);
        Route::patch('urls/{urlEntry}', [UrlEntryController::class, 'update']);
        Route::delete('urls/{urlEntry}', [UrlEntryController::class, 'destroy']);
    });

    // 管理画面（認証 + 管理者）
    Route::middleware(['auth:sanctum', EnsureUserIsAdmin::class])->prefix('admin')->group(function () {
        Route::get('urls', [AdminUrlEntryController::class, 'index']);
        Route::delete('urls/{urlEntry}', [AdminUrlEntryController::class, 'destroy']);
        Route::get('export/bookmarks', [AdminUrlEntryController::class, 'exportBookmarks']);
        Route::get('users', [AdminUserController::class, 'index']);
        Route::post('users', [AdminUserController::class, 'store']);
        Route::delete('users/{user}', [AdminUserController::class, 'destroy']);
    });

});

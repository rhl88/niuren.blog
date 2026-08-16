<?php

use App\Apps\NiurenBlog\Controllers\Web\BlogController;
use Illuminate\Support\Facades\Route;

// 注意：ServiceProvider 已配置 prefix('blog')，此处路径无需再带 /blog 前缀
// 静态路由必须放在 /{id} 之前，避免被动态参数吞掉
Route::get('/', [BlogController::class, 'index'])->name('niuren.blog.index');
Route::get('/write', [BlogController::class, 'create'])->name('niuren.blog.create');
Route::post('/publish', [BlogController::class, 'store'])->name('niuren.blog.store');
Route::get('/{id}', [BlogController::class, 'show'])
    ->whereNumber('id')
    ->name('niuren.blog.show');

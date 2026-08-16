<?php

use App\Apps\NiurenBlog\Controllers\Admin\PostController;
use Illuminate\Support\Facades\Route;

Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/create', [PostController::class, 'create']);
Route::get('/posts/{id}/edit', [PostController::class, 'edit']);

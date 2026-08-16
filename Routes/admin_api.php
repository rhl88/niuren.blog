<?php

use App\Apps\NiurenBlog\Controllers\Admin\Api\PostApiController;
use Illuminate\Support\Facades\Route;

Route::get('/posts/list', [PostApiController::class, 'list']);
Route::post('/posts/save', [PostApiController::class, 'save']);
Route::put('/posts/{id}', [PostApiController::class, 'update']);
Route::delete('/posts/{id}', [PostApiController::class, 'delete']);

<?php

use App\Apps\NiurenBlog\Controllers\Admin\Api\PostApiController;
use App\Apps\NiurenBlog\Controllers\Admin\Api\SettingApiController;
use App\Apps\NiurenBlog\Controllers\Web\UploadApiController;
use Illuminate\Support\Facades\Route;

Route::post('/setting/save', [SettingApiController::class, 'save']);
Route::post('/upload', [UploadApiController::class, 'uploadImage']);
Route::get('/posts/list', [PostApiController::class, 'list']);
Route::post('/posts/save', [PostApiController::class, 'save']);
Route::put('/posts/{id}', [PostApiController::class, 'update']);
Route::delete('/posts/{id}', [PostApiController::class, 'delete']);

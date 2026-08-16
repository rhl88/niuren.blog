<?php

namespace App\Apps\NiurenBlog;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        $this->registerAdminRoutes();
        $this->registerWebRoutes();
        $this->loadViews();
    }

    protected function registerAdminRoutes(): void
    {
        Route::prefix('admin/niuren/blog')
            ->namespace('App\Apps\NiurenBlog\Controllers\Admin')
            ->middleware(['web', 'auth:admin'])
            ->group(base_path('app/Apps/NiurenBlog/Routes/admin.php'));

        Route::prefix('api/admin/niuren/blog')
            ->namespace('App\Apps\NiurenBlog\Controllers\Admin\Api')
            ->middleware(['web', 'auth:admin'])
            ->group(base_path('app/Apps/NiurenBlog/Routes/admin_api.php'));
    }

    protected function registerWebRoutes(): void
    {
        Route::prefix('blog')
            ->namespace('App\Apps\NiurenBlog\Controllers\Web')
            ->middleware(['web'])
            ->group(base_path('app/Apps/NiurenBlog/Routes/web.php'));
    }

    protected function loadViews(): void
    {
        $this->loadViewsFrom(
            base_path('app/Apps/NiurenBlog/Views'),
            'niuren.blog'
        );
    }
}

<?php

namespace App\Apps\NiurenBlog;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * 启动应用：注册后台路由、前台路由、加载视图
     */
    public function boot(): void
    {
        $this->registerAdminRoutes();
        $this->registerWebRoutes();
        $this->loadViews();
    }

    /**
     * 注册后台路由（页面视图 + API）
     *
     * 页面路由：GET /admin/niuren/blog/posts（列表/新增/编辑）
     * API 路由：/api/admin/niuren/blog/posts（CRUD，含权限中间件 can:）
     * 中间件：web + auth:admin（仅管理员可访问）
     */
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

    /**
     * 注册前台路由（公开访问，无需登录）
     *
     * 路由前缀：/blog（朋友圈动态流、写动态、详情）
     * 中间件：仅 web（无 auth，前台公开）
     */
    protected function registerWebRoutes(): void
    {
        Route::prefix('blog')
            ->namespace('App\Apps\NiurenBlog\Controllers\Web')
            ->middleware(['web'])
            ->group(base_path('app/Apps/NiurenBlog/Routes/web.php'));
    }

    /**
     * 加载视图，命名空间为 niuren.blog
     *
     * 使用方式：view('niuren.blog::Admin.Post.index')
     */
    protected function loadViews(): void
    {
        $this->loadViewsFrom(
            base_path('app/Apps/NiurenBlog/Views'),
            'niuren.blog'
        );
    }
}

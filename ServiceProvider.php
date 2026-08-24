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
     * 按访问模式注册前台路由（公开访问，无需登录）
     *
     * 三种访问模式（后台「博客设置 → 访问设置」配置驱动）：
     * - root 模式（根路径）：路由注册在站点根路径 /，无 prefix 无 domain
     * - path 模式（路径前缀）：Route::prefix('{access_path_prefix}')，如 /blog
     * - domain 模式（子域名绑定）：由系统 AppServiceProvider::registerDomainRoutesEarly()
     *   扫描 *_access_domain 配置后提前注册 Routes/home.php（含子域名 catch-all 隔离），
     *   应用侧检测到 domain_routes_registered 标记后跳过，避免重复注册污染主域名
     *
     * 中间件：仅 web（无 auth，前台公开）
     */
    protected function registerWebRoutes(): void
    {
        // domain 模式：系统已按域名提前注册前台路由（Routes/home.php + catch-all），跳过本地注册
        if ($this->app->bound('domain_routes_registered.niuren.blog')) {
            return;
        }

        // 无配置时默认 path + /blog（与历史行为一致，避免未配置应用抢占根路径）
        $accessMode = $this->getConfigValue('access_mode', 'path');

        if ($accessMode === 'domain') {
            // 配置为子域名模式但系统未注册域名路由（如域名配置异常被跳过）：
            // 不在主域名注册，避免与主站路由冲突，由系统兜底处理
            return;
        }

        // path 模式使用配置的路径前缀；root 模式无前缀（注册在根路径）
        $prefix = $accessMode === 'path'
            ? trim($this->getConfigValue('access_path_prefix', '/blog'), '/')
            : '';

        Route::prefix($prefix)
            ->namespace('App\Apps\NiurenBlog\Controllers\Web')
            ->middleware(['web'])
            ->group(base_path('app/Apps/NiurenBlog/Routes/web.php'));
    }

    /**
     * 从数据库读取本应用配置值（读取失败或未配置返回默认值）
     */
    protected function getConfigValue(string $name, string $default = ''): string
    {
        try {
            $value = \App\Models\ConfigItem::where('code', 'app_niuren_blog_' . $name)->value('value');

            return (string) ($value ?? $default);
        } catch (\Throwable $e) {
            return $default;
        }
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

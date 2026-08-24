<?php

/**
 * 朋友圈博客 · 子域名绑定模式前台路由入口
 *
 * 系统 AppServiceProvider::registerDomainRoutesEarly() 在 domain 访问模式下
 * 会按 Routes/home.php + Controllers\Home 命名约定提前注册本文件（包裹
 * Route::domain($domain)），并为子域名追加 catch-all 404 隔离路由。
 *
 * 本应用前台控制器位于 Controllers\Web 命名空间，且 Routes/web.php 中的
 * 路由全部使用完整 use 类名声明（不依赖 group 的 namespace 属性），
 * 故此处直接复用 web.php 的路由定义，避免两份文件内容漂移。
 */

require __DIR__ . '/web.php';

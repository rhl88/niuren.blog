# CmsPro-朋友圈博客-特性清单

> 应用标识：`niuren.blog` ｜ 版本：1.0.2 ｜ 更新日期：2026-08-18

本文档系统整理朋友圈博客应用覆盖的 CMSPRO 框架特性及对应实现位置，作为应用开发与维护参考。

## 一、目录结构总览

```
app/Apps/NiurenBlog/
├── manifest.json              # 应用声明（菜单/配置组/权限）
├── icon.svg                   # 应用图标
├── ServiceProvider.php        # 服务提供者（路由注册/视图加载）
├── Install.php                # 安装/升级/卸载（迁移执行）
├── Routes/
│   ├── admin.php              # 后台视图路由
│   ├── admin_api.php          # 后台 API 路由
│   └── web.php                # 前台路由
├── Controllers/
│   ├── Admin/
│   │   ├── PostController.php # 后台页面控制器
│   │   └── Api/
│   │       └── PostApiController.php  # 后台 API 控制器
│   └── Web/
│       └── BlogController.php # 前台控制器
├── Models/
│   └── Post.php               # 文章模型
├── Services/
│   └── PostService.php        # 文章业务服务层
├── Migrations/
│   └── 2026_01_01_000001_create_posts_table.php
├── Views/
│   ├── Admin/Post/            # 后台视图（index/create）
│   └── Web/                   # 前台视图（index/show/create）
├── Assets/
│   ├── css/style.css          # 朋友圈风格样式
│   └── js/app.js              # 前台脚本
├── Tests/
│   └── Feature/
│       └── PostApiTest.php    # 应用级测试
└── doc/                       # 应用文档目录
```

## 二、框架特性覆盖清单

| 特性 | 实现位置 | 说明 |
|------|----------|------|
| 应用声明 | `manifest.json` | id/name/version/menus/config_groups/permissions 完整声明，菜单含 code 稳定标识 |
| 服务提供者 | `ServiceProvider.php` | 后台路由（`auth:admin` 中间件）、后台 API 路由、前台路由注册，视图命名空间 `niuren.blog` |
| 数据库隔离 | `Migrations/` | 表名前缀 `app_niuren_blog_`，符合应用数据库隔离规范 |
| 模型规范 | `Models/Post.php` | `$casts` 日期格式 `Y-m-d H:i:s`、images JSON 数组转换、状态常量 |
| 服务层 | `Services/PostService.php` | 后台筛选分页、前台已发布列表、增删改，供其他应用复用 |
| 配置系统 | `manifest.json` config_groups | `blog_settings` 配置组，`posts_per_page` 每页文章数，存储于 config_items（code：`app_niuren_blog_posts_per_page`） |
| 权限声明 | `manifest.json` permissions | `niuren.blog.manage` / `post.create` / `post.edit` / `post.delete` |
| Assets 发布 | `Assets/` → `public/apps/niuren.blog/` | 安装/升级时系统自动链接/复制，视图通过 `asset('apps/niuren.blog/...')` 引用 |
| 视图规范 | `Views/Admin/Post/index.blade.php` | 独立完整 HTML、Layui 模板 `@verbatim` 包裹、`parseData` 适配 API 响应、`{{ asset() }}` 本地资源引用 |
| 安装卸载 | `Install.php` | install/uninstall/upgrade 自动执行迁移与回滚 |
| 应用测试 | `Tests/Feature/PostApiTest.php` | Laravel 功能测试覆盖列表/新增/更新/删除/前台发布/未认证拦截 |

## 三、路由清单

### 后台页面（`admin/niuren/blog`，中间件 `web` + `auth:admin`）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/admin/niuren/blog/posts` | 文章管理列表页 |
| GET | `/admin/niuren/blog/posts/create` | 写文章页 |
| GET | `/admin/niuren/blog/posts/{id}/edit` | 编辑文章页 |

### 后台 API（`api/admin/niuren/blog`，中间件 `web` + `auth:admin`）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/admin/niuren/blog/posts/list` | 分页列表（keyword/status 筛选） |
| POST | `/api/admin/niuren/blog/posts/save` | 新建文章 |
| PUT | `/api/admin/niuren/blog/posts/{id}` | 更新文章 |
| DELETE | `/api/admin/niuren/blog/posts/{id}` | 删除文章 |

### 前台（`blog`，中间件 `web`）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/blog` | 朋友圈动态流（仅已发布） |
| GET | `/blog/write` | 写动态页 |
| POST | `/blog/publish` | 发布动态 |
| GET | `/blog/{id}` | 动态详情（`{id}` 约束为数字） |

## 四、数据库表

### app_niuren_blog_posts（文章表）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT UNSIGNED AUTO_INCREMENT | 主键 |
| title | VARCHAR(255) NULL | 标题（动态可不带标题） |
| content | TEXT | 正文 |
| images | TEXT NULL | 图片 URL JSON 数组 |
| status | TINYINT UNSIGNED DEFAULT 1 | 0-草稿 1-已发布（索引） |
| create_time | TIMESTAMP NULL | 创建时间 |
| update_time | TIMESTAMP NULL | 更新时间 |

## 五、版本历史

| 版本 | 日期 | 说明 |
|------|------|------|
| 1.0.2 | 2026-08-18 | 验收修复：补齐后端权限校验（PostApiController 各方法 requirePermission）、Ajax 401 处理与 error 回调、表格 skin:false + 完整分页、防重复提交；新增 .exportignore/.gitignore/README.md；manifest 声明 home_routes 路由冲突检测；ServiceProvider 补充注释 |
| 1.0.1 | 2026-08-15 | 按开发文档补齐规范：新增 doc/ 三份文档、icon.svg、Assets 静态资源（css/js）、PostService 服务层、应用级测试；修复前台路由双重前缀导致 404、Layui 模板未 @verbatim、表格缺 parseData、迁移表注释驱动兼容、manifest 菜单补 code |
| 1.0.0 | 2026-01-01 | 初始版本：朋友圈风格轻博客，后台文章管理 + 前台动态流 |

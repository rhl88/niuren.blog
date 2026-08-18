# 朋友圈博客 应用文档

## 元信息

- 应用 ID：`niuren.blog`
- 文档版本：1.0.2
- 应用版本：1.0.2
- 依赖：PHP >= 8.1，CmsPro >= 5.0.0
- 官方地址：https://www.cmspro.cn/apps/niuren.blog

## 应用概述

朋友圈博客是一款模仿微信朋友圈风格的轻博客应用，支持后台文章管理与前台动态流展示。

- **前台**：卡片式动态流，支持发布纯文字/带标题动态，图片九宫格展示
- **后台**：文章管理（列表筛选、写文章、编辑、删除、草稿/发布状态管理）

## 目录结构

```
app/Apps/NiurenBlog/
├── manifest.json              # 应用声明（菜单/配置组/权限）
├── icon.svg                   # 应用图标
├── ServiceProvider.php        # 服务提供者（路由注册/视图加载）
├── Install.php                # 安装/升级/卸载（迁移执行）
├── Routes/
│   ├── admin.php              # 后台视图路由
│   ├── admin_api.php          # 后台 API 路由（含权限中间件）
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
└── doc/                       # 应用文档
```

## 文档

- [特性清单](doc/CmsPro-朋友圈博客-特性清单.md)
- [使用指南](doc/CmsPro-朋友圈博客-使用指南.md)
- [扩展指南](doc/CmsPro-朋友圈博客-扩展指南.md)
# 朋友圈博客 应用文档

## 元信息

- 应用 ID：`niuren.blog`
- 文档版本：1.4.0
- 应用版本：1.4.0
- 依赖：PHP >= 8.1，CmsPro >= 5.0.0
- 官方地址：https://www.cmspro.cn/apps/niuren.blog

## 应用概述

朋友圈博客是一款模仿微信朋友圈风格的轻博客应用，支持后台文章管理与前台动态流展示。

- **前台**：卡片式动态流，支持发布纯文字/带标题动态，图片九宫格展示；已发布动态支持点赞（昵称列表）与评论（昵称/邮箱/网址 + emoji）；页头展示后台配置的博客名称、头像与背景图；可配置发布密码保护前台发布
- **后台**：文章管理（列表筛选、写文章、编辑、删除、草稿/发布状态管理）；博客设置（博客名称/头像/背景图、发布密码、每页文章数、访问方式）

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
│   │   ├── SettingController.php # 博客设置页面控制器
│   │   └── Api/
│   │       ├── PostApiController.php  # 后台 API 控制器
│   │       ├── SettingApiController.php # 设置保存 API 控制器
│   │       └── UploadApiController.php # 图片上传 API 控制器
│   └── Web/
│       ├── BlogController.php # 前台控制器（列表/详情/发布/发布密码校验）
│       ├── CommentApiController.php # 评论 API
│       ├── LikeApiController.php # 点赞 API
│       └── UploadApiController.php # 前台图片上传 API
├── Models/
│   ├── Post.php               # 文章模型
│   ├── PostComment.php        # 评论模型
│   └── PostLike.php           # 点赞模型
├── Services/
│   ├── PostService.php        # 文章业务服务层
│   └── VisitorId.php          # 访客指纹解析
├── Migrations/
│   ├── 2026_01_01_000001_create_posts_table.php
│   ├── 2026_02_01_000001_add_blog_access_settings.php
│   ├── 2026_02_02_000001_create_post_likes_and_comments_tables.php
│   └── 2026_03_01_000001_add_blog_profile_and_comment_meta.php
├── Views/
│   ├── Admin/Post/            # 后台视图（index/create）
│   ├── Admin/Setting/         # 后台设置视图（index）
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
# 朋友圈博客 应用文档

> 应用ID：`niuren.blog` | 文档版本：1.4.2 | 应用版本：1.4.2 | 依赖：PHP >= 8.1, CmsPro >= 5.0.0

- 官方地址：https://www.cmspro.cn/apps/niuren.blog

## 1. 应用概述

朋友圈博客是一款模仿微信朋友圈风格的轻博客应用，支持后台文章管理与前台动态流展示，前台体验贴近移动端朋友圈。

- **前台**：卡片式动态流（图片九宫格展示、长短文混排、纯文字动态）；点赞昵称列表与多层评论回复（昵称/邮箱/网址 + emoji）；页头展示后台配置的博客名称、头像与背景图；支持浅色/深色主题切换（访客选择并记忆）；可按访问设置通过根路径、路径前缀或子域名三种模式对外提供
- **发布与访问**：前台可直接发布动态（可带标题/图片）；后台可配置发布密码，配置后访客须先通过密码验证（Cookie 7 天有效）方可发布；访问占用检测避免三种访问方式冲突
- **中后台**：文章管理（列表筛选、写文章、编辑、删除、草稿/发布状态管理）；博客设置（基础设置 + 访问设置、博客名称/头像/背景图、发布密码、每页展示数、主题模式）；已发布动态支持带权限的删除与微信式居中确认弹窗

## 2. 目录结构

```
app/Apps/NiurenBlog/
├── manifest.json              # 应用声明（菜单/配置组/权限）
├── icon.svg                   # 应用图标
├── ServiceProvider.php        # 服务提供者（路由注册/视图加载）
├── Install.php                # 安装/升级/卸载（迁移执行）
├── seed_dev.php               # 开发环境一次性测试数据填充脚本
├── Routes/
│   ├── admin.php              # 后台视图路由
│   ├── admin_api.php          # 后台 API 路由（含权限中间件）
│   └── web.php                # 前台路由
├── Controllers/
│   ├── Admin/
│   │   ├── PostController.php        # 后台文章页面控制器
│   │   ├── SettingController.php    # 博客设置页面控制器
│   │   └── Api/
│   │       ├── PostApiController.php      # 后台文章 API 控制器
│   │       └── SettingApiController.php   # 设置保存 API 控制器
│   └── Web/
│       ├── BlogController.php    # 前台控制器（列表/详情/发布/发布密码校验）
│       ├── CommentApiController.php # 评论 API 控制器
│       ├── LikeApiController.php # 点赞 API 控制器
│       └── UploadApiController.php # 前台图片上传 API 控制器
├── Models/
│   ├── Post.php                 # 文章模型
│   ├── PostComment.php          # 评论模型
│   └── PostLike.php             # 点赞模型
├── Services/
│   ├── PostService.php          # 文章业务服务层
│   └── VisitorId.php            # 访客指纹解析
├── Migrations/                  # 应用表迁移（app_niuren_blog_ 前缀）
│   ├── 2026_01_01_000001_create_posts_table.php
│   ├── 2026_02_01_000001_add_blog_access_settings.php
│   ├── 2026_02_02_000001_create_post_likes_and_comments_tables.php
│   ├── 2026_03_01_000001_add_blog_profile_and_comment_meta.php
│   ├── 2026_03_02_000001_add_reply_to_id_to_post_comments.php
│   └── 2026_03_03_000001_add_theme_mode_setting.php
├── Views/
│   ├── Admin/Post/               # 后台文章视图（index 列表 / create 编辑）
│   ├── Admin/Setting/            # 后台设置视图（index）
│   ├── Web/                      # 前台视图（index 动态流 / show 详情 / create 发布 / gate 密码门）
├── Assets/
│   ├── css/style.css             # 朋友圈风格样式
│   └── js/app.js                 # 前台脚本
├── Tests/
│   └── Feature/
│       └── PostApiTest.php       # 应用级测试
└── doc/                          # 应用文档
```

## 3. 应用文档

| 文档 | 说明 |
|------|------|
| [朋友圈博客-特性清单.md](doc/CmsPro-朋友圈博客-特性清单.md) | 功能特性与对应实现位置 |
| [朋友圈博客-使用指南.md](doc/CmsPro-朋友圈博客-使用指南.md) | 安装、配置、功能验证指南 |
| [朋友圈博客-扩展指南.md](doc/CmsPro-朋友圈博客-扩展指南.md) | 二次开发与集成扩展说明 |
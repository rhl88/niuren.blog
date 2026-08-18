# CmsPro-朋友圈博客-扩展指南

> 应用标识：`niuren.blog` ｜ 版本：1.0.2 ｜ 更新日期：2026-08-18

本文档说明其他应用如何调用朋友圈博客的 Service 层、模型与数据，实现跨应用集成。

## 一、Service 层调用

文章业务统一收敛在 `App\Apps\NiurenBlog\Services\PostService`，其他应用通过容器获取：

```php
use App\Apps\NiurenBlog\Services\PostService;

$postService = app(PostService::class);
```

### 1.1 可用方法

| 方法 | 签名 | 说明 |
|------|------|------|
| listForAdmin | `listForAdmin(array $filters, int $page = 1, int $pageSize = 10): LengthAwarePaginator` | 后台筛选分页（filters：keyword 标题模糊、status 状态） |
| listPublished | `listPublished(int $page = 1, int $pageSize = 10): LengthAwarePaginator` | 前台已发布文章分页 |
| findPublishedById | `findPublishedById(int $id): ?Post` | 按 ID 获取已发布文章（草稿返回 null） |
| create | `create(array $data): Post` | 新建文章（title 可选、content 必填、images 数组、status 默认发布） |
| update | `update(Post $post, array $data): Post` | 更新文章（未提交字段保留原值） |
| delete | `delete(Post $post): void` | 删除文章 |

### 1.2 调用示例

**发布一条动态**（如其他应用生成内容后自动发动态）：

```php
use App\Apps\NiurenBlog\Services\PostService;

$post = app(PostService::class)->create([
    'title' => '系统公告',
    'content' => '站点今晚例行维护，预计 30 分钟。',
    'images' => [],           // 图片 URL 数组，可空
    'status' => 1,            // 1-发布 0-草稿
]);
```

**读取最新动态**（如首页聚合展示）：

```php
$paginator = app(PostService::class)->listPublished(1, 5);

foreach ($paginator->items() as $post) {
    echo $post->title . ' - ' . $post->create_time;  // Y-m-d H:i:s
}
```

## 二、模型直接使用

```php
use App\Apps\NiurenBlog\Models\Post;

// 状态常量
Post::STATUS_DRAFT;       // 0 草稿
Post::STATUS_PUBLISHED;   // 1 已发布

// images 字段已配置 array cast，自动 JSON 编解码
$post = Post::find(1);
$images = $post->images;  // array
$post->images = ['https://example.com/a.png'];
$post->save();
```

注意事项：

- 表名 `app_niuren_blog_posts`，遵循应用数据库隔离前缀，跨应用查询请使用模型而非裸表名
- 时间字段 `create_time` / `update_time` 已配置 `$casts`（`Y-m-d H:i:s`），序列化直接输出，无需二次格式化

## 三、配置读取

应用配置存储于系统 `config_items` 表，code 前缀 `app_niuren_blog_`：

```php
use App\Models\ConfigItem;

// 每页文章数
$perPage = (int) (ConfigItem::where('code', 'app_niuren_blog_posts_per_page')->value('value') ?: 10);
```

## 四、路由复用

前台动态流路由已命名，其他应用可直接按名称生成 URL：

```php
route('niuren.blog.index');   // /blog
route('niuren.blog.show', ['id' => 1]);  // /blog/1
```

## 五、扩展点建议

| 需求 | 建议做法 |
|------|----------|
| 发布动态前注入校验/内容过滤 | 在 `PostService::create` 外层包装装饰器，或监听 Eloquent `creating` 事件 |
| 动态发布后联动通知 | 监听 `Post::created` 事件（`app/Apps/NiurenBlog/Models/Post.php` 中 `#[Observable]` 或 EventServiceProvider 注册） |
| 其他应用展示最新动态 | 调用 `listPublished`，勿绕过 Service 直接拼 SQL |
| 定时同步外部内容为动态 | 调度任务中调用 `PostService::create`，`status` 传 0 先入草稿再人工审核 |

## 六、版本兼容

- 1.0.x 保持 `PostService` 上述方法签名稳定；后续新增方法不改变现有签名
- 涉及表结构变更将通过 Migrations 增量迁移，已有字段不重命名、不收窄类型

<?php

namespace App\Apps\NiurenBlog\Services;

use App\Apps\NiurenBlog\Models\Post;
use App\Apps\NiurenBlog\Models\PostComment;
use App\Apps\NiurenBlog\Models\PostLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 朋友圈博客文章服务层
 *
 * 统一承载后台管理与前台展示的文章业务逻辑，
 * 其他应用可通过容器获取本服务复用文章能力（详见 doc/扩展指南）。
 */
class PostService
{
    /**
     * 后台文章分页列表
     *
     * @param array $filters 筛选条件：keyword（内容模糊）、status（0草稿/1发布）
     * @param int $page 页码
     * @param int $pageSize 每页条数
     */
    public function listForAdmin(array $filters, int $page = 1, int $pageSize = 10): LengthAwarePaginator
    {
        return $this->buildQuery($filters)
            ->orderByDesc('id')
            ->paginate($pageSize, ['id', 'title', 'content', 'status', 'create_time', 'update_time'], 'page', $page);
    }

    /**
     * 前台已发布文章分页列表（附带点赞数/评论数）
     */
    public function listPublished(int $page = 1, int $pageSize = 10): LengthAwarePaginator
    {
        $paginator = Post::query()
            ->where('status', Post::STATUS_PUBLISHED)
            ->orderByDesc('id')
            ->paginate($pageSize, ['id', 'title', 'content', 'images', 'create_time'], 'page', $page);

        $this->attachCounts($paginator->getCollection());

        return $paginator;
    }

    /**
     * 前台文章详情（仅已发布，附带点赞数/评论数）
     */
    public function findPublishedById(int $id): ?Post
    {
        $post = Post::query()
            ->where('id', $id)
            ->where('status', Post::STATUS_PUBLISHED)
            ->first();

        if ($post !== null) {
            $this->attachCounts(collect([$post]));
        }

        return $post;
    }

    /**
     * 新建文章
     *
     * @param array $data 已验证数据：title、content、images、status
     */
    public function create(array $data): Post
    {
        return Post::create([
            'title' => $data['title'] ?? null,
            'content' => $data['content'],
            'images' => $data['images'] ?? [],
            'status' => (int) ($data['status'] ?? Post::STATUS_PUBLISHED),
        ]);
    }

    /**
     * 更新文章（未提交的字段保留原值）
     */
    public function update(Post $post, array $data): Post
    {
        $post->fill([
            'title' => array_key_exists('title', $data) ? $data['title'] : $post->title,
            'content' => $data['content'],
            'images' => array_key_exists('images', $data) ? $data['images'] : $post->images,
            'status' => (int) ($data['status'] ?? $post->status),
        ])->save();

        return $post;
    }

    /**
     * 删除文章
     */
    public function delete(Post $post): void
    {
        $post->delete();
    }

    /**
     * 批量附加点赞数与评论数（分组聚合，避免 N+1 与 withCount 列冲突）
     *
     * @param iterable $posts Post 模型集合或数组
     */
    protected function attachCounts(iterable $posts): void
    {
        $ids = collect($posts)->pluck('id')->filter()->all();
        if ($ids === []) {
            return;
        }

        $likeCounts = PostLike::query()
            ->whereIn('post_id', $ids)
            ->selectRaw('post_id, COUNT(*) AS aggregate')
            ->groupBy('post_id')
            ->pluck('aggregate', 'post_id');

        $commentCounts = PostComment::query()
            ->whereIn('post_id', $ids)
            ->selectRaw('post_id, COUNT(*) AS aggregate')
            ->groupBy('post_id')
            ->pluck('aggregate', 'post_id');

        foreach ($posts as $post) {
            $post->likes_count = (int) ($likeCounts[$post->id] ?? 0);
            $post->comments_count = (int) ($commentCounts[$post->id] ?? 0);
        }
    }

    /**
     * 构造后台筛选查询
     */
    protected function buildQuery(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $query = Post::query();

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where('content', 'like', '%' . $keyword . '%');
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        return $query;
    }
}

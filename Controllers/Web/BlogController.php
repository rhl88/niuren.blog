<?php

namespace App\Apps\NiurenBlog\Controllers\Web;

use App\Apps\NiurenBlog\Models\Post;
use App\Apps\NiurenBlog\Services\PostService;
use App\Http\Responses\ApiResponse;
use App\Models\ConfigItem;
use Illuminate\Http\Request;

/**
 * 前台博客控制器（朋友圈风格展示与发布）
 */
class BlogController
{
    public function __construct(protected PostService $postService) {}

    /**
     * 前台文章列表（仅已发布）
     */
    public function index(Request $request)
    {
        $page = (int) $request->query('page', 1);
        $pageSize = $this->postsPerPage();

        $paginator = $this->postService->listPublished($page, $pageSize);

        return view('niuren.blog::Web.index', [
            'list' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'pageSize' => $paginator->perPage(),
        ]);
    }

    /**
     * 文章详情（仅已发布）
     */
    public function show(int $id)
    {
        $post = $this->postService->findPublishedById($id);
        abort_if($post === null, 404);

        return view('niuren.blog::Web.show', compact('post'));
    }

    /**
     * 写文章页
     */
    public function create()
    {
        return view('niuren.blog::Web.create');
    }

    /**
     * 发布动态（前台发布默认为已发布状态）
     */
    public function store(Request $request): array
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'required|string',
            'images' => 'nullable|array',
        ]);

        $post = $this->postService->create($data + ['status' => Post::STATUS_PUBLISHED]);

        return ApiResponse::success($post, '发布成功');
    }

    /**
     * 读取后台配置的每页文章数（config_items 表，code 前缀 app_niuren_blog_）
     */
    protected function postsPerPage(): int
    {
        $value = ConfigItem::where('code', 'app_niuren_blog_posts_per_page')->value('value');

        return max(1, (int) ($value ?: 10));
    }
}

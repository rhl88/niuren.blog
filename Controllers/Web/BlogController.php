<?php

namespace App\Apps\NiurenBlog\Controllers\Web;

use App\Apps\NiurenBlog\Models\Post;
use App\Apps\NiurenBlog\Models\PostLike;
use App\Apps\NiurenBlog\Services\PostService;
use App\Apps\NiurenBlog\Services\VisitorId;
use App\Http\Responses\ApiResponse;
use App\Models\ConfigItem;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 前台博客控制器（朋友圈风格展示与发布）
 */
class BlogController
{
    public function __construct(protected PostService $postService, protected VisitorId $visitorId) {}

    /**
     * 前台文章列表（仅已发布）
     */
    public function index(Request $request)
    {
        $page = (int) $request->query('page', 1);
        $pageSize = $this->postsPerPage();

        $paginator = $this->postService->listPublished($page, $pageSize);
        $items = $paginator->items();
        $postIds = collect($items)->pluck('id')->all();

        return view('niuren.blog::Web.index', [
            'list' => $items,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'pageSize' => $paginator->perPage(),
            'blog' => $this->webSettings(),
            // 首屏直接标红当前访客已赞文章，避免前端二次请求造成状态闪烁
            'likedIds' => $this->likedPostIds($postIds, $request),
            // 每篇文章的点赞昵称列表（首屏渲染）
            'likersMap' => $this->likersByPost($postIds),
        ]);
    }

    /**
     * 文章详情（仅已发布）
     */
    public function show(int $id, Request $request)
    {
        $post = $this->postService->findPublishedById($id);
        abort_if($post === null, 404);

        return view('niuren.blog::Web.show', [
            'post' => $post,
            'blog' => $this->webSettings(),
            'likedIds' => $this->likedPostIds([$post->id], $request),
            'likersMap' => $this->likersByPost([$post->id]),
        ]);
    }

    /**
     * 写文章页
     */
    public function create()
    {
        return view('niuren.blog::Web.create', [
            'blog' => $this->webSettings(),
        ]);
    }

    /**
     * 发布动态（前台发布默认为已发布状态）
     *
     * 若后台配置了发布密码，则必须校验发布者提交的密码，通过后方可发布。
     */
    public function store(Request $request): array
    {
        $this->verifyPublishPermission($request);

        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'required_without:images|string',
            'images' => 'nullable|array|max:9',
            'images.*' => 'string',
        ]);

        $post = $this->postService->create($data + ['status' => Post::STATUS_PUBLISHED]);

        return ApiResponse::success($post, '发布成功');
    }

    /**
     * 校验当前访客是否有发布权限（后台配置了发布密码时需校验密码）
     */
    protected function verifyPublishPermission(Request $request): void
    {
        $password = $this->configValue('publish_password');
        if ($password === '') {
            return;
        }

        // 已通过密码验证（响应 Cookie 标记）可放行
        if ($this->isVerified($request)) {
            return;
        }

        $submitted = (string) $request->input('password', '');
        if (! hash_equals($password, $submitted)) {
            throw ValidationException::withMessages([
                'password' => '发布密码不正确',
            ]);
        }
    }

    /**
     * 校验发布密码接口
     *
     * 后台配置了发布密码时，前端此接口校验；校验通过后下发验证标记 Cookie，
     * 后续发布请求携带该 Cookie 即可免再次输入（游客未验证时发布接口仍强制校验）。
     */
    public function verifyPassword(Request $request): array
    {
        $password = $this->configValue('publish_password');

        $submitted = (string) $request->input('password', '');
        if ($password === '' || hash_equals($password, $submitted)) {
            // 未配置密码或校验通过：标记为已验证
            cookie()->queue($this->verifiedCookieName(), '1', 60 * 24 * 7);

            return ApiResponse::success(['ok' => true], '验证成功');
        }

        return ApiResponse::error(40301, '发布密码不正确');
    }

    /**
     * 已验证标记 Cookie 名称
     */
    protected function verifiedCookieName(): string
    {
        return 'nr_blog_pwd';
    }

    /**
     * 当前访客是否已通过发布密码验证
     */
    protected function isVerified(Request $request): bool
    {
        return hash_equals('1', (string) $request->cookie($this->verifiedCookieName(), ''));
    }

    /**
     * 当前访客已点赞的文章 ID 集合
     *
     * 复用 VisitorId 三级策略；首次访问会生成新指纹并顺带下发 Cookie，
     * 保证页面首屏与后续接口调用两侧身份一致。
     *
     * @param array $postIds 待检查的文章 ID
     */
    protected function likedPostIds(array $postIds, Request $request): array
    {
        if ($postIds === []) {
            return [];
        }

        $visitorId = $this->visitorId->resolve($request);

        return PostLike::query()
            ->whereIn('post_id', $postIds)
            ->where('visitor_id', $visitorId)
            ->pluck('post_id')
            ->all();
    }

    /**
     * 读取后台配置的每页文章数（config_items 表，code 前缀 app_niuren_blog_）
     */
    protected function postsPerPage(): int
    {
        $value = ConfigItem::where('code', 'app_niuren_blog_posts_per_page')->value('value');

        return max(1, (int) ($value ?: 10));
    }

    /**
     * 前台页头展示配置（博客名称 / 头像 / 背景图）
     *
     * @return array{name: string, avatar: string, bg: string, publish_password: string}
     */
    protected function webSettings(): array
    {
        $name = $this->configValue('blog_name');

        return [
            'name' => $name !== '' ? $name : '朋友圈',
            'avatar' => $this->configValue('blog_avatar'),
            'bg' => $this->configValue('blog_bg'),
            'publish_password' => $this->configValue('publish_password'),
        ];
    }

    /**
     * 读取单个配置项值（无值返回空串）
     */
    protected function configValue(string $name): string
    {
        return (string) ConfigItem::where('code', 'app_niuren_blog_' . $name)->value('value');
    }

    /**
     * 每篇文章的点赞昵称列表（首屏渲染），按点赞时间升序返回
     *
     * @param array $postIds 文章 ID 集合（可为空）
     * @return array<int, string[]> post_id → 昵称列表
     */
    protected function likersByPost(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $rows = PostLike::query()
            ->whereIn('post_id', $postIds)
            ->orderBy('create_time')
            ->get(['post_id', 'nickname']);

        $map = [];
        foreach ($rows as $row) {
            $nick = trim((string) $row->nickname);
            $map[$row->post_id][] = $nick !== '' ? $nick : '访客';
        }

        return $map;
    }
}

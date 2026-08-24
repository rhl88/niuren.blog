<?php

namespace App\Apps\NiurenBlog\Controllers\Web;

use App\Apps\NiurenBlog\Models\Post;
use App\Apps\NiurenBlog\Models\PostComment;
use App\Apps\NiurenBlog\Models\PostLike;
use App\Apps\NiurenBlog\Services\PostService;
use App\Apps\NiurenBlog\Services\VisitorId;
use App\Http\Responses\ApiResponse;
use App\Models\ConfigItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * 前台博客控制器（朋友圈风格展示与发布）
 */
class BlogController
{
    public function __construct(protected PostService $postService, protected VisitorId $visitorId) {}

    /**
     * 前台文章列表（仅已发布）
     *
     * 普通请求渲染首屏视图；AJAX 请求（X-Requested-With / Accept JSON）返回
     * JSON 分页数据，供前端滚动到底自动加载下一页。
     */
    public function index(Request $request)
    {
        $page = (int) $request->query('page', 1);
        $pageSize = $this->postsPerPage();

        $paginator = $this->postService->listPublished($page, $pageSize);
        $items = $paginator->items();
        $postIds = collect($items)->pluck('id')->all();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'code'    => 0,
                'message' => 'success',
                'data'    => [
                    'items'    => $this->feedItems($items, $request),
                    'has_more' => $paginator->hasMorePages(),
                    'page'     => $paginator->currentPage(),
                    'total'    => $paginator->total(),
                ],
            ]);
        }

        return view('niuren.blog::Web.index', [
            'list' => $items,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'pageSize' => $paginator->perPage(),
            'hasMore' => $paginator->hasMorePages(),
            'blog' => $this->webSettings(),
            // 前台基础路径（path 模式为前缀如 /blog，其余模式为空串）
            'basePath' => $this->webBasePath(),
            // 首屏直接标红当前访客已赞文章，避免前端二次请求造成状态闪烁
            'likedIds' => $this->likedPostIds($postIds, $request),
            // 每篇文章的点赞昵称列表（首屏渲染）
            'likersMap' => $this->likersByPost($postIds),
            // 博主昵称（已验证发布密码或后台登录时为博客名称，评论框自动填充）
            'ownerNickname' => $this->ownerNickname($request),
            // 后台登录时前台显示删除入口
            'isAdmin' => Auth::guard('admin')->check(),
        ]);
    }

    /**
     * 动态流 JSON 数据项（AJAX 分页追加渲染用）
     *
     * @param array $posts 文章模型列表
     * @return array<int, array<string, mixed>>
     */
    protected function feedItems(array $posts, Request $request): array
    {
        $postIds = collect($posts)->pluck('id')->all();
        $likedIds = $this->likedPostIds($postIds, $request);
        $likersMap = $this->likersByPost($postIds);

        return array_map(fn (Post $post) => [
            'id'             => $post->id,
            'title'          => (string) $post->title,
            'content'        => (string) $post->content,
            'images'         => $post->images ?? [],
            'create_time'    => (string) $post->create_time,
            'likes_count'    => (int) ($post->likes_count ?? 0),
            'comments_count' => (int) ($post->comments_count ?? 0),
            'liked'          => in_array($post->id, $likedIds, true),
            'likers'         => array_values($likersMap[$post->id] ?? []),
            // 后台登录时 AJAX 追加卡片也显示删除入口
            'is_admin'       => Auth::guard('admin')->check(),
        ], array_values($posts));
    }

    /**
     * 删除动态（仅后台登录管理员，前台删除入口）
     *
     * 关联的点赞、评论一并删除，保持数据一致。
     */
    public function destroy(Request $request, int $id): array
    {
        if (! Auth::guard('admin')->check()) {
            return ApiResponse::error(40301, '仅管理员可删除动态');
        }

        $post = Post::find($id);
        if ($post === null) {
            return ApiResponse::error(40401, '动态不存在');
        }

        PostLike::where('post_id', $post->id)->delete();
        PostComment::where('post_id', $post->id)->delete();
        $post->delete();

        return ApiResponse::success(null, '已删除');
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
            'basePath' => $this->webBasePath(),
            'likedIds' => $this->likedPostIds([$post->id], $request),
            'likersMap' => $this->likersByPost([$post->id]),
            // 博主昵称（已验证发布密码或后台登录时为博客名称，评论框昵称自动填充）
            'ownerNickname' => $this->ownerNickname($request),
        ]);
    }

    /**
     * 博主昵称：已验证发布密码或后台登录的访客返回博客名称，普通访客返回 null
     *
     * 用于前端评论框昵称自动填充（博客名称来自后台「博客设置」）。
     */
    protected function ownerNickname(Request $request): ?string
    {
        if ($this->isVerified($request) || Auth::guard('admin')->check()) {
            return $this->webSettings()['name'];
        }

        return null;
    }

    /**
     * 写文章页
     *
     * 权限前置：未通过发布密码验证且未后台登录的访客，渲染独立密码门页面
     * （不含发布表单与上传逻辑），验证成功后方可进入发布表单；
     * 防止审查元素移除前端遮罩后绕过密码。
     */
    public function create()
    {
        if (! $this->canPublish(request())) {
            return view('niuren.blog::Web.gate', [
                'blog' => $this->webSettings(),
                'basePath' => $this->webBasePath(),
            ]);
        }

        return view('niuren.blog::Web.create', [
            'blog' => $this->webSettings(),
            'basePath' => $this->webBasePath(),
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
     * 当前访客是否具备发布资格（无密码直接进入表单的判定口径）
     *
     * 已后台登录、或已通过发布密码验证、或后台未配置发布密码 → true。
     * 写动态页渲染与图片上传共用此口径，防止审查元素移除前端遮罩绕过密码。
     */
    public function canPublish(Request $request): bool
    {
        if ($this->configValue('publish_password') === '') {
            return true;
        }

        return $this->isVerified($request) || Auth::guard('admin')->check();
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
            // 前台默认显示模式（light 浅色 / dark 深色），访客本地选择优先
            'theme_mode' => $this->configValue('theme_mode') === 'dark' ? 'dark' : 'light',
        ];
    }

    /**
     * 前台基础路径（root/domain 模式为空串，path 模式为路径前缀如 /blog）
     *
     * 视图注入 window.NR_BASE 供前端 AJAX 与页面跳转拼接使用，
     * 保证切换访问方式后前台请求路径始终正确。
     */
    protected function webBasePath(): string
    {
        // 默认口径与 ServiceProvider::registerWebRoutes() 保持一致：无配置时视为 path + /blog
        $mode = $this->configValue('access_mode');
        if ($mode === '') {
            $mode = 'path';
        }
        if ($mode !== 'path') {
            return '';
        }

        $prefix = $this->configValue('access_path_prefix') ?: '/blog';
        $prefix = rtrim(str_replace('\\', '/', trim($prefix)), '/');

        return $prefix !== '' ? '/' . ltrim($prefix, '/') : '';
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

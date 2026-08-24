<?php

namespace App\Apps\NiurenBlog\Controllers\Web;

use App\Apps\NiurenBlog\Models\Post;
use App\Apps\NiurenBlog\Models\PostComment;
use App\Apps\NiurenBlog\Services\VisitorId;
use App\Models\ConfigItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * 朋友圈博客评论 API 控制器
 *
 * 无登录体系，访客身份由 VisitorId 三级策略解析；
 * 评论支持匿名（昵称留空时前端展示「访客」）。
 */
class CommentApiController extends Controller
{
    /** 单页最大返回条数 */
    protected const PAGE_SIZE = 50;

    public function __construct(protected VisitorId $visitorId)
    {
    }

    /**
     * 评论列表
     *
     * 入参 post_id（query）；按时间倒序分页返回，含当前访客标识便于前端高亮自己的评论。
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'post_id' => 'required|integer|min:1',
        ], [
            'post_id.required' => '缺少文章参数',
            'post_id.integer'  => '文章参数不合法',
        ]);

        $post = Post::where('id', (int) $request->input('post_id'))
            ->where('status', Post::STATUS_PUBLISHED)
            ->first();

        if ($post === null) {
            return response()->json([
                'code'    => 40401,
                'message' => '文章不存在或已下架',
                'data'    => null,
            ]);
        }

        $visitorId = $this->visitorId->resolve($request);

        $paginator = PostComment::with('replyTo')
            ->where('post_id', $post->id)
            ->orderByDesc('id')
            ->paginate(self::PAGE_SIZE);

        $items = collect($paginator->items())->map(fn (PostComment $comment) => [
            'id'         => $comment->id,
            'nickname'   => $comment->displayName(),
            'content'    => $comment->content,
            'create_time'=> (string) $comment->create_time,
            'is_mine'    => hash_equals($visitorId, (string) $comment->visitor_id),
            // 昵称链接：有网址点开网页，其次邮箱（mailto），均未填时不可点击
            'website'    => $comment->website ?: '',
            'email'      => $comment->email ?: '',
            // 回复目标（朋友圈式平铺：A 回复 B：内容），普通评论为空
            'reply_to_id'       => $comment->reply_to_id ? (int) $comment->reply_to_id : 0,
            'reply_to_nickname' => $comment->replyTo ? $comment->replyTo->displayName() : '',
            'reply_to_owner'    => $comment->replyTo ? $this->isOwnerNickname($comment->replyTo->nickname) : false,
            // 博主标识：昵称等于博客名称时评论中的昵称高亮展示
            'is_owner' => $this->isOwnerNickname($comment->nickname),
        ])->values()->all();

        return response()->json([
            'code'    => 0,
            'message' => 'success',
            'data'    => [
                'items' => $items,
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * 发表评论
     *
     * 入参 post_id / nickname（必填 ≤50 字）/ content（必填 ≤500 字）/
     * email（选填，合法邮箱）/ website（选填，合法网址）/
     * reply_to_id（选填，回复目标评论 ID，须为同一文章下的评论）；
     * 昵称/邮箱/网址用于记录并支持下次访问自动填充（前端 Cookie 记忆）。
     * 成功返回新建评论数据。
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'post_id'  => 'required|integer|min:1',
            'content'  => 'required|string|max:500',
            'nickname' => 'required|string|max:50',
            'email'    => 'nullable|email|max:100',
            'website'  => ['nullable', 'string', 'max:255', 'regex:/^https?:\/\/.+/i'],
            'reply_to_id' => 'nullable|integer|min:0',
        ], [
            'post_id.required' => '缺少文章参数',
            'post_id.integer'  => '文章参数不合法',
            'content.required' => '请输入评论内容',
            'content.max'      => '评论内容不能超过500字',
            'nickname.required'=> '请填写昵称',
            'nickname.max'     => '昵称不能超过50字',
            'email.email'      => '邮箱格式不正确',
            'email.max'        => '邮箱不能超过100字',
            'website.regex'    => '网址须以 http(s):// 开头',
            'website.max'      => '网址不能超过255字',
            'reply_to_id.integer' => '回复目标不合法',
        ]);

        $post = Post::where('id', (int) $request->input('post_id'))
            ->where('status', Post::STATUS_PUBLISHED)
            ->first();

        if ($post === null) {
            return response()->json([
                'code'    => 40401,
                'message' => '文章不存在或已下架',
                'data'    => null,
            ]);
        }

        $visitorId = $this->visitorId->resolve($request);

        // 回复目标校验：必须是同一文章下的既有评论，否则忽略（降级为普通评论）
        $replyToId = (int) $request->input('reply_to_id', 0);
        if ($replyToId > 0) {
            $replyTo = PostComment::where('id', $replyToId)
                ->where('post_id', $post->id)
                ->first();
            if ($replyTo === null) {
                $replyToId = 0;
            }
        }

        $comment = PostComment::create([
            'post_id'    => $post->id,
            'reply_to_id'=> $replyToId > 0 ? $replyToId : null,
            'visitor_id' => $visitorId,
            'nickname'   => trim((string) $request->input('nickname')),
            'content'    => trim((string) $request->input('content')),
            'email'      => trim((string) $request->input('email', '')) ?: null,
            'website'    => trim((string) $request->input('website', '')) ?: null,
        ]);

        return response()->json([
            'code'    => 0,
            'message' => '评论成功',
            'data'    => [
                'id'          => $comment->id,
                'post_id'     => $comment->post_id,
                'nickname'    => $comment->displayName(),
                'content'     => $comment->content,
                'create_time' => (string) $comment->create_time,
                // 昵称链接：有网址点开网页，其次邮箱（mailto）
                'website'     => $comment->website ?: '',
                'email'       => $comment->email ?: '',
                // 回复目标（朋友圈式平铺：A 回复 B：内容）
                'reply_to_id'       => (int) ($comment->reply_to_id ?? 0),
                'reply_to_nickname' => isset($replyTo) && $replyTo ? $replyTo->displayName() : '',
                'reply_to_owner'    => isset($replyTo) && $replyTo ? $this->isOwnerNickname($replyTo->nickname) : false,
                // 博主标识：昵称等于博客名称时评论中的昵称高亮展示
                'is_owner' => $this->isOwnerNickname($comment->nickname),
            ],
        ]);
    }

    /**
     * 判断评论昵称是否为博主（昵称等于博客名称）
     */
    protected function isOwnerNickname(?string $nickname): bool
    {
        if ($nickname === null) {
            return false;
        }

        $blogName = trim((string) ConfigItem::where('code', 'app_niuren_blog_blog_name')->value('value'));

        return $blogName !== '' && hash_equals($blogName, trim($nickname));
    }
}

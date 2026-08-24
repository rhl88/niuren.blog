<?php

namespace App\Apps\NiurenBlog\Controllers\Web;

use App\Apps\NiurenBlog\Models\Post;
use App\Apps\NiurenBlog\Models\PostLike;
use App\Apps\NiurenBlog\Services\VisitorId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

/**
 * 朋友圈博客点赞 API 控制器
 *
 * 无登录体系，访客身份由 VisitorId 三级策略解析：
 * 请求头 X-Visitor-Id → Cookie nr_visitor → 服务端生成新指纹（自动下发 Cookie）。
 * 点赞记录以 (post_id, visitor_id) 唯一约束兜底，并发重复点赞由数据库唯一键拦截。
 */
class LikeApiController extends Controller
{
    public function __construct(protected VisitorId $visitorId)
    {
    }

    /**
     * 点赞（仅一次，不可取消）
     *
     * 入参 post_id；返回 { liked: bool, count: int }。
     * 入参 nickname（选填）：点赞方昵称（已通过密码验证的博主传博客名称，游客可传自设昵称），
     * 用于点赞列表展示；未传或为空则前端回退「访客」。
     * 点赞仅允许一次：未赞则新增，已赞幂等返回（不提供取消）；文章不存在或未发布返回错误。
     */
    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'post_id' => 'required|integer|min:1',
            'nickname' => 'nullable|string|max:50',
        ], [
            'post_id.required' => '缺少文章参数',
            'post_id.integer'  => '文章参数不合法',
            'nickname.max'     => '昵称不能超过50字',
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

        // 未赞则新增；并发场景由 uk_post_visitor 唯一索引兜底，冲突视为已赞
        try {
            PostLike::firstOrCreate([
                'post_id'    => $post->id,
                'visitor_id' => $visitorId,
                'nickname'   => trim((string) $request->input('nickname', '')),
            ]);
        } catch (Throwable $e) {
            // 唯一键冲突：并发重复点击，保持已赞状态即可
        }

        return response()->json([
            'code'    => 0,
            'message' => '已点赞',
            'data'    => [
                'liked'  => true,
                'count'  => $this->countLikes($post->id),
                // 点赞人昵称列表（按点赞时间升序），前端展示「💗 路人、小明、小王」
                'likers' => $this->likerNicknames($post->id),
            ],
        ]);
    }

    /**
     * 查询指定文章的点赞总数与当前访客点赞状态
     */
    public function status(Request $request, int $postId): JsonResponse
    {
        $post = Post::where('id', $postId)
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

        return response()->json([
            'code'    => 0,
            'message' => 'success',
            'data'    => [
                'liked' => $post->likes()->where('visitor_id', $visitorId)->exists(),
                'count' => $this->countLikes($postId),
            ],
        ]);
    }

    /**
     * 点赞总数（基于唯一索引，直接 count 即为去重后数量）
     */
    protected function countLikes(int $postId): int
    {
        return PostLike::where('post_id', $postId)->count();
    }

    /**
     * 点赞人昵称列表（按点赞时间升序）
     */
    protected function likerNicknames(int $postId): array
    {
        return PostLike::where('post_id', $postId)
            ->orderBy('create_time')
            ->pluck('nickname')
            ->map(fn ($nickname) => trim((string) $nickname) ?: '访客')
            ->values()
            ->all();
    }
}

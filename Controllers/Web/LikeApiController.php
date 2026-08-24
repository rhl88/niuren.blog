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
     * 点赞 / 取消点赞（幂等切换）
     *
     * 入参 post_id；返回 { liked: bool, count: int }。
     * 入参 nickname（选填）：点赞方昵称（已通过密码验证的博主传博客名称，游客可传自设昵称），
     * 用于点赞列表展示；未传或为空则前端回退「访客」。
     * 已赞则取消，未赞则新增；文章不存在或未发布返回错误。
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

        $exists = PostLike::where('post_id', $post->id)
            ->where('visitor_id', $visitorId)
            ->exists();

        if ($exists) {
            // 已赞 → 取消点赞
            PostLike::where('post_id', $post->id)
                ->where('visitor_id', $visitorId)
                ->delete();
            $liked = false;
        } else {
            // 未赞 → 新增；并发场景由 uk_post_visitor 唯一索引兜底，冲突视为已赞
            try {
                PostLike::firstOrCreate([
                    'post_id'    => $post->id,
                    'visitor_id' => $visitorId,
                    'nickname'   => trim((string) $request->input('nickname', '')),
                ]);
            } catch (Throwable $e) {
                // 唯一键冲突：并发重复点击，保持已赞状态即可
            }
            $liked = true;
        }

        return response()->json([
            'code'    => 0,
            'message' => $liked ? '已点赞' : '已取消',
            'data'    => [
                'liked' => $liked,
                'count' => $this->countLikes($post->id),
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
}

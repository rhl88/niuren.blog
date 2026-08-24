<?php

namespace App\Apps\NiurenBlog\Controllers\Admin\Api;

use App\Apps\NiurenBlog\Models\Post;
use App\Apps\NiurenBlog\Services\PostService;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

/**
 * 后台文章管理 API
 */
class PostApiController
{
    public function __construct(protected PostService $postService) {}

    /**
     * 文章分页列表（支持 keyword 内容模糊、status 状态筛选）
     */
    public function list(Request $request): array
    {
        $this->requirePermission('niuren.blog.manage');

        $paginator = $this->postService->listForAdmin(
            $request->only(['keyword', 'status']),
            (int) $request->query('page', 1),
            (int) $request->query('pageSize', 10)
        );

        return ApiResponse::success([
            'list' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'pageSize' => $paginator->perPage(),
        ]);
    }

    /**
     * 新建文章
     */
    public function save(Request $request): array
    {
        $this->requirePermission('niuren.blog.post.create');

        $post = $this->postService->create($this->validated($request));

        return ApiResponse::success($post, '保存成功');
    }

    /**
     * 更新文章
     */
    public function update(Request $request, int $id): array
    {
        $this->requirePermission('niuren.blog.post.edit');

        $post = Post::findOrFail($id);
        $post = $this->postService->update($post, $this->validated($request));

        return ApiResponse::success($post, '保存成功');
    }

    /**
     * 删除文章
     */
    public function delete(int $id): array
    {
        $this->requirePermission('niuren.blog.post.delete');

        $post = Post::findOrFail($id);
        $this->postService->delete($post);

        return ApiResponse::success(null, '删除成功');
    }

    /**
     * 保存/更新共用验证规则
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'required_without:images|string',
            'images' => 'nullable|array|max:9',
            'images.*' => 'string',
            'status' => 'required|integer|in:0,1',
        ]);

        // 未携带 images 字段时显式置为空数组，确保编辑页“删光图片”能保存生效
        $data['images'] = $data['images'] ?? [];

        return $data;
    }

    /**
     * 校验当前管理员是否具备指定权限
     *
     * 后端校验是安全兜底，前端 data-permission 隐藏按钮只是体验优化。
     * 系统 AdminUser::hasPermission() 内部已对 super_admin 角色直接放行。
     *
     * @param string $code 权限码，如 niuren.blog.post.create
     * @throws \Illuminate\Auth\Access\AuthorizationException 无权限时抛出
     */
    protected function requirePermission(string $code): void
    {
        $user = auth('admin')->user();

        if ($user === null || ! $user->hasPermission($code)) {
            abort(403, '无访问权限');
        }
    }
}

<?php

namespace App\Apps\NiurenBlog\Controllers\Web;

use App\Apps\NiurenBlog\Controllers\Web\BlogController;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;


/**
 * 朋友圈博客图片上传控制器
 *
 * 内部调用系统 AttachmentService::upload() 复用系统附件上传能力
 * （扩展名校验、大小限制、哈希去重、文件存储、Attachment 记录），
 * 并传入应用 ID 使文件落盘到 public/uploads/niuren.blog/{Y/m/d}/ 应用隔离目录。
 *
 * 权限：与发布同口径——后台登录、或已通过发布密码验证、或未配置密码方可上传；
 * 防止未验证访客通过审查元素移除前端遮罩后直接调用本端点。
 * 单文件上传、逐张调用，前端负责九张上限拦截。
 */
class UploadApiController extends Controller
{
    /**
     * 上传图片
     *
     * 接收 image 字段（单文件），调用系统 AttachmentService 上传，
     * 返回 { url, path } 结构供前端拼装 images 数组。
     */
    public function uploadImage(Request $request)
    {
        // 发布权限校验（与写动态页/发布接口同一口径）
        if (! app(BlogController::class)->canPublish($request)) {
            return response()->json([
                'code'    => 40301,
                'message' => '请先输入发布密码',
                'data'    => null,
            ]);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp|mimetypes:image/jpeg,image/png,image/gif,image/webp|max:5120',
        ], [
            'image.required' => '请选择要上传的图片',
            'image.image'    => '上传的文件必须是图片',
            'image.mimes'    => '仅支持 JPEG、PNG、GIF、WebP 格式',
            'image.mimetypes'=> '文件内容与扩展名不匹配，请上传真实的图片文件',
            'image.max'      => '图片大小不能超过5MB',
        ]);

        $file = $request->file('image');

        // 调用系统附件服务上传（含扩展名/大小校验、哈希去重、文件存储、Attachment 记录创建）
        // 第二参数 groupId 置 null，第三参数 appId 指定本应用，实现存储目录按应用隔离
        // 第四参数 trace 明确渠道/IP/UA，保证游客(公共)上传也满足附件记录规范
        $result = app(AttachmentService::class)->upload($file, null, 'niuren.blog', [
            'channel' => 'blog',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if (($result['code'] ?? 1) !== 0) {
            return response()->json([
                'code'    => $result['code'] ?? 50001,
                'message' => $result['message'] ?? '上传失败',
                'data'    => null,
            ]);
        }

        $attachment = $result['data'];

        return response()->json([
            'code'    => 0,
            'message' => '上传成功',
            'data'    => [
                'url'  => $attachment->url,
                'path' => $attachment->path,
            ],
        ]);
    }
}

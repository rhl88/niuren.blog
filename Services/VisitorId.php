<?php

namespace App\Apps\NiurenBlog\Services;

use Illuminate\Http\Request;

/**
 * 访客指纹服务
 *
 * 朋友圈博客无登录体系，点赞/评论归属依赖「访客指纹」：
 * 1. 优先取请求头 X-Visitor-Id（前端 localStorage 持久化，首次由服务端下发）；
 * 2. 其次取 Cookie（nr_visitor），兼容未携带请求头的场景；
 * 3. 均不存在时生成新指纹并写入响应 Cookie。
 */
class VisitorId
{
    /** Cookie 名称 */
    public const COOKIE_NAME = 'nr_visitor';

    /** 请求头名称 */
    public const HEADER_NAME = 'X-Visitor-Id';

    /**
     * 解析当前访客指纹；不存在时生成新值
     */
    public function resolve(Request $request): string
    {
        $visitorId = trim((string) $request->header(self::HEADER_NAME, ''));

        if ($visitorId === '') {
            $visitorId = trim((string) $request->cookie(self::COOKIE_NAME, ''));
        }

        if ($visitorId === '' || !preg_match('/^[A-Za-z0-9_-]{8,64}$/', $visitorId)) {
            $visitorId = $this->generate();
            // 新建指纹时同步写入响应 Cookie，浏览器后续请求自动携带
            $this->queueCookie($visitorId);
        }

        return $visitorId;
    }

    /**
     * 将指纹写入响应 Cookie（仅在新建指纹时调用）
     */
    public function queueCookie(string $visitorId): void
    {
        cookie()->queue(
            // 末参 httpOnly=false：前端 JS 需读取 Cookie 镜像到 localStorage
            cookie(self::COOKIE_NAME, $visitorId, 60 * 24 * 365, '/', null, false, false)
        );
    }

    /**
     * 生成新指纹：随机字符串，满足 [A-Za-z0-9_-]{8,64}
     */
    protected function generate(): string
    {
        return 'v_' . bin2hex(random_bytes(16));
    }
}

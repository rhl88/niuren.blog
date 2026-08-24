<?php

namespace App\Apps\NiurenBlog\Controllers\Admin\Api;

use App\Apps\NiurenBlog\Controllers\Admin\SettingController;
use App\Models\ConfigItem;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 博客设置保存 API
 *
 * 安全约束：仅允许更新本应用配置项（code 前缀 app_niuren_blog_ 且属于本应用配置组），
 * 防止越权修改其他应用/系统配置。
 *
 * 访问设置：access_mode 为单选下拉（root 根路径 / path 路径前缀 / domain 子域名绑定，三选一），
 * 保存时按所选方式对参数项做条件必填校验；非当前方式的参数值保留不清空，便于来回切换不丢配置。
 */
class SettingApiController
{
    /**
     * 访问方式合法取值（与迁移 / manifest 中 options 保持一致）
     */
    public const ACCESS_MODES = ['root', 'path', 'domain'];

    /**
     * 配置项元信息（name/type 为 config_items 表 NOT NULL 字段，首次创建时必须提供）
     *
     * 取值为代码内固定映射而非用户输入，不引入注入风险；与迁移文件中的定义保持一致。
     */
    protected array $meta = [
        'posts_per_page'     => ['name' => '每页文章数', 'type' => 'number'],
        'access_mode'        => ['name' => '访问方式', 'type' => 'select'],
        'access_path_prefix' => ['name' => '路径前缀', 'type' => 'text'],
        'access_domain'      => ['name' => '绑定子域名', 'type' => 'text'],
        'blog_name'          => ['name' => '博客名称', 'type' => 'text'],
        'blog_avatar'        => ['name' => '博主头像', 'type' => 'text'],
        'blog_bg'            => ['name' => '背景图', 'type' => 'text'],
        'publish_password'   => ['name' => '发布密码', 'type' => 'text'],
    ];

    /**
     * 各配置项基础校验规则
     */
    protected array $rules = [
        'posts_per_page'     => 'required|integer|min:1|max:100',
        'access_mode'        => 'required|in:root,path,domain',
        'access_path_prefix' => ['nullable', 'string', 'max:100', 'regex:/^\/[A-Za-z0-9_\-\/]*$/'],
        'access_domain'      => ['nullable', 'string', 'max:255', 'regex:/^(?=.{1,253}$)(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))+$/'],
        'blog_name'          => ['nullable', 'string', 'max:50'],
        'blog_avatar'        => ['nullable', 'string', 'max:500'],
        'blog_bg'            => ['nullable', 'string', 'max:500'],
        'publish_password'   => ['nullable', 'string', 'max:100'],
    ];

    /**
     * 保存博客设置
     */
    public function save(Request $request): array
    {
        $this->requirePermission('niuren.blog.manage');

        // 参数名归一化：表单原生 name 带 app_niuren_blog_ 前缀（如 app_niuren_blog_posts_per_page），
        // 校验规则用无前缀短名（posts_per_page），此处统一剥离前缀后再校验，两种提交格式均兼容
        $prefix  = SettingController::ITEM_PREFIX;
        $prefixLen = strlen($prefix);
        $data = [];
        foreach ($request->all() as $key => $value) {
            $name = strpos($key, $prefix) === 0 ? substr($key, $prefixLen) : $key;
            $data[$name] = $value;
        }

        $validated = validator($data, $this->rules)->validate();

        // 归一化：路径前缀去除尾部斜杠；子域名转小写
        if (! empty($validated['access_path_prefix'])) {
            $validated['access_path_prefix'] = rtrim($validated['access_path_prefix'], '/');
        }
        $validated['access_domain'] = isset($validated['access_domain'])
            ? strtolower(trim($validated['access_domain']))
            : '';

        // 条件必填：按所选访问方式校验对应参数项，避免「选了方式但参数为空」的脏状态
        if ($validated['access_mode'] === 'path'
            && isset($validated['access_path_prefix'])
            && rtrim($validated['access_path_prefix'], '/') === '') {
            throw ValidationException::withMessages([
                'access_path_prefix' => '访问方式为路径前缀时，必须填写路径前缀',
            ]);
        }
        if ($validated['access_mode'] === 'domain' && $validated['access_domain'] === '') {
            throw ValidationException::withMessages([
                'access_domain' => '访问方式为子域名绑定时，必须填写绑定域名',
            ]);
        }

        $groupId = DB::table('config_groups')
            ->where('code', SettingController::GROUP_CODE)
            ->value('id');

        if ($groupId === null) {
            return ApiResponse::error(50001, '配置组不存在，请重新安装应用');
        }

        foreach ($validated as $name => $value) {
            // 白名单：仅允许本应用配置组内、按前缀精确拼出的 code，防止越权
            $code = SettingController::ITEM_PREFIX . $name;

            $payload = ['value' => (string) ($value ?? '')];

            // 仅当配置项不存在时才补齐 name/type 等 NOT NULL 元信息；
            // 已存在则只更新 value，不覆盖管理端维护的名称与类型
            if (! ConfigItem::query()
                ->where('group_id', $groupId)
                ->where('code', $code)
                ->exists()) {
                $payload += [
                    'name'   => $this->meta[$name]['name'] ?? $name,
                    'type'   => $this->meta[$name]['type'] ?? 'text',
                    'sort'   => 0,
                    'status' => 1,
                ];
            }

            ConfigItem::updateOrCreate(
                [
                    'group_id' => $groupId,
                    'code'     => $code,
                ],
                $payload
            );
        }

        return ApiResponse::success(null, '保存成功');
    }

    /**
     * 校验当前管理员是否具备指定权限
     *
     * 系统对 super_admin 角色直接放行。
     *
     * @param string $code 权限码，如 niuren.blog.manage
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

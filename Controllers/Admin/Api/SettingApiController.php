<?php

namespace App\Apps\NiurenBlog\Controllers\Admin\Api;

use App\Apps\NiurenBlog\Controllers\Admin\SettingController;
use App\Enums\AppStatus;
use App\Http\Responses\ApiResponse;
use App\Models\AppModel;
use App\Models\ConfigItem;
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
        'posts_per_page'     => ['name' => '默认显示数', 'type' => 'number'],
        'theme_mode'         => ['name' => '显示模式', 'type' => 'select'],
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
        'theme_mode'         => 'required|in:light,dark',
        'access_mode'        => 'required|in:root,path,domain',
        'access_path_prefix' => ['nullable', 'string', 'max:100', 'regex:/^\/[A-Za-z0-9_\-\/]*$/'],
        'access_domain'      => ['nullable', 'string', 'max:255', 'regex:/^(?=.{1,253}$)(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))+$/'],
        'blog_name'          => ['nullable', 'string', 'max:50'],
        'blog_avatar'        => ['nullable', 'string', 'max:500'],
        'blog_bg'            => ['nullable', 'string', 'max:500'],
        'publish_password'   => ['nullable', 'string', 'max:100'],
    ];

    /**
     * 访问设置冲突预检（保存前实时检测）
     *
     * 依据提交的 access_mode / access_path_prefix 运行与 save 相同的占用检测，
     * 供设置页在切换访问方式或修改前缀时即时展示冲突提示，不落库。
     */
    public function check(Request $request): array
    {
        $this->requirePermission('niuren.blog.manage');

        $mode = (string) $request->input('access_mode', '');
        if (! in_array($mode, self::ACCESS_MODES, true)) {
            return ApiResponse::error(40001, '访问方式参数不合法');
        }

        $prefix = trim((string) $request->input('access_path_prefix', ''), '/');

        if ($mode === 'root') {
            $conflict = $this->findRootPathConflict();
        } elseif ($mode === 'path' && $prefix !== '') {
            $conflict = $this->findPathPrefixConflict('/' . $prefix);
        } else {
            $conflict = null;
        }

        if ($conflict !== null) {
            return ApiResponse::error(40002, $conflict['message'], ['conflicts' => $conflict['paths']]);
        }

        return ApiResponse::success(['ok' => true], '当前访问配置无冲突');
    }

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

        // 根路径模式占用检测：已有其他启用应用占用根路径路由时拒绝保存
        if ($validated['access_mode'] === 'root') {
            $conflict = $this->findRootPathConflict();
            if ($conflict !== null) {
                return ApiResponse::error(40002, $conflict['message'], ['conflicts' => $conflict['paths']]);
            }
        }

        // 路径前缀模式占用检测：前缀与系统保留路径或其他启用应用的路由/前缀冲突时拒绝保存
        if ($validated['access_mode'] === 'path'
            && isset($validated['access_path_prefix'])
            && $validated['access_path_prefix'] !== '') {
            $conflict = $this->findPathPrefixConflict($validated['access_path_prefix']);
            if ($conflict !== null) {
                return ApiResponse::error(40002, $conflict['message'], ['conflicts' => $conflict['paths']]);
            }
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
     * 根路径模式占用检测
     *
     * root 模式下本应用会在站点根路径 / 注册前台路由（清单与 Routes/web.php 一致），
     * 若已有其他「已启用」应用通过无前缀路由占用相同根路径，将产生路由覆盖冲突
     * （安装时 manifest home_routes 检测无法拦截，因声明路径与 root 实际路径不一致）。
     *
     * @return array{message: string, paths: array<int, array<string, string>>}|null
     *         无冲突返回 null；有冲突返回提示信息与冲突路径明细
     */
    protected function findRootPathConflict(): ?array
    {
        // 本应用 root 模式下会注册的前台路由（与 Routes/web.php 保持一致）
        $myRoutes = [
            '/'                => '朋友圈动态流',
            '/write'           => '写动态',
            '/publish'         => '发布动态',
            '/verify-password' => '发布密码验证',
            '/upload'          => '图片上传',
            '/like'            => '点赞',
            '/comments'        => '评论',
            '/posts/{id}'      => '删除动态',
            '/{id}'            => '动态详情',
        ];

        $conflicts = [];
        foreach ($this->enabledApps() as $app) {
            $otherRoutes = ($app->manifest ?? [])['home_routes'] ?? [];
            foreach ($myRoutes as $path => $title) {
                if (isset($otherRoutes[$path])) {
                    $conflicts[] = [
                        'path'  => $path,
                        'app'   => $app->name ?: $app->app_id,
                        'title' => (string) $otherRoutes[$path],
                    ];
                }
            }
        }

        return $this->buildConflictResult($conflicts, '根路径模式与已启用应用冲突，无法保存');
    }

    /**
     * 路径前缀模式占用检测
     *
     * 检测三个维度：
     * 1. 前缀与系统保留路径冲突（admin / user / api / apps 等）
     * 2. 其他已启用应用 manifest home_routes 声明的路径落在本前缀之下
     * 3. 其他已启用应用（path 模式）配置的路径前缀与本前缀重合或互为前缀
     *
     * @param string $prefix 本次提交的路径前缀（如 /blog）
     * @return array{message: string, paths: array<int, array<string, string>>}|null
     */
    protected function findPathPrefixConflict(string $prefix): ?array
    {
        $prefix = '/' . trim($prefix, '/');
        $conflicts = [];

        // 1) 系统保留路径
        $reservedMap = [
            '/admin' => '后台管理',
            '/user'  => '用户中心',
            '/api'   => '系统 API',
            '/apps'  => '应用静态资源',
        ];
        if (isset($reservedMap[$prefix])) {
            $conflicts[] = [
                'path'  => $prefix,
                'app'   => '系统',
                'title' => $reservedMap[$prefix] . '（保留路径）',
            ];
        }

        foreach ($this->enabledApps() as $app) {
            // 2) 其他应用声明的路由路径落在本前缀之下
            $otherRoutes = ($app->manifest ?? [])['home_routes'] ?? [];
            foreach ($otherRoutes as $path => $title) {
                $path = '/' . trim((string) $path, '/');
                if ($this->pathOverlaps($path, $prefix)) {
                    $conflicts[] = [
                        'path'  => $path,
                        'app'   => $app->name ?: $app->app_id,
                        'title' => (string) $title,
                    ];
                }
            }

            // 3) 其他应用（path 模式）配置的路径前缀重合（兼容 access_path_prefix / access_path 两种命名）
            $appCode = 'app_' . str_replace('.', '_', $app->app_id) . '_';
            if ((string) ConfigItem::where('code', $appCode . 'access_mode')->value('value') === 'path') {
                foreach (['access_path_prefix', 'access_path'] as $key) {
                    $otherPrefix = (string) ConfigItem::where('code', $appCode . $key)->value('value');
                    if ($otherPrefix === '') {
                        continue;
                    }
                    $otherPrefix = '/' . trim($otherPrefix, '/');
                    if ($this->pathOverlaps($otherPrefix, $prefix)) {
                        $conflicts[] = [
                            'path'  => $otherPrefix,
                            'app'   => $app->name ?: $app->app_id,
                            'title' => '路径前缀',
                        ];
                    }
                }
            }
        }

        return $this->buildConflictResult($conflicts, '路径前缀与已启用应用或系统保留路径冲突，无法保存');
    }

    /**
     * 两个路径是否重合或互为前缀（/a 与 /a、/a 与 /a/b 均视为重合）
     */
    protected function pathOverlaps(string $a, string $b): bool
    {
        if ($a === '/' || $b === '/') {
            return $a === $b;
        }

        return $a === $b || str_starts_with($a, $b . '/') || str_starts_with($b, $a . '/');
    }

    /**
     * 已启用应用列表（排除本应用，仅已启用应用的路由才会实际注册）
     *
     * @return array<int, mixed>
     */
    protected function enabledApps(): array
    {
        try {
            return AppModel::query()
                ->where('status', AppStatus::ENABLED)
                ->where('app_id', '!=', 'niuren.blog')
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 汇总冲突明细为返回结构（无冲突返回 null）
     *
     * @param array<int, array<string, string>> $conflicts
     * @return array{message: string, paths: array<int, array<string, string>>}|null
     */
    protected function buildConflictResult(array $conflicts, string $reason): ?array
    {
        if ($conflicts === []) {
            return null;
        }

        $lines = array_map(
            fn ($c) => "路由 {$c['path']} 已被「{$c['app']}」占用（{$c['title']}）",
            $conflicts
        );

        return [
            'message' => $reason . '：' . implode('；', $lines) . '。请调整访问方式或前缀，或先禁用/卸载冲突应用。',
            'paths'   => $conflicts,
        ];
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

<?php

namespace App\Apps\NiurenBlog\Controllers\Admin;

use App\Models\ConfigItem;
use Illuminate\Http\Request;


/**
 * 博客设置页（对应 manifest.json config_groups.blog_settings）
 *
 * 页面使用 Tab 区分「基础设置」与「访问设置」：
 * 访问设置为单选下拉（access_mode）：根路径 / 路径前缀 / 子域名绑定三选一；
 * 非当前方式的参数值保留不清空，便于来回切换不丢配置。
 */
class SettingController
{
    /**
     * 设置组编码（manifest.json 中 blog_settings，安装时自动加 app_niuren_blog_ 前缀落库）
     */
    public const GROUP_CODE = 'app_niuren_blog_blog_settings';

    /**
     * 配置项 code 前缀（安全过滤用，仅允许更新本应用配置项）
     */
    public const ITEM_PREFIX = 'app_niuren_blog_';

    /**
     * 配置项默认值兜底（数据库缺失时自动补齐，保证页面完整可用）
     *
     * @var array<string, array{name: string, value: string, type: string, tips: string, options?: array<int, array{label: string, value: string}>}>
     */
    public const DEFAULTS = [
        'posts_per_page'     => ['name' => '默认显示数',   'value' => '10',   'type' => 'number', 'tips' => '前台评论、日志默认展示数量'],
        'theme_mode'         => [
            'name'    => '显示模式',
            'value'   => 'light',
            'type'    => 'select',
            'tips'    => '前台默认显示模式，访客仍可自行切换并记忆',
            'options' => [
                ['label' => '浅色', 'value' => 'light'],
                ['label' => '深色', 'value' => 'dark'],
            ],
        ],
        'access_mode'        => [
            'name'    => '访问方式',
            'value'   => 'root',
            'type'    => 'select',
            'tips'    => '博客对外提供访问的入口形式，三选一',
            'options' => [
                ['label' => '根路径访问（http://域名/）',        'value' => 'root'],
                ['label' => '路径前缀访问（http://域名/blog）',  'value' => 'path'],
                ['label' => '子域名绑定（如 blog.example.com）', 'value' => 'domain'],
            ],
        ],
        'access_path_prefix'  => ['name' => '路径前缀',       'value' => '/blog','type' => 'text',   'tips' => '访问方式为「路径前缀」时生效，以 / 开头，如 /blog'],
        'access_domain'       => ['name' => '绑定子域名',     'value' => '',     'type' => 'text',   'tips' => '访问方式为「子域名绑定」时生效，如 blog.example.com'],

        // 前台展示相关（基础设置页）
        'blog_name'           => ['name' => '博客名称',       'value' => '朋友圈','type' => 'text',   'tips' => '前台显示的博客名称 / 博主名称'],
        'blog_avatar'         => ['name' => '博主头像',       'value' => '',     'type' => 'text',   'tips' => '前台展示的博主头像（图片地址，可上传）'],
        'blog_bg'             => ['name' => '背景图',         'value' => '',     'type' => 'text',   'tips' => '前台顶部页头背景图（图片地址，可上传）'],
        'publish_password'    => ['name' => '发布密码',       'value' => '',     'type' => 'text',   'tips' => '前台发布动态所需密码，留空表示游客可直接发布'],
    ];

    /**
     * 访问设置包含的配置项（Tab 第二页）
     *
     * @var string[]
     */
    public const ACCESS_ITEMS = ['access_mode', 'access_path_prefix', 'access_domain'];

    public function index(Request $request)
    {
        $rows = ConfigItem::where('group_id', function ($query) {
                $query->select('id')
                    ->from('config_groups')
                    ->where('code', self::GROUP_CODE);
            })
            ->whereIn('code', array_map(fn ($name) => self::ITEM_PREFIX . $name, array_keys(self::DEFAULTS)))
            ->orderBy('id')
            ->get()
            ->keyBy(fn ($row) => substr($row->code, strlen(self::ITEM_PREFIX)));

        $baseItems   = [];
        $accessItems = [];

        foreach (self::DEFAULTS as $name => $default) {
            $row = $rows->get($name);

            $item = (object) [
                'name'    => $row->name ?? $default['name'],
                'code'    => self::ITEM_PREFIX . $name,
                'value'   => $row->value ?? $default['value'],
                'type'    => $row->type ?? $default['type'],
                'tips'    => $row->tips ?? $default['tips'],
                'options' => $default['options'] ?? null,
            ];

            if (in_array($name, self::ACCESS_ITEMS, true)) {
                $accessItems[] = $item;
            } else {
                $baseItems[] = $item;
            }
        }

        return view('niuren.blog::Admin.Setting.index', compact('baseItems', 'accessItems'));
    }
}

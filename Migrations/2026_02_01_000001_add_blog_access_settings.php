<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 配置项 code 前缀（安装时系统自动为 blog_settings 组的 items 加上 app_niuren_blog_ 前缀）
     */
    public const ITEM_PREFIX = 'app_niuren_blog_';

    /**
     * 向「博客设置」配置组插入访问设置相关配置项（幂等：已存在则跳过）
     *
     * 访问方式为单选下拉（access_mode）：根路径 / 路径前缀 / 子域名绑定 三选一；
     * 路径前缀与子域名为对应方式的参数项，仅当前缀 / 域名非空时该方式生效。
     */
    public function up(): void
    {
        $groupId = DB::table('config_groups')
            ->where('code', 'app_niuren_blog_blog_settings')
            ->value('id');

        if ($groupId === null) {
            // 配置组不存在（应用未完整安装），跳过避免脏数据
            return;
        }

        $now = now();

        $items = [
            [
                'name' => '访问方式',
                'code' => self::ITEM_PREFIX . 'access_mode',
                'value' => 'root',
                'type' => 'select',
                'options' => json_encode([
                    ['label' => '根路径', 'value' => 'root'],
                    ['label' => '路径前缀', 'value' => 'path'],
                    ['label' => '子域名绑定', 'value' => 'domain'],
                ], JSON_UNESCAPED_UNICODE),
                'tips' => '博客对外提供访问的方式：根路径（/）、路径前缀（如 /blog）或子域名绑定（如 blog.example.com），三选一',
                'sort' => 1,
                'status' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ],
            [
                'name' => '路径前缀',
                'code' => self::ITEM_PREFIX . 'access_path_prefix',
                'value' => '/blog',
                'type' => 'text',
                'options' => null,
                'tips' => '访问方式为「路径前缀」时生效，以 /{前缀} 形式访问博客（如 /blog）；留空表示不启用该方式',
                'sort' => 2,
                'status' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ],
            [
                'name' => '绑定子域名',
                'code' => self::ITEM_PREFIX . 'access_domain',
                'value' => '',
                'type' => 'text',
                'options' => null,
                'tips' => '访问方式为「子域名绑定」时生效，如 blog.example.com；留空表示不启用该方式',
                'sort' => 3,
                'status' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ],
        ];

        foreach ($items as $item) {
            $exists = DB::table('config_items')
                ->where('group_id', $groupId)
                ->where('code', $item['code'])
                ->exists();

            if (! $exists) {
                DB::table('config_items')->insert($item);
            }
        }
    }

    public function down(): void
    {
        $groupId = DB::table('config_groups')
            ->where('code', 'app_niuren_blog_blog_settings')
            ->value('id');

        if ($groupId === null) {
            return;
        }

        DB::table('config_items')
            ->where('group_id', $groupId)
            ->whereIn('code', [
                self::ITEM_PREFIX . 'access_mode',
                self::ITEM_PREFIX . 'access_path_prefix',
                self::ITEM_PREFIX . 'access_domain',
            ])
            ->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * 新增「显示模式」配置项（theme_mode：浅色 light / 深色 dark，前台默认显示模式）
 * 并同步更新 posts_per_page 的名称与提示文案（每页文章数 → 默认显示数）
 *
 * 幂等设计：配置项存在则跳过插入；文案仅按旧值精确匹配更新，重复执行无副作用。
 */
return new class extends Migration
{
    public const ITEM_PREFIX = 'app_niuren_blog_';

    public function up(): void
    {
        $groupId = DB::table('config_groups')
            ->where('code', 'app_niuren_blog_blog_settings')
            ->value('id');

        if ($groupId === null) {
            return;
        }

        $now = now();

        // 1. 新增 theme_mode 配置项（存在则跳过）
        $themeCode = self::ITEM_PREFIX . 'theme_mode';
        if (! DB::table('config_items')->where('group_id', $groupId)->where('code', $themeCode)->exists()) {
            DB::table('config_items')->insert([
                'group_id'    => $groupId,
                'name'        => '显示模式',
                'code'        => $themeCode,
                'value'       => 'light',
                'type'        => 'select',
                'options'     => json_encode([
                    ['label' => '浅色', 'value' => 'light'],
                    ['label' => '深色', 'value' => 'dark'],
                ], JSON_UNESCAPED_UNICODE),
                'tips'        => '前台默认显示模式，访客仍可自行切换并记忆',
                'sort'        => 0,
                'status'      => 1,
                'create_time' => $now,
                'update_time' => $now,
            ]);
        }

        // 2. 更新 posts_per_page 文案（仅当仍为旧文案时，避免覆盖管理员自定义名称）
        DB::table('config_items')
            ->where('group_id', $groupId)
            ->where('code', self::ITEM_PREFIX . 'posts_per_page')
            ->where('name', '每页文章数')
            ->update(['name' => '默认显示数', 'update_time' => $now]);

        DB::table('config_items')
            ->where('group_id', $groupId)
            ->where('code', self::ITEM_PREFIX . 'posts_per_page')
            ->where('tips', '前台列表每页文章数量')
            ->update(['tips' => '前台评论、日志默认展示数量', 'update_time' => $now]);
    }

    public function down(): void
    {
        $groupId = DB::table('config_groups')
            ->where('code', 'app_niuren_blog_blog_settings')
            ->value('id');

        if ($groupId === null) {
            return;
        }

        // 回滚：移除 theme_mode 配置项，恢复 posts_per_page 旧文案
        DB::table('config_items')
            ->where('group_id', $groupId)
            ->where('code', self::ITEM_PREFIX . 'theme_mode')
            ->delete();

        DB::table('config_items')
            ->where('group_id', $groupId)
            ->where('code', self::ITEM_PREFIX . 'posts_per_page')
            ->where('name', '默认显示数')
            ->update(['name' => '每页文章数', 'tips' => '前台列表每页文章数量', 'update_time' => now()]);
    }
};

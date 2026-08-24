<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 新增前台展示与评论增强相关字段
 *
 * 1. 博客设置组补充配置项：博客名称 / 博主头像 / 背景图 / 发布密码（幂等）
 * 2. app_niuren_blog_post_comments 表补充 email、website 列（评论人邮箱/网址，用于下次自动填充）
 * 3. app_niuren_blog_post_likes 表补充 nickname 列（点赞时访客昵称，用于点赞列表展示）
 */
return new class extends Migration
{
    /** 配置项 code 前缀（与安装时系统自动加前缀规则一致） */
    public const ITEM_PREFIX = 'app_niuren_blog_';

    /**
     * 新增配置项定义（code 后缀 → name/tips）
     */
    private const NEW_ITEMS = [
        'blog_name'        => ['name' => '博客名称', 'value' => '朋友圈', 'tips' => '前台显示的博客名称 / 博主名称'],
        'blog_avatar'      => ['name' => '博主头像', 'value' => '', 'tips' => '前台展示的博主头像（图片地址，可上传）'],
        'blog_bg'          => ['name' => '背景图', 'value' => '', 'tips' => '前台顶部页头背景图（图片地址，可上传）'],
        'publish_password' => ['name' => '发布密码', 'value' => '', 'tips' => '前台发布动态所需密码，留空表示游客可直接发布'],
    ];

    public function up(): void
    {
        $this->addConfigItems();

        if (Schema::hasTable('app_niuren_blog_post_comments')) {
            Schema::table('app_niuren_blog_post_comments', function ($table) {
                if (! Schema::hasColumn('app_niuren_blog_post_comments', 'email')) {
                    $table->string('email', 100)->nullable()->comment('评论人邮箱');
                }
                if (! Schema::hasColumn('app_niuren_blog_post_comments', 'website')) {
                    $table->string('website', 255)->nullable()->comment('评论人网址');
                }
            });
        }

        if (Schema::hasTable('app_niuren_blog_post_likes')) {
            Schema::table('app_niuren_blog_post_likes', function ($table) {
                if (! Schema::hasColumn('app_niuren_blog_post_likes', 'nickname')) {
                    $table->string('nickname', 50)->nullable()->comment('点赞方昵称（博主/访客评论昵称，空则按"访客"展示）');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('app_niuren_blog_post_comments')) {
            Schema::table('app_niuren_blog_post_comments', function ($table) {
                if (Schema::hasColumn('app_niuren_blog_post_comments', 'email')) {
                    $table->dropColumn('email');
                }
                if (Schema::hasColumn('app_niuren_blog_post_comments', 'website')) {
                    $table->dropColumn('website');
                }
            });
        }

        if (Schema::hasTable('app_niuren_blog_post_likes')) {
            Schema::table('app_niuren_blog_post_likes', function ($table) {
                if (Schema::hasColumn('app_niuren_blog_post_likes', 'nickname')) {
                    $table->dropColumn('nickname');
                }
            });
        }

        $this->removeConfigItems();
    }

    /**
     * 向「博客设置」配置组补充新增配置项（幂等：已存在则跳过）
     */
    protected function addConfigItems(): void
    {
        $groupId = DB::table('config_groups')
            ->where('code', 'app_niuren_blog_blog_settings')
            ->value('id');

        if ($groupId === null) {
            return;
        }

        $now = now();
        foreach (self::NEW_ITEMS as $suffix => $item) {
            $code = self::ITEM_PREFIX . $suffix;
            $exists = DB::table('config_items')
                ->where('group_id', $groupId)
                ->where('code', $code)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('config_items')->insert([
                'group_id'    => $groupId,
                'name'        => $item['name'],
                'code'        => $code,
                'value'       => $item['value'],
                'type'        => 'text',
                'options'     => null,
                'tips'        => $item['tips'],
                'sort'        => 0,
                'status'      => 1,
                'create_time' => $now,
                'update_time' => $now,
            ]);
        }
    }

    /**
     * 移除本次新增配置项
     */
    protected function removeConfigItems(): void
    {
        $groupId = DB::table('config_groups')
            ->where('code', 'app_niuren_blog_blog_settings')
            ->value('id');

        if ($groupId === null) {
            return;
        }

        DB::table('config_items')
            ->where('group_id', $groupId)
            ->whereIn('code', array_map(fn ($k) => self::ITEM_PREFIX . $k, array_keys(self::NEW_ITEMS)))
            ->delete();
    }
};

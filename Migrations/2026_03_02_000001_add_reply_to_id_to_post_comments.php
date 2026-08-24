<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 朋友圈式评论回复：app_niuren_blog_post_comments 补充 reply_to_id 列
 *
 * 记录被回复评论的 ID（平铺两层结构，同朋友圈：A 回复 B：内容）；
 * nullable，普通评论为 null。幂等可重复执行。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_niuren_blog_post_comments')
            && ! Schema::hasColumn('app_niuren_blog_post_comments', 'reply_to_id')) {
            Schema::table('app_niuren_blog_post_comments', function (Blueprint $table) {
                $table->unsignedBigInteger('reply_to_id')->nullable()->after('post_id')
                    ->comment('被回复评论ID（null=普通评论）');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('app_niuren_blog_post_comments')
            && Schema::hasColumn('app_niuren_blog_post_comments', 'reply_to_id')) {
            Schema::table('app_niuren_blog_post_comments', function (Blueprint $table) {
                $table->dropColumn('reply_to_id');
            });
        }
    }
};

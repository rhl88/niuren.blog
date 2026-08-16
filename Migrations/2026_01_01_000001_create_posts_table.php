<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_niuren_blog_posts')) {
            return;
        }

        Schema::create('app_niuren_blog_posts', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->string('title')->nullable()->comment('标题');
            $table->text('content')->comment('正文');
            $table->text('images')->nullable()->comment('图片JSON数组');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：0-草稿 1-发布');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            $table->index('status');
        });

        // 表注释仅 MySQL 支持（测试环境 sqlite 跳过）
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `app_niuren_blog_posts` COMMENT '朋友圈博客文章表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_niuren_blog_posts');
    }
};

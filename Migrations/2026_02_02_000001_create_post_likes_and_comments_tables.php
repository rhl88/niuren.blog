<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_niuren_blog_post_likes')) {
            Schema::create('app_niuren_blog_post_likes', function (Blueprint $table) {
                $table->bigIncrements('id')->comment('主键ID');
                $table->unsignedBigInteger('post_id')->comment('文章ID');
                $table->string('visitor_id', 64)->comment('访客指纹（cookie）');
                $table->timestamp('create_time')->nullable()->comment('点赞时间');

                // 同一访客对同一文章仅允许一条点赞记录
                $table->unique(['post_id', 'visitor_id'], 'uk_post_visitor');
            });

            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `app_niuren_blog_post_likes` COMMENT '朋友圈博客文章点赞表'");
            }
        }

        if (! Schema::hasTable('app_niuren_blog_post_comments')) {
            Schema::create('app_niuren_blog_post_comments', function (Blueprint $table) {
                $table->bigIncrements('id')->comment('主键ID');
                $table->unsignedBigInteger('post_id')->comment('文章ID');
                $table->string('nickname', 50)->nullable()->comment('评论人昵称（空则显示“访客”）');
                $table->string('content', 500)->comment('评论内容');
                $table->string('visitor_id', 64)->nullable()->comment('访客指纹（cookie）');
                $table->timestamp('create_time')->nullable()->comment('评论时间');

                $table->index(['post_id', 'create_time'], 'idx_post_time');
            });

            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `app_niuren_blog_post_comments` COMMENT '朋友圈博客文章评论表'");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_niuren_blog_post_comments');
        Schema::dropIfExists('app_niuren_blog_post_likes');
    }
};

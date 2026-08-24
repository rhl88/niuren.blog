<?php

namespace App\Apps\NiurenBlog\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 文章点赞模型（同一访客对同一文章仅一条记录，uk_post_visitor 唯一约束）
 */
class PostLike extends Model
{
    protected $table = 'app_niuren_blog_post_likes';

    public const CREATED_AT = 'create_time';
    public const UPDATED_AT = null;

    protected $fillable = ['post_id', 'visitor_id', 'nickname'];

    protected $casts = [
        'post_id' => 'integer',
        'create_time' => 'datetime:Y-m-d H:i:s',
    ];
}

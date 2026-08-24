<?php

namespace App\Apps\NiurenBlog\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 文章评论模型（无登录体系，昵称可空，空则前端展示「访客」）
 */
class PostComment extends Model
{
    protected $table = 'app_niuren_blog_post_comments';

    public const CREATED_AT = 'create_time';
    public const UPDATED_AT = null;

    protected $fillable = ['post_id', 'reply_to_id', 'nickname', 'content', 'visitor_id', 'email', 'website'];

    protected $casts = [
        'post_id' => 'integer',
        'create_time' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 展示昵称（空昵称回退为「访客」）
     */
    public function displayName(): string
    {
        $name = trim((string) $this->nickname);

        return $name !== '' ? $name : '访客';
    }

    /**
     * 回复目标评论（朋友圈式平铺：A 回复 B：内容）
     */
    public function replyTo()
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }
}

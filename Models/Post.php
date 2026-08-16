<?php

namespace App\Apps\NiurenBlog\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $table = 'app_niuren_blog_posts';

    public const CREATED_AT = 'create_time';
    public const UPDATED_AT = 'update_time';

    /** 草稿 */
    public const STATUS_DRAFT = 0;
    /** 已发布 */
    public const STATUS_PUBLISHED = 1;

    protected $fillable = [
        'title',
        'content',
        'images',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
        'images' => 'array',
        'create_time' => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s',
    ];
}

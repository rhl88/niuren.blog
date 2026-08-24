<?php

namespace App\Apps\NiurenBlog\Tests\Feature;

use App\Apps\NiurenBlog\Models\Post;
use App\Models\AdminUser;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 朋友圈博客应用级功能测试
 * 覆盖后台 CRUD API、前台发布/列表/详情、未认证拦截
 */
class PostApiTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // 内存库中 apps 表为空，应用路由不会被自动注册，手动注册以启用待测路由
        $this->app->register(\App\Apps\NiurenBlog\ServiceProvider::class);
        $this->createTables();

        // 种子 super_admin 角色，确保权限校验通过
        $this->seed(AdminRoleSeeder::class);

        $this->admin = AdminUser::create([
            'username' => 'niuren_test_' . uniqid(),
            'password' => bcrypt('123456'),
            'status' => 1,
        ]);

        // 赋予 super_admin 角色（role_id=1），使 hasPermission 放行
        DB::table('admin_user_roles')->insert([
            'user_id' => $this->admin->id,
            'role_id' => 1,
            'create_time' => now(),
            'update_time' => now(),
        ]);
    }

    protected function createTables(): void
    {
        // 文章表（含后续点赞/评论表，保证列表/详情附带计数查询可执行）
        if (!Schema::hasTable('app_niuren_blog_posts')) {
            (require base_path('app/Apps/NiurenBlog/Migrations/2026_01_01_000001_create_posts_table.php'))->up();
            (require base_path('app/Apps/NiurenBlog/Migrations/2026_02_02_000001_create_post_likes_and_comments_tables.php'))->up();
        }

        // 评论/点赞扩展字段迁移依赖 config_groups 中的博客设置组，先确保组存在再执行
        $this->ensureBlogConfigGroup();

        (require base_path('app/Apps/NiurenBlog/Migrations/2026_03_01_000001_add_blog_profile_and_comment_meta.php'))->up();
    }

    /**
     * 确保博客设置配置组存在（app_id 归本应用），供扩展字段/访问设置迁移与测试断言使用
     */
    protected function ensureBlogConfigGroup(): void
    {
        $exists = DB::table('config_groups')
            ->where('code', 'app_niuren_blog_blog_settings')
            ->exists();

        if (! $exists) {
            DB::table('config_groups')->insert([
                'name'        => '博客设置',
                'code'        => 'app_niuren_blog_blog_settings',
                'app_id'      => 'niuren.blog',
                'sort'        => 0,
                'status'      => 1,
                'create_time' => now(),
                'update_time' => now(),
            ]);
        }
    }

    private function createPost(array $overrides = []): Post
    {
        return Post::create(array_merge([
            'title' => '测试动态',
            'content' => '内容内容内容',
            'images' => [],
            'status' => Post::STATUS_PUBLISHED,
        ], $overrides));
    }

    public function test_admin_list_returns_paged_data(): void
    {
        $this->createPost(['title' => '第一条', 'status' => Post::STATUS_PUBLISHED]);
        $this->createPost(['title' => '第二条', 'status' => Post::STATUS_DRAFT]);

        $this->actingAs($this->admin, 'admin')
            ->getJson('/api/admin/niuren/blog/posts/list?page=1&pageSize=10')
            ->assertOk()
            ->assertJson(['code' => 0])
            ->assertJsonPath('data.total', 2)
            ->assertJsonCount(2, 'data.list');
    }

    public function test_admin_list_filters_by_keyword_and_status(): void
    {
        $this->createPost(['title' => '旅游日记', 'content' => '今天去海边旅游了', 'status' => Post::STATUS_PUBLISHED]);
        $this->createPost(['title' => '工作周报', 'content' => '本周工作日报汇总', 'status' => Post::STATUS_DRAFT]);

        // 内容关键词筛选
        $this->actingAs($this->admin, 'admin')
            ->getJson('/api/admin/niuren/blog/posts/list?keyword=旅游')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.title', '旅游日记');

        // 状态筛选
        $this->actingAs($this->admin, 'admin')
            ->getJson('/api/admin/niuren/blog/posts/list?status=0')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.title', '工作周报');
    }

    public function test_admin_create_post(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->postJson('/api/admin/niuren/blog/posts/save', [
                'title' => '新动态',
                'content' => '通过接口创建的内容',
                'status' => 1,
            ])
            ->assertOk()
            ->assertJson(['code' => 0]);

        $this->assertDatabaseHas('app_niuren_blog_posts', [
            'title' => '新动态',
            'status' => 1,
        ]);
    }

    public function test_admin_create_validation_fails_without_content(): void
    {
        // 系统统一响应格式：ValidationException → {code: 40201, message, data: {字段: [错误]}}，HTTP 422
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/admin/niuren/blog/posts/save', [
                'title' => '缺正文',
                'status' => 1,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('code', 40201)
            ->assertJsonPath('message', '请求参数不合法');

        $this->assertArrayHasKey(
            'content',
            $response->json('data'),
            '校验错误应包含 content 字段'
        );
    }

    public function test_admin_update_post(): void
    {
        $post = $this->createPost(['title' => '旧标题']);

        $this->actingAs($this->admin, 'admin')
            ->putJson('/api/admin/niuren/blog/posts/' . $post->id, [
                'title' => '新标题',
                'content' => $post->content,
                'status' => 0,
            ])
            ->assertOk()
            ->assertJson(['code' => 0]);

        $this->assertDatabaseHas('app_niuren_blog_posts', [
            'id' => $post->id,
            'title' => '新标题',
            'status' => 0,
        ]);
    }

    public function test_admin_delete_post(): void
    {
        $post = $this->createPost();

        $this->actingAs($this->admin, 'admin')
            ->deleteJson('/api/admin/niuren/blog/posts/' . $post->id)
            ->assertOk()
            ->assertJson(['code' => 0]);

        $this->assertDatabaseMissing('app_niuren_blog_posts', ['id' => $post->id]);
    }

    public function test_web_publish_creates_published_post(): void
    {
        $this->postJson('/blog/publish', [
            'content' => '前台发布的第一条动态',
        ])
            ->assertOk()
            ->assertJson(['code' => 0]);

        $this->assertDatabaseHas('app_niuren_blog_posts', [
            'content' => '前台发布的第一条动态',
            'status' => Post::STATUS_PUBLISHED,
        ]);
    }

    public function test_web_index_only_shows_published(): void
    {
        $this->createPost(['title' => '已发布', 'status' => Post::STATUS_PUBLISHED]);
        $this->createPost(['title' => '草稿', 'status' => Post::STATUS_DRAFT]);

        $response = $this->get('/blog');

        $response->assertOk()
            ->assertSee('已发布')
            ->assertDontSee('草稿');
    }

    public function test_web_show_returns_404_page_for_draft(): void
    {
        $draft = $this->createPost(['title' => '不可见草稿', 'content' => '草稿内容', 'status' => Post::STATUS_DRAFT]);

        // 系统前台 404 页面按设计返回 HTTP 200 + errors.404 视图
        $this->get('/blog/' . $draft->id)
            ->assertOk()
            ->assertViewIs('errors.404')
            ->assertDontSee('草稿内容');
    }

    public function test_web_show_renders_published_post_detail(): void
    {
        $post = $this->createPost(['title' => '详情标题', 'content' => '详情正文内容', 'status' => Post::STATUS_PUBLISHED]);

        $response = $this->get('/blog/' . $post->id);

        $response->assertOk()
            ->assertViewIs('niuren.blog::Web.show')
            ->assertSee('详情标题')
            ->assertSee('详情正文内容');
    }

    public function test_web_write_page_is_accessible(): void
    {
        // 验证静态路由不被 /{id} 动态路由吞掉（whereNumber 约束生效）
        $this->get('/blog/write')->assertOk();
    }

    public function test_admin_api_requires_authentication(): void
    {
        $this->getJson('/api/admin/niuren/blog/posts/list')->assertStatus(401);
    }

    public function test_web_comment_stores_email_and_website(): void
    {
        $post = $this->createPost(['status' => Post::STATUS_PUBLISHED]);

        $this->postJson('/blog/comments', [
            'post_id'  => $post->id,
            'nickname' => '测试访客',
            'content'  => '带邮箱和网址的评论',
            'email'    => 'visitor@example.com',
            'website'  => 'https://example.com',
        ])->assertOk()
          ->assertJson(['code' => 0])
          ->assertJsonPath('data.nickname', '测试访客');

        $this->assertDatabaseHas('app_niuren_blog_post_comments', [
            'post_id'  => $post->id,
            'nickname' => '测试访客',
            'email'    => 'visitor@example.com',
            'website'  => 'https://example.com',
        ]);
    }

    public function test_web_comment_requires_nickname(): void
    {
        $post = $this->createPost(['status' => Post::STATUS_PUBLISHED]);

        $this->postJson('/blog/comments', [
            'post_id' => $post->id,
            'content' => '缺昵称的评论',
        ])->assertUnprocessable()
          ->assertJson(['code' => 40201])
          ->assertJsonPath('data.nickname.0', '请填写昵称');
    }

    public function test_web_comment_rejects_invalid_email(): void
    {
        $post = $this->createPost(['status' => Post::STATUS_PUBLISHED]);

        $this->postJson('/blog/comments', [
            'post_id'  => $post->id,
            'nickname' => '测试',
            'content'  => '非法邮箱',
            'email'    => 'not-an-email',
        ])->assertUnprocessable()
          ->assertJson(['code' => 40201])
          ->assertJsonPath('data.email.0', '邮箱格式不正确');
    }

    public function test_web_like_stores_nickname(): void
    {
        $post = $this->createPost(['status' => Post::STATUS_PUBLISHED]);

        $this->postJson('/blog/like', [
            'post_id'  => $post->id,
            'nickname' => '点赞访客',
        ])->assertOk()
          ->assertJson(['code' => 0])
          ->assertJsonPath('data.liked', true)
          ->assertJsonPath('data.count', 1);

        $this->assertDatabaseHas('app_niuren_blog_post_likes', [
            'post_id'  => $post->id,
            'nickname' => '点赞访客',
        ]);
    }

    public function test_web_publish_requires_password_when_configured(): void
    {
        // 配置发布密码
        $this->seedConfig('publish_password', 'secret123');

        $this->postJson('/blog/publish', [
            'content' => '未验证密码的发布',
        ])->assertUnprocessable()
          ->assertJson(['code' => 40201])
          ->assertJsonPath('data.password.0', '发布密码不正确');

        // 携带正确密码可发布
        $this->postJson('/blog/publish', [
            'content'  => '验证密码后的发布',
            'password' => 'secret123',
        ])->assertOk()
          ->assertJson(['code' => 0]);

        $this->assertDatabaseHas('app_niuren_blog_posts', [
            'content' => '验证密码后的发布',
            'status'  => Post::STATUS_PUBLISHED,
        ]);
    }

    public function test_web_verify_password_issues_cookie(): void
    {
        $this->seedConfig('publish_password', 'secret123');

        $this->postJson('/blog/verify-password', [
            'password' => 'secret123',
        ])->assertOk()
          ->assertJson(['code' => 0])
          ->assertCookie('nr_blog_pwd', '1');
    }

    public function test_admin_setting_save_persists_profile(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->postJson('/api/admin/niuren/blog/setting/save', [
                'app_niuren_blog_blog_name' => '我的博客',
                'app_niuren_blog_blog_avatar' => '/uploads/niuren.blog/2026/08/24/avatar.png',
                'app_niuren_blog_blog_bg' => '/uploads/niuren.blog/2026/08/24/bg.png',
                'app_niuren_blog_publish_password' => 'pwd123',
                'app_niuren_blog_posts_per_page' => 10,
                'app_niuren_blog_access_mode' => 'root',
            ])->assertOk()
            ->assertJson(['code' => 0]);

        $this->assertDatabaseHas('config_items', [
            'code'  => 'app_niuren_blog_blog_name',
            'value' => '我的博客',
        ]);
        $this->assertDatabaseHas('config_items', [
            'code'  => 'app_niuren_blog_blog_avatar',
            'value' => '/uploads/niuren.blog/2026/08/24/avatar.png',
        ]);
        $this->assertDatabaseHas('config_items', [
            'code'  => 'app_niuren_blog_blog_bg',
            'value' => '/uploads/niuren.blog/2026/08/24/bg.png',
        ]);
    }

    /**
     * 向博客设置组写入配置项（测试用）
     */
    protected function seedConfig(string $suffix, string $value): void
    {
        $groupId = DB::table('config_groups')
            ->where('code', 'app_niuren_blog_blog_settings')
            ->value('id');

        if ($groupId === null) {
            return;
        }

        DB::table('config_items')->updateOrInsert(
            [
                'group_id' => $groupId,
                'code'     => 'app_niuren_blog_' . $suffix,
            ],
            [
                'name'        => $suffix,
                'value'       => $value,
                'type'        => 'text',
                'options'     => null,
                'tips'        => '',
                'sort'        => 0,
                'status'      => 1,
                'create_time' => now(),
                'update_time' => now(),
            ]
        );
    }
}

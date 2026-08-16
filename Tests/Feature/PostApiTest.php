<?php

namespace App\Apps\NiurenBlog\Tests\Feature;

use App\Apps\NiurenBlog\Models\Post;
use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->admin = AdminUser::create([
            'username' => 'niuren_test_' . uniqid(),
            'password' => bcrypt('123456'),
            'status' => 1,
        ]);
    }

    protected function createTables(): void
    {
        if (!Schema::hasTable('app_niuren_blog_posts')) {
            (require base_path('app/Apps/NiurenBlog/Migrations/2026_01_01_000001_create_posts_table.php'))->up();
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
        $this->createPost(['title' => '旅游日记', 'status' => Post::STATUS_PUBLISHED]);
        $this->createPost(['title' => '工作周报', 'status' => Post::STATUS_DRAFT]);

        // 标题关键词筛选
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

    public function test_web_write_page_is_accessible(): void
    {
        // 验证静态路由不被 /{id} 动态路由吞掉（whereNumber 约束生效）
        $this->get('/blog/write')->assertOk();
    }

    public function test_admin_api_requires_authentication(): void
    {
        $this->getJson('/api/admin/niuren/blog/posts/list')->assertStatus(401);
    }
}

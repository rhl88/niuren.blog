<!DOCTYPE html>
<html lang="zh-CN"@if(($blog['theme_mode'] ?? 'light') === 'dark') data-theme="dark"@endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">
    <title>{{ ($blog['name'] ?? '') . ($post->title ? ' - ' . $post->title : ' - 动态详情') }}</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('apps/niuren.blog/css/style.css') }}?v=1.4.21">
</head>
<body data-page="detail">

<!-- 顶部导航栏 -->
<div class="mom-nav is-solid">
    <a class="mom-nav-back" href="{{ url(($basePath ?? '') . '/') }}" title="返回">
        <i class="fa fa-angle-left"></i>
    </a>
    <h1 class="mom-nav-title">动态详情</h1>
    <div class="mom-nav-actions">
        <button type="button" class="mom-icon-btn mom-theme-toggle" title="切换深浅色" data-action="theme">
            <i class="fa fa-moon-o"></i>
        </button>
    </div>
</div>

<div class="mom-feed">
    <article class="mom-card" data-post-id="{{ $post->id }}">
        <header class="mom-card-head">
            @if(!empty($blog['avatar']))
                <img class="mom-avatar" src="{{ $blog['avatar'] }}" alt="{{ $blog['name'] }}">
            @else
                <span class="mom-avatar">{{ mb_substr($blog['name'] ?? '博', 0, 1) }}</span>
            @endif
            <div class="mom-card-meta">
                <span class="mom-name">{{ $blog['name'] ?? '博主' }}</span>
                @if($post->title)
                    <h1 class="mom-detail-title">{{ $post->title }}</h1>
                @endif
            </div>
        </header>

        <div class="mom-card-body is-expanded">
            @if($post->content)
                <div class="mom-text mom-text-full">{{ $post->content }}</div>
            @endif

            @if(!empty($post->images))
                <div class="mom-grid mom-grid-{{ min(count($post->images), 9) }}"
                     data-count="{{ count($post->images) }}">
                    @foreach($post->images as $img)
                        <div class="mom-grid-cell">
                            <img src="{{ $img }}" alt="" loading="lazy" data-full="{{ $img }}">
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mom-meta">
                <time class="mom-time" data-time="{{ $post->create_time }}">{{ $post->create_time }}</time>
            </div>
        </div>

        <!-- 点赞 + 评论区（灰底气泡） -->
        <div class="mom-praise">
            <div class="mom-like-row{{ in_array($post->id, $likedIds ?? []) ? ' liked' : '' }}">
                <i class="mom-like-icon fa fa-heart-o"></i>
                @if(!empty($likersMap[$post->id] ?? []))
                    <span class="mom-like-nick">{{ implode('、', $likersMap[$post->id]) }}</span>
                @endif
            </div>
            <div class="mom-comment-list" data-post-id="{{ $post->id }}"></div>
        </div>

        <footer class="mom-card-foot">
            <button type="button" class="mom-op mom-comment-open">
                <i class="fa fa-comment-o"></i> 评论
                @if((int)($post->comments_count ?? 0) > 0)
                    <span class="mom-op-num">{{ (int)($post->comments_count ?? 0) }}</span>
                @endif
            </button>
            <button type="button" class="mom-op mom-more" title="赞 / 更多">
                <i class="fa fa-ellipsis-h"></i>
            </button>
        </footer>

        <!-- 赞 / 评论 操作浮层 -->
        <div class="mom-pop" hidden>
            <a href="javascript:;" class="mom-pop-item mom-pop-like">
                <i class="fa fa-heart"></i> 赞
            </a>
            <a href="javascript:;" class="mom-pop-item mom-pop-comment">
                <i class="fa fa-comment-o"></i> 评论
            </a>
            <i class="mom-pop-arrow"></i>
        </div>
    </article>

    <div class="mom-detail-actions">
        <a href="{{ url(($basePath ?? '') . '/') }}" class="mom-back-btn"><i class="fa fa-chevron-left"></i> 返回朋友圈</a>
    </div>
</div>

<!-- 底部评论输入条 -->
<div class="mom-comment-bar" hidden>
    <div class="mom-comment-bar-inner">
        <button type="button" class="mom-comment-close" title="收起评论"><i class="fa fa-close"></i></button>
        <div class="mom-comment-input-row">
            <textarea class="mom-comment-input" maxlength="500" placeholder="评论" rows="2"></textarea>
            <button type="button" class="mom-comment-emoji" title="表情"><i class="fa fa-smile-o"></i></button>
        </div>
        <div class="mom-comment-meta">
            <input type="text" class="mom-comment-nick" maxlength="50" placeholder="昵称(必填)">
            <input type="text" class="mom-comment-email" maxlength="100" placeholder="邮箱(选填)">
            <input type="text" class="mom-comment-website" maxlength="255" placeholder="网址(选填)">
        </div>
        <button type="button" class="mom-comment-send" disabled>发送</button>
    </div>
</div>

<!-- 评论表情面板 -->
<div class="mom-comment-emoji-panel" hidden></div>

<!-- 图片全屏浏览 -->
<div class="mom-viewer" hidden>
    <button type="button" class="mom-viewer-close"><i class="fa fa-close"></i></button>
    <button type="button" class="mom-viewer-prev"><i class="fa fa-chevron-left"></i></button>
    <img src="" alt="" draggable="false">
    <button type="button" class="mom-viewer-next"><i class="fa fa-chevron-right"></i></button>
</div>

<script>
    // 博主昵称（已验证发布密码或后台登录时为博客名称）：评论框昵称自动填充
    window.NR_OWNER_NICKNAME = @json($ownerNickname);
    // 前台基础路径（path 模式为前缀如 /blog，root/domain 模式为空串）
    window.NR_BASE = @json($basePath ?? '');
</script>
<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('apps/niuren.blog/js/app.js') }}?v=1.4.30"></script>
</body>
</html>
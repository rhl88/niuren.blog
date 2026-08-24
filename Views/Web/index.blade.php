<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">
    <title>{{ $blog['name'] }}</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('apps/niuren.blog/css/style.css') }}">
</head>
<body data-page="feed">

<!-- 顶部导航栏：固定悬浮，滚动时背景渐变 -->
<div class="mom-nav">
    <a class="mom-nav-back" href="{{ url('/') }}" title="返回">
        <i class="fa fa-angle-left"></i>
    </a>
    <h1 class="mom-nav-title">{{ $blog['name'] }}</h1>
    <div class="mom-nav-actions">
        <button type="button" class="mom-icon-btn mom-theme-toggle" title="切换深浅色" data-action="theme">
            <i class="fa fa-moon-o"></i>
        </button>
        <a class="mom-icon-btn" href="{{ url('blog/write') }}" title="发布动态">
            <i class="fa fa-camera"></i>
        </a>
    </div>
</div>

<!-- 封面头部区域：封面图 + 右下角用户信息 -->
<div class="mom-cover">
    <div class="mom-cover-bg"@if(!empty($blog['bg'])) style="background-image:url('{{ $blog['bg'] }}');background-size:cover;background-position:center;"@endif></div>
    <div class="mom-cover-user">
        <span class="mom-cover-name">{{ $blog['name'] }}</span>
        @if(!empty($blog['avatar']))
            <span class="mom-cover-avatar"><img src="{{ $blog['avatar'] }}" alt="{{ $blog['name'] }}"></span>
        @else
            <span class="mom-cover-avatar">{{ mb_substr($blog['name'], 0, 1) }}</span>
        @endif
    </div>
</div>

<!-- 动态信息流 -->
<div class="mom-feed">
    @forelse($list as $item)
        <article class="mom-card" data-post-id="{{ $item->id }}">
            <header class="mom-card-head">
                @if(!empty($blog['avatar']))
                    <img class="mom-avatar" src="{{ $blog['avatar'] }}" alt="{{ $blog['name'] }}">
                @else
                    <span class="mom-avatar">{{ mb_substr($blog['name'], 0, 1) }}</span>
                @endif
                <div class="mom-card-meta">
                    <span class="mom-name">{{ $blog['name'] }}</span>
                    @if(!empty($item->title))
                        <h3 class="mom-title">{{ $item->title }}</h3>
                    @endif
                </div>
            </header>

            <div class="mom-card-body">
                @if($item->content)
                    <p class="mom-text">{{ $item->content }}</p>
                @endif

                @if(!empty($item->images))
                    <div class="mom-grid mom-grid-{{ min(count($item->images), 9) }}"
                         data-count="{{ count($item->images) }}">
                        @foreach($item->images as $img)
                            <div class="mom-grid-cell">
                                <img src="{{ $img }}" alt="" loading="lazy" data-full="{{ $img }}">
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mom-meta">
                    <time class="mom-time" data-time="{{ $item->create_time }}">{{ $item->create_time }}</time>
                </div>
            </div>

            <!-- 点赞 + 评论区（灰底气泡） -->
            <div class="mom-praise">
                <div class="mom-like-row{{ in_array($item->id, $likedIds ?? []) ? ' liked' : '' }}">
                    <i class="mom-like-icon fa fa-thumbs-o-up"></i>
                    <span class="mom-like-nick">@if(!empty($likersMap[$item->id] ?? [])){{ implode('、', $likersMap[$item->id]) }}觉得很赞@endif</span>
                    @if((int)($item->likes_count ?? 0) > 0)
                        <span class="mom-like-count">(<span class="like-num">{{ (int)($item->likes_count ?? 0) }}</span>)</span>
                    @endif
                </div>
                <div class="mom-comment-list" data-post-id="{{ $item->id }}"></div>
            </div>

            <footer class="mom-card-foot">
                <button type="button" class="mom-op mom-comment-open">
                    <i class="fa fa-comment-o"></i> 评论
                    @if((int)($item->comments_count ?? 0) > 0)
                        <span class="mom-op-num">{{ (int)($item->comments_count ?? 0) }}</span>
                    @endif
                </button>
                <button type="button" class="mom-op mom-more" title="赞 / 更多">
                    <i class="fa fa-ellipsis-h"></i>
                </button>
            </footer>

            <!-- 赞 / 评论 操作浮层 -->
            <div class="mom-pop" hidden>
                <a href="javascript:;" class="mom-pop-item mom-pop-like">
                    <i class="fa fa-thumbs-up"></i> 赞
                </a>
                <a href="javascript:;" class="mom-pop-item mom-pop-comment">
                    <i class="fa fa-comment-o"></i> 评论
                </a>
                <i class="mom-pop-arrow"></i>
            </div>
        </article>
    @empty
        <div class="mom-empty">
            <p>还没有动态，点击右上角相机发布第一条～</p>
        </div>
    @endforelse
</div>

<!-- 分页加载提示 -->
<div class="mom-pager">
    @if($total > $pageSize)
        <span>共 {{ $total }} 条动态</span>
    @endif
</div>

<!-- 底部评论输入条（点击评论唤起，随软键盘弹起） -->
<div class="mom-comment-bar" hidden>
    <div class="mom-comment-bar-inner">
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

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('apps/niuren.blog/js/app.js') }}"></script>
</body>
</html>
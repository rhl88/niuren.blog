<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $post->title ? $post->title : '文章详情' }}</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('apps/niuren.blog/css/style.css') }}">
</head>
<body>
<div class="blog-wrap">
    <div class="blog-header">
        <a class="pear-btn" href="{{ url('blog') }}">
            <i class="fa fa-arrow-left"></i> 返回列表
        </a>
    </div>

    <div class="blog-item blog-detail">
        <div class="blog-item-header">
            <div class="blog-avatar">博主</div>
            <div class="blog-meta">
                <div class="blog-name">博主</div>
                <div class="blog-time">{{ $post->create_time }}</div>
            </div>
        </div>
        <div class="blog-body">
            @if($post->title)
                <h1>{{ $post->title }}</h1>
            @endif
            <div class="blog-content">{{ $post->content }}</div>
            @if(!empty($post->images))
                <div class="blog-images">
                    @foreach($post->images as $img)
                        <img src="{{ $img }}" alt="">
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('apps/niuren.blog/js/app.js') }}"></script>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>朋友圈博客</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('apps/niuren.blog/css/style.css') }}">
</head>
<body>
<div class="blog-wrap">
    <div class="blog-header">
        <h1>朋友圈博客</h1>
        <a class="pear-btn pear-btn-primary" href="{{ url('blog/write') }}">
            <i class="fa fa-pencil-square-o"></i> 写文章
        </a>
    </div>

    <div class="blog-list">
        @foreach($list as $item)
            <div class="blog-item">
                <div class="blog-item-header">
                    <div class="blog-avatar">博主</div>
                    <div class="blog-meta">
                        <div class="blog-name">博主</div>
                        <div class="blog-time">{{ $item->create_time }}</div>
                    </div>
                </div>
                <div class="blog-body">
                    @if($item->title)
                        <h3>{{ $item->title }}</h3>
                    @endif
                    <p>{{ $item->content }}</p>
                    @if(!empty($item->images))
                        <div class="blog-images">
                            @foreach($item->images as $img)
                                <img src="{{ $img }}" alt="">
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="blog-footer">
                    <a class="blog-link" href="{{ url('blog/'.$item->id) }}">查看详情</a>
                </div>
            </div>
        @endforeach
    </div>

    <div class="blog-pager">
        @if($total > $pageSize)
            <div class="layui-box">
                共 {{ $total }} 条
            </div>
        @endif
    </div>
</div>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('apps/niuren.blog/js/app.js') }}"></script>
</body>
</html>

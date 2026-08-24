<!DOCTYPE html>
<html lang="zh-CN"@if(($blog['theme_mode'] ?? 'light') === 'dark') data-theme="dark"@endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">
    <title>{{ ($blog['name'] ?? '朋友圈') }} - 发布动态</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('apps/niuren.blog/css/style.css') }}?v=1.4.21">
</head>
<body data-page="writer">

<!-- 顶部导航栏 -->
<div class="mom-nav is-solid">
    <a class="mom-nav-back" href="{{ url(($basePath ?? '') . '/') }}" title="取消">
        <i class="fa fa-angle-left"></i>
    </a>
    <h1 class="mom-nav-title">发表动态</h1>
    <div class="mom-nav-actions">
        <button type="button" class="mom-write-post mom-nav-post" disabled>发表</button>
    </div>
</div>

<div class="mom-writer">
    <!-- 正文编辑区 -->
    <div class="mom-writer-content">
        <textarea class="mom-writer-textarea" rows="6" maxlength="2000"
                  placeholder="这一刻的想法..."></textarea>
    </div>

    <!-- 图片预览区：缩略图 + 追加占位 -->
    <div class="mom-writer-images" id="momWriterImages"></div>

    <div class="mom-writer-count"><span id="momWriteCount">0</span>/2000</div>
</div>

<!-- 底部固定输入条热区说明 -->
<div class="mom-writer-tip">支持纯文字或图文动态，图片最多 9 张。</div>

<script>
    // 上传接口返回结构：{ code: 0, data: { url, path } }
    // 前台基础路径（path 模式为前缀如 /blog，root/domain 模式为空串）
    window.NR_BASE = @json($basePath ?? '');
    window.NR_UPLOAD_URL = "{{ url(($basePath ?? '') . '/upload') }}";
    window.NR_PUBLISH_URL = "{{ url(($basePath ?? '') . '/publish') }}";
    window.NR_LIST_URL = "{{ url(($basePath ?? '') . '/') }}";
    window.NR_BLOG_NAME = @json($blog['name'] ?? '朋友圈');
</script>
<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('apps/niuren.blog/js/app.js') }}?v=1.4.30"></script>
</body>
</html>
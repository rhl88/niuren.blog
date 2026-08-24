<!DOCTYPE html>
<html lang="zh-CN"@if(($blog['theme_mode'] ?? 'light') === 'dark') data-theme="dark"@endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">
    <title>{{ ($blog['name'] ?? '朋友圈') }} - 发布验证</title>
    <link rel="stylesheet" href="{{ asset('apps/niuren.blog/css/style.css') }}?v=1.4.21">
</head>
<body data-page="pwd-gate">

{{-- 密码门：独立页面，不包含发布表单与上传逻辑；验证成功后由 JS 跳转写动态页 --}}
<div class="mom-pwd-mask">
    <div class="mom-pwd-box">
        <div class="mom-pwd-title">需要发布密码</div>
        <p class="mom-pwd-desc">该博客已开启发布密码，请输入验证后再发布</p>
        <input type="password" class="mom-pwd-input" placeholder="请输入发布密码" maxlength="100">
        <div class="mom-pwd-actions">
            <button type="button" class="mom-pwd-cancel">取消</button>
            <button type="button" class="mom-pwd-confirm">确认</button>
        </div>
    </div>
</div>

<script>
    // 密码门模式：验证成功后跳转写动态页（此时已持验证 Cookie，服务端放行）
    window.NR_PWD_GATE = true;
    // 前台基础路径（path 模式为前缀如 /blog，root/domain 模式为空串）
    window.NR_BASE = @json($basePath ?? '');
    window.NR_VERIFY_URL = "{{ url(($basePath ?? '') . '/verify-password') }}";
    window.NR_LIST_URL = "{{ url(($basePath ?? '') . '/') }}";
    window.NR_WRITE_URL = "{{ url(($basePath ?? '') . '/write') }}";
</script>
<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('apps/niuren.blog/js/app.js') }}?v=1.4.30"></script>
</body>
</html>

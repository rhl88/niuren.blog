<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>写文章</title>
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

    <div class="layui-card">
        <div class="layui-card-body">
            <form class="layui-form" id="blogForm">
                <div class="layui-form-item">
                    <label class="layui-form-label">标题</label>
                    <div class="layui-input-block">
                        <input type="text" name="title" placeholder="可选标题" class="layui-input">
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">正文</label>
                    <div class="layui-input-block">
                        <textarea name="content" placeholder="分享你的想法..." class="layui-textarea" style="min-height: 260px;"></textarea>
                    </div>
                </div>
                <div class="layui-form-item">
                    <div class="layui-input-block">
                        <button class="pear-btn pear-btn-primary" lay-submit lay-filter="publish">发布</button>
                        <a class="pear-btn" href="{{ url('blog') }}">取消</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script src="{{ asset('apps/niuren.blog/js/app.js') }}"></script>
<script>
layui.use(['form', 'jquery'], function(){
    var $ = layui.jquery;
    var form = layui.form;

    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    });

    form.on('submit(publish)', function(data){
        $.ajax({
            url: '{{ url('blog/publish') }}',
            type: 'POST',
            data: data.field,
            success: function(res){
                layer.msg(res.message || '发布成功');
                setTimeout(function(){
                    location.href = '{{ url('blog') }}';
                }, 600);
            }
        });
        return false;
    });
});
</script>
</body>
</html>

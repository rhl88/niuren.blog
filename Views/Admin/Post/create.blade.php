<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($post) ? '编辑文章' : '写文章' }}</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <link rel="stylesheet" href="{{ asset('apps/niuren.blog/css/style.css') }}">
</head>
<body>
<div class="pear-container">
    <div class="layui-card">
        <div class="layui-card-body">
            <form class="layui-form" id="postForm">
                <div class="layui-form-item">
                    <label class="layui-form-label">标题</label>
                    <div class="layui-input-block">
                        <input type="text" name="title" placeholder="可选标题" value="{{ isset($post) ? $post->title : '' }}" class="layui-input">
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">正文</label>
                    <div class="layui-input-block">
                        <textarea name="content" placeholder="分享你的想法..." class="layui-textarea" style="min-height: 240px;">{{ isset($post) ? $post->content : '' }}</textarea>
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">状态</label>
                    <div class="layui-input-block">
                        <input type="radio" name="status" value="0" title="草稿" {{ isset($post) && $post->status === 0 ? 'checked' : '' }}>
                        <input type="radio" name="status" value="1" title="发布" {{ !isset($post) || $post->status === 1 ? 'checked' : '' }}>
                    </div>
                </div>
                <div class="layui-form-item">
                    <div class="layui-input-block">
                        <button class="pear-btn pear-btn-primary" lay-submit lay-filter="save">保存</button>
                        <a class="pear-btn" href="{{ url('admin/niuren/blog/posts') }}">返回</a>
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

    form.on('submit(save)', function(data){
        var url = '{{ isset($post) ? url('api/admin/niuren/blog/posts/'.$post->id) : url('api/admin/niuren/blog/posts/save') }}';
        var type = '{{ isset($post) ? 'PUT' : 'POST' }}';

        $.ajax({
            url: url,
            type: type,
            data: data.field,
            success: function(res){
                layer.msg(res.message || '保存成功');
                setTimeout(function(){
                    location.href = '{{ url('admin/niuren/blog/posts') }}';
                }, 600);
            }
        });

        return false;
    });
});
</script>
</body>
</html>

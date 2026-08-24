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
    <style>
        .img-grid { display: flex; flex-wrap: wrap; gap: 10px; }
        .img-item { position: relative; width: 100px; height: 100px; border-radius: 6px; overflow: hidden; }
        .img-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .img-item .img-del {
            position: absolute; top: 4px; right: 4px; width: 22px; height: 22px;
            line-height: 22px; text-align: center; border-radius: 50%;
            background: rgba(0,0,0,.55); color: #fff; font-size: 12px; cursor: pointer;
        }
        .img-add {
            width: 100px; height: 100px; border: 1px dashed #d2d2d2; border-radius: 6px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: #999; cursor: pointer; user-select: none;
        }
        .img-add i { font-size: 24px; margin-bottom: 4px; }
        .img-add.disabled { opacity: .4; cursor: not-allowed; }
        #imageInput { display: none; }
    </style>
</head>
<body>
<div class="pear-container">
    <div class="layui-card">
        <div class="layui-card-body">
            <form class="layui-form" id="postForm">
                <div class="layui-form-item">
                    <label class="layui-form-label">正文</label>
                    <div class="layui-input-block">
                        <textarea name="content" placeholder="分享你的想法..." class="layui-textarea" style="min-height: 240px;">{{ isset($post) ? $post->content : '' }}</textarea>
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">图片</label>
                    <div class="layui-input-block">
                        <div class="img-grid" id="imgGrid">
                            <div class="img-add" id="imgAdd">
                                <i class="fa fa-plus"></i>
                                <span id="imgCount">0/9</span>
                            </div>
                        </div>
                        <input type="file" id="imageInput" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                        <div style="color:#999;font-size:12px;margin-top:6px;">可选，最多 9 张，单张不超过 5MB；不填标题时将展示为九宫格图片</div>
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

    var MAX_IMAGES = 9;
    // 编辑模式回显已有图片；新建模式为空数组
    var images = @json(isset($post) ? ($post->images ?? []) : []);
    var uploading = false;

    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        statusCode: {
            401: function () {
                layer.msg('登录已过期，请重新登录', { icon: 2 });
                setTimeout(function () {
                    location.href = '{{ url('admin/login') }}' + '?redirect=' + encodeURIComponent(location.href);
                }, 1500);
            }
        }
    });

    form.render();

    function renderGrid() {
        $('#imgGrid .img-item').remove();
        $.each(images, function(i, url){
            var item = $('<div class="img-item"><img src="'+url+'"><span class="img-del" data-i="'+i+'">&times;</span></div>');
            $('#imgAdd').before(item);
        });
        $('#imgCount').text(images.length + '/' + MAX_IMAGES);
        $('#imgAdd').toggleClass('disabled', images.length >= MAX_IMAGES);
    }

    function uploadFile(file) {
        if (images.length >= MAX_IMAGES) {
            layer.msg('最多上传 ' + MAX_IMAGES + ' 张图片', { icon: 2 });
            return;
        }
        if (!/^image\/(jpeg|png|gif|webp)$/.test(file.type)) {
            layer.msg('仅支持 JPEG、PNG、GIF、WebP 格式', { icon: 2 });
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            layer.msg('图片大小不能超过5MB', { icon: 2 });
            return;
        }

        var fd = new FormData();
        fd.append('image', file);
        uploading = true;
        var loadIndex = layer.load(1);

        $.ajax({
            url: '{{ url('api/admin/niuren/blog/upload') }}',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(res){
                layer.close(loadIndex);
                uploading = false;
                if (res.code === 0 && res.data && res.data.url) {
                    images.push(res.data.url);
                    renderGrid();
                } else {
                    layer.msg(res.message || '上传失败', { icon: 2 });
                }
            },
            error: function(xhr){
                layer.close(loadIndex);
                uploading = false;
                if (xhr.status === 401) return;
                var msg = '上传失败';
                try { msg = JSON.parse(xhr.responseText).message || msg; } catch(e) {}
                layer.msg(msg, { icon: 2 });
            }
        });
    }

    // 点击添加框 / 已有缩略图触发选图
    $(document).on('click', '#imgAdd:not(.disabled)', function(){ $('#imageInput').val('').click(); });

    // 选择文件后逐张上传（超出上限部分截断）
    $('#imageInput').on('change', function(){
        var files = Array.prototype.slice.call(this.files || []);
        if (!files.length) return;
        var remain = MAX_IMAGES - images.length;
        if (files.length > remain) {
            layer.msg('一次最多还能选择 ' + remain + ' 张', { icon: 2 });
            files = files.slice(0, Math.max(remain, 0));
        }
        files.forEach(uploadFile);
    });

    // 删除已上传图片
    $(document).on('click', '.img-del', function(){
        var i = parseInt($(this).data('i'), 10);
        if (!isNaN(i)) {
            images.splice(i, 1);
            renderGrid();
        }
    });

    form.on('submit(save)', function(data){
        if (uploading) {
            layer.msg('图片正在上传中，请稍候', { icon: 2 });
            return false;
        }
        if (!$.trim(data.field.content || '') && images.length === 0) {
            layer.msg('请填写正文或至少上传一张图片', { icon: 2 });
            return false;
        }

        var $btn = $(this);
        if ($btn.hasClass('layui-btn-disabled')) return false;
        $btn.addClass('layui-btn-disabled');
        var loadIndex = layer.load(1);

        var url = '{{ isset($post) ? url('api/admin/niuren/blog/posts/'.$post->id) : url('api/admin/niuren/blog/posts/save') }}';
        // PHP 不解析 PUT 请求的 multipart 表单体，编辑时用 POST + _method=PUT 伪装
        var isEdit = {{ isset($post) ? 'true' : 'false' }};

        // FormData 整体提交：未追加任何 images[] 即代表清空全部图片
        var fd = new FormData();
        if (isEdit) { fd.append('_method', 'PUT'); }
        fd.append('content', data.field.content || '');
        fd.append('status', data.field.status || '1');
        images.forEach(function(url){ fd.append('images[]', url); });

        $.ajax({
            url: url,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(res){
                layer.close(loadIndex);
                layer.msg(res.message || '保存成功');
                setTimeout(function(){
                    location.href = '{{ url('admin/niuren/blog/posts') }}';
                }, 600);
            },
            error: function(xhr){
                layer.close(loadIndex);
                $btn.removeClass('layui-btn-disabled');
                if (xhr.status === 401) return;
                var msg = '保存失败';
                try { msg = JSON.parse(xhr.responseText).message || msg; } catch(e) {}
                layer.msg(msg, { icon: 2 });
            }
        });

        return false;
    });

    renderGrid();
});
</script>
</body>
</html>

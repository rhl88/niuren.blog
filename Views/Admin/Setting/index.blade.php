<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>博客设置</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <link rel="stylesheet" href="{{ asset('apps/niuren.blog/css/style.css') }}">
    <style>
        .layui-form-label{width: 110px;}
        .layui-input-block{margin-left: 136px;}
        .image-upload-wrap { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .image-upload-wrap .preview-img {
            width: 96px; height: 96px; object-fit: cover;
            border: 1px solid #e6e6e6; border-radius: 6px; display: none;
        }
        .image-upload-wrap .preview-img.active { display: block; }
    </style>
</head>
<body>
<div class="pear-container">
    <div class="layui-card">
        <div class="layui-card-header">博客设置</div>
        <div class="layui-card-body">
            <form class="layui-form" id="settingForm">
                <div class="layui-tab layui-tab-brief" lay-filter="settingTab">
                    <ul class="layui-tab-title">
                        <li class="layui-this">基础设置</li>
                        <li>访问设置</li>
                    </ul>
                    <div class="layui-tab-content">

                        {{-- ========== Tab 1：基础设置 ========== --}}
                        <div class="layui-tab-item layui-show">
                            @foreach($baseItems as $item)
                                @php
                                    $isImage = in_array($item->code, [
                                        'app_niuren_blog_blog_avatar',
                                        'app_niuren_blog_blog_bg',
                                    ], true);
                                @endphp
                                <div class="layui-form-item">
                                    <label class="layui-form-label">{{ $item->name }}</label>
                                    <div class="layui-input-block">
                                        @if($item->type === 'number')
                                            <input type="number" name="{{ $item->code }}" value="{{ $item->value }}"
                                                   min="1" max="100" step="1" lay-affix="number"
                                                   placeholder="请输入正整数" class="layui-input">
                                        @elseif($item->type === 'select')
                                            {{-- 显示模式：layui 渲染下拉框，中文选项（浅色/深色） --}}
                                            <select name="{{ $item->code }}" lay-filter="themeMode">
                                                @foreach(($item->options ?? []) as $opt)
                                                    <option value="{{ $opt['value'] }}" {{ (string) $item->value === (string) $opt['value'] ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                                @endforeach
                                            </select>
                                        @elseif($item->type === 'switch')
                                            <input type="hidden" name="{{ $item->code }}" value="0">
                                            <input type="checkbox" name="{{ $item->code }}" lay-skin="switch"
                                                   lay-text="开启|关闭" value="1" {{ $item->value == '1' ? 'checked' : '' }}>
                                        @elseif($item->type === 'textarea')
                                            <textarea name="{{ $item->code }}" placeholder="请输入"
                                                      class="layui-textarea">{{ $item->value }}</textarea>
                                        @elseif($isImage)
                                            <input type="hidden" name="{{ $item->code }}" class="setting-image-input" value="{{ $item->value }}">
                                            <div class="image-upload-wrap">
                                                <img class="preview-img {{ $item->value ? 'active' : '' }}"
                                                     src="{{ $item->value }}" alt="{{ $item->name }}">
                                                <button type="button" class="layui-btn layui-btn-sm setting-upload-btn"
                                                        data-code="{{ $item->code }}"
                                                        data-preview="sibling img.preview-img">
                                                    <i class="layui-icon layui-icon-upload"></i> 点击上传
                                                </button>
                                            </div>
                                        @else
                                            <input type="text" name="{{ $item->code }}" value="{{ $item->value }}"
                                                   placeholder="请输入" class="layui-input">
                                        @endif
                                        @if(!empty($item->tips))
                                            <div class="layui-form-mid layui-word-aux">{{ $item->tips }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- ========== Tab 2：访问设置（单选下拉：根路径 / 路径前缀 / 子域名绑定） ========== --}}
                        <div class="layui-tab-item">
                            <blockquote class="layui-elem-quote layui-quote-nm">
                                访问方式<b>三选一</b>：仅当前所选方式的参数参与校验并生效；
                                切换方式时另一方式的参数值会保留，来回切换不丢失已填配置。
                            </blockquote>
                            {{-- 占用检测提示区：根路径 / 路径前缀模式下实时检测冲突 --}}
                            <div id="accessConflictTip" class="layui-form-item" style="display:none;">
                                <label class="layui-form-label" style="color:#FF5722;">占用冲突</label>
                                <div class="layui-input-block">
                                    <div class="layui-alert layui-alert-red" style="margin:0;">
                                        <div id="accessConflictMsg" style="word-break:break-all;"></div>
                                        <ul id="accessConflictList" style="margin:6px 0 0; padding-left:18px;"></ul>
                                    </div>
                                </div>
                            </div>
                            @foreach($accessItems as $item)
                                @php
                                    $isDomain = str_ends_with($item->code, 'access_domain');
                                    $depends  = $isDomain ? 'domain' : (str_ends_with($item->code, 'access_path_prefix') ? 'path' : null);
                                @endphp
                                <div class="layui-form-item" @if($depends) data-depends="{{ $depends }}" @endif>
                                    <label class="layui-form-label">{{ $item->name }}</label>
                                    <div class="layui-input-block">
                                        @if($item->type === 'select')
                                            <select name="{{ $item->code }}" lay-filter="accessMode">
                                                @foreach(($item->options ?? []) as $opt)
                                                    <option value="{{ $opt['value'] }}" {{ (string) $item->value === (string) $opt['value'] ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                                @endforeach
                                            </select>
                                        @elseif($item->type === 'switch')
                                            {{-- 隐藏域在前：未勾选时提交 0；勾选时后置的 1 覆盖 0 --}}
                                            <input type="hidden" name="{{ $item->code }}" value="0">
                                            <input type="checkbox" name="{{ $item->code }}" lay-skin="switch"
                                                   lay-text="开启|关闭" value="1" {{ $item->value == '1' ? 'checked' : '' }}>
                                        @elseif($item->type === 'textarea')
                                            <textarea name="{{ $item->code }}" placeholder="请输入"
                                                      class="layui-textarea">{{ $item->value }}</textarea>
                                        @else
                                            <input type="text" name="{{ $item->code }}" value="{{ $item->value }}"
                                                   placeholder="{{ $isDomain ? '如 blog.example.com' : '如 /blog' }}"
                                                   class="layui-input" style="max-width: 360px;"
                                                   data-role="access-prefix">
                                        @endif
                                        @if(!empty($item->tips))
                                            <div class="layui-form-mid layui-word-aux">{{ $item->tips }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                    </div>
                </div>

                <div class="layui-form-item">
                    <div class="layui-input-block">
                        <button class="pear-btn pear-btn-primary" lay-submit lay-filter="saveSetting">保存设置</button>
                        <button type="reset" class="pear-btn">重置</button>
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
layui.use(['form', 'jquery', 'element', 'upload'], function(){
    var $ = layui.jquery;
    var form = layui.form;
    var element = layui.element;
    var upload = layui.upload;

    form.render('select');

    // 博主头像 / 背景图：点击上传（走系统附件上传接口，app_id 归本应用）
    upload.render({
        elem: '.setting-upload-btn',
        url: "{{ url('api/admin/attachments/upload') }}",
        field: 'files[]',
        accept: 'images',
        exts: 'jpg|jpeg|png|gif|webp',
        size: 5120,
        data: function () { return { app_id: 'niuren.blog', _token: "{{ csrf_token() }}" }; },
        done: function (res) {
            if (res.code !== 0 || !res.data || !res.data.success || res.data.success.length === 0) {
                layer.msg((res.data && res.data.failed && res.data.failed[0]) || res.message || '上传失败', { icon: 2 });
                return;
            }
            var url = res.data.success[0].url;
            var code = $(this.item).attr('data-code');
            var input = $('input[name="' + code + '"]');
            var preview = input.siblings('.image-upload-wrap').find('.preview-img');
            input.val(url);
            preview.attr('src', url).addClass('active');
            layer.msg('上传成功', { icon: 1, time: 1200 });
        },
        error: function () {
            layer.msg('上传请求失败', { icon: 2 });
        }
    });

    // 访问方式联动：仅显示当前所选方式的参数行（隐藏行的值仍会提交，由服务端保留不清空）
    function syncAccessMode(){
        var mode = $('select[name$="_access_mode"]').val() || 'root';
        $('[data-depends]').each(function(){
            $(this).toggle($(this).data('depends') === mode);
        });
        checkAccessConflict();
    }

    // 占用检测：根路径 / 路径前缀模式下实时调用预检接口，展示冲突明细
    var checkPending = null;
    function checkAccessConflict(){
        var mode = $('select[name$="_access_mode"]').val() || 'root';
        if (mode !== 'root' && mode !== 'path') {
            hideConflictTip();
            return;
        }
        var prefix = mode === 'path' ? ($('[data-role="access-prefix"]').val() || '').trim() : '';
        if (mode === 'path' && prefix.replace(/\//g, '') === '') {
            hideConflictTip();
            return;
        }

        if (checkPending) { checkPending.abort(); }
        checkPending = $.ajax({
            url: "{{ url('api/admin/niuren/blog/setting/check') }}",
            type: 'POST',
            data: { access_mode: mode, access_path_prefix: prefix },
            success: function(res){
                if (res && res.code === 40002) {
                    showConflictTip(res);
                } else {
                    hideConflictTip();
                }
            },
            error: function(){ /* 预检失败不打扰用户，保存时后端会兜底拦截 */ }
        });
    }

    function showConflictTip(res){
        $('#accessConflictMsg').text(res.message || '当前访问配置与已启用应用冲突');
        var list = $('#accessConflictList').empty();
        $.each((res.data && res.data.conflicts) || [], function(i, c){
            $('<li>').text('路径 ' + c.path + ' 已被「' + c.app + '」占用（' + c.title + '）').appendTo(list);
        });
        $('#accessConflictTip').show();
    }

    function hideConflictTip(){
        $('#accessConflictTip').hide();
    }

    form.on('select(accessMode)', syncAccessMode);
    // 前缀输入变化时重新检测（输入防抖 400ms）
    var prefixTimer = null;
    $(document).on('input', '[data-role="access-prefix"]', function(){
        clearTimeout(prefixTimer);
        prefixTimer = setTimeout(checkAccessConflict, 400);
    });

    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': "{{ csrf_token() }}" },
        statusCode: {
            401: function () {
                layer.msg('登录已过期，请重新登录', { icon: 2 });
                setTimeout(function () {
                    location.href = "{{ url('admin/login') }}" + '?redirect=' + encodeURIComponent(location.href);
                }, 1500);
            }
        }
    });

    // 首次联动需在 CSRF 头配置完成后执行，保证预检请求合法
    syncAccessMode();

    // 一次性提交全部页签字段：非当前方式的参数原样提交，服务端按「保留不清空」策略处理，来回切换不丢已填配置
    form.on('submit(saveSetting)', function(data){
        $.ajax({
            url: "{{ url('api/admin/niuren/blog/setting/save') }}",
            type: 'POST',
            data: data.field,
            success: function(res){
                if (res.code !== 0) {
                    // 占用冲突：展示明细并滚动到提示区
                    if (res.code === 40002) {
                        showConflictTip(res);
                        layer.msg(res.message || '存在占用冲突，无法保存', { icon: 2, time: 3000 });
                    } else {
                        layer.msg(res.message || '保存失败', { icon: 2 });
                    }
                    return;
                }
                hideConflictTip();
                layer.msg(res.message || '保存成功', { icon: 1 });
            },
            error: function(xhr){
                if (xhr.status === 401) return;
                var msg = '保存失败';
                var res = null;
                try { res = JSON.parse(xhr.responseText); msg = res.message || msg; } catch(e) {}
                if (res && res.code === 40002) { showConflictTip(res); }
                layer.msg(msg, { icon: 2 });
            }
        });
        return false;
    });
});
</script>
</body>
</html>

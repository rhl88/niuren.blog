<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>文章管理</title>
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
            <form class="layui-form" id="searchForm">
                <div class="layui-form-item">
                    <div class="layui-inline">
                        <label class="layui-form-label">关键词</label>
                        <div class="layui-input-inline">
                            <input type="text" name="keyword" placeholder="请输入内容关键词" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-inline">
                        <label class="layui-form-label">状态</label>
                        <div class="layui-input-inline">
                            <select name="status">
                                <option value="">全部</option>
                                <option value="1">已发布</option>
                                <option value="0">草稿</option>
                            </select>
                        </div>
                    </div>
                    <div class="layui-inline">
                        <button class="pear-btn pear-btn-primary" lay-submit lay-filter="search">查询</button>
                        <button class="pear-btn" lay-submit lay-filter="reset">重置</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="layui-card">
        <div class="layui-card-body">
            <div class="layui-btn-group">
                <a href="{{ url('admin/niuren/blog/posts/create') }}" class="pear-btn pear-btn-primary">
                    <i class="fa fa-plus"></i> 写文章
                </a>
            </div>
            <table id="postTable" lay-filter="postTable"></table>
        </div>
    </div>
</div>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script src="{{ asset('apps/niuren.blog/js/app.js') }}"></script>
<script>
layui.use(['table', 'form', 'jquery'], function(){
    var $ = layui.jquery;

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

    var table = layui.table;

    table.render({
        elem: '#postTable',
        url: '{{ url('api/admin/niuren/blog/posts/list') }}',
        skin: false,
        page: {
            layout: ['count', 'prev', 'page', 'next', 'limit', 'skip'],
            groups: 5,
            limit: 10,
            limits: [10, 20, 50, 100]
        },
        // 接口返回 { code, data: { list, total } }，需映射为 Layui 表格格式
        parseData: function(res){
            return {
                'code': res.code,
                'msg': res.message || '',
                'count': res.data ? res.data.total : 0,
                'data': res.data ? res.data.list : []
            };
        },
        cols: [[
            {field: 'id', title: 'ID', width: 80},
            {field: 'content', title: '内容', templet: function(d){
                var text = String(d.content || '').replace(/\s+/g, ' ').trim();
                if (!text) return '<span style="color:#999;">[图片]</span>';
                if (text.length > 30) text = text.substring(0, 30) + '…';
                var box = document.createElement('div');
                box.textContent = text;
                return box.innerHTML;
            }},
            {field: 'status', title: '状态', width: 100, templet: '#statusTpl'},
            {field: 'create_time', title: '创建时间', width: 180},
            {field: 'update_time', title: '更新时间', width: 180},
            {title: '操作', width: 220, align: 'center', toolbar: '#toolbarTpl'}
        ]]
    });

    table.on('tool(postTable)', function(obj){
        var data = obj.data;
        if(obj.event === 'edit'){
            location.href = '{{ url('admin/niuren/blog/posts') }}/' + data.id + '/edit';
        } else if(obj.event === 'del'){
            layer.confirm('确定删除该文章吗？', function(){
                $.ajax({
                    url: '{{ url('api/admin/niuren/blog/posts') }}/' + data.id,
                    type: 'DELETE',
                    success: function(res){
                        layer.msg(res.message || '删除成功');
                        table.reload('postTable');
                    },
                    error: function(xhr){
                        if (xhr.status === 401) return;
                        var msg = '删除失败';
                        try { msg = JSON.parse(xhr.responseText).message || msg; } catch(e) {}
                        layer.msg(msg, { icon: 2 });
                    }
                });
            });
        }
    });

    form.on('submit(search)', function(data){
        table.reload('postTable', {
            where: data.field
        });
        return false;
    });

    form.on('submit(reset)', function(){
        $('#searchForm')[0].reset();
        table.reload('postTable');
        return false;
    });
});
</script>
@verbatim
<script type="text/html" id="statusTpl">
    {{#  if(d.status === 1){ }}
        <span class="layui-badge layui-bg-green">已发布</span>
    {{#  } else { }}
        <span class="layui-badge layui-bg-gray">草稿</span>
    {{#  } }}
</script>
<script type="text/html" id="toolbarTpl">
    <a class="pear-btn pear-btn-primary pear-btn-sm" lay-event="edit">编辑</a>
    <a class="pear-btn pear-btn-danger pear-btn-sm" lay-event="del">删除</a>
</script>
@endverbatim
</body>
</html>

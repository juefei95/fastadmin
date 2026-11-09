define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({extend: {index_url: 'code2yellow/announcement/index' + location.search, add_url: 'code2yellow/announcement/add', edit_url: 'code2yellow/announcement/edit', del_url: 'code2yellow/announcement/del', table: 'code2yellow_announcement'}});
            var table = $('#table');
            table.bootstrapTable({url: $.fn.bootstrapTable.defaults.extend.index_url, pk: 'id', sortName: 'id', sortOrder: 'desc', columns: [[
                {field: 'id', title: 'ID'}, {field: 'title', title: '标题', operate: 'LIKE', escape: true},
                {field: 'status', title: '状态', searchList: {draft:'草稿',published:'发布',disabled:'停用'}, formatter: Table.api.formatter.normal},
                {field: 'mode', title: '模式', searchList: {all:'全部',green:'绿区',yellow:'黄区'}, formatter: Table.api.formatter.normal},
                {field: 'is_pinned', title: '置顶', operate: false, formatter: function(v){return Number(v) ? '是' : '否';}},
                {field: 'is_urgent', title: '紧急', operate: false, formatter: function(v){return Number(v) ? '是' : '否';}},
                {field: 'published_at', title: '发布时间', operate: false, formatter: function(v, row, index){return v ? Table.api.formatter.datetime(v, row, index) : '-';}},
                {field: 'operate', title: '操作', table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
            ]]});
            Table.api.bindevent(table);
        },
        add: function () { Controller.api.bindevent(); },
        edit: function () { Controller.api.bindevent(); },
        api: {bindevent: function () {
            Form.api.bindevent($('form[role=form]'));
            $('.btn-preview').on('click', function () {
                var content = $('<div/>').css({padding: '20px', whiteSpace: 'pre-wrap', wordBreak: 'break-word'});
                $('<h3/>').text($('[name="row[title]"]').val()).appendTo(content);
                $('<p/>').text($('[name="row[content]"]').val()).appendTo(content);
                Layer.open({type: 1, title: '公告预览', area: ['500px', '400px'], content: $('<div/>').append(content).html()});
            });
        }}
    };
    return Controller;
});

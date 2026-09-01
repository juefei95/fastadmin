define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'third/index' + location.search,
                    add_url: 'third/add',
                    edit_url: 'third/edit',
                    del_url: 'third/del',
                    multi_url: 'third/multi',
                    table: 'third',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'user_id', title: __('User_id'), formatter: Table.api.formatter.search},
                        {field: 'user.nickname', title: __('Nickname')},
                        {field: 'platform', title: __('Platform'), formatter: Table.api.formatter.flag, searchList: {'wechat': '微信', 'qq': 'QQ', 'weibo': '微博'}, custom: {'wechat': 'success', 'qq': 'info', 'weibo': 'danger'}},
                        {field: 'apptype', title: __('Apptype'), formatter: Table.api.formatter.normal, searchList: {'mp': '公众号', 'wxapp': '小程序', 'web': 'PC端'}},
                        {field: 'openid', title: __('Openid')},
                        {field: 'unionid', title: __('Unionid')},
                        {field: 'openname', title: __('Openname'), formatter: Table.api.formatter.search},
                        {field: 'createtime', title: __('Createtime'), sortable: true, operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), visible: false, sortable: true, operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'logintime', title: __('Logintime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});

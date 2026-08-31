define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'remotecontrol/member/index' + location.search,
                    add_url: 'remotecontrol/member/add',
                    edit_url: 'remotecontrol/member/edit',
                    del_url: 'remotecontrol/member/del',
                    multi_url: 'remotecontrol/member/multi',
                    import_url: 'remotecontrol/member/import',
                    table: 'remote_member',
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
                        {field: 'user_id', title: __('User_id')},
                        {field: 'expire_time', title: __('Expire_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'trial_given', title: __('Trial_given'), searchList: {"0":__('Trial_given 0'),"1":__('Trial_given 1')}, formatter: Table.api.formatter.normal},
                        {field: 'trial_started_at', title: __('Trial_started_at')},
                        {field: 'control_enabled', title: __('Control_enabled'), searchList: {"0":__('Control_enabled 0'),"1":__('Control_enabled 1')}, formatter: Table.api.formatter.normal},
                        {field: 'last_paid_at', title: __('Last_paid_at')},
                        {field: 'total_paid', title: __('Total_paid'), operate:'BETWEEN'},
                        {field: 'created_at', title: __('Created_at')},
                        {field: 'updated_at', title: __('Updated_at')},
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

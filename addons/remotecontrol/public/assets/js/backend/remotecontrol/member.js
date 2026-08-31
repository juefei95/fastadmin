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
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                {name: 'adddays1', text: __('Add 1 day'), title: __('Add 1 day'), classname: 'btn btn-xs btn-success btn-ajax', icon: 'fa fa-plus', url: 'remotecontrol/member/adddays?days=1', confirm: __('Are you sure you want to add %s day?', 1), refresh: true},
                                {name: 'adddays7', text: __('Add 7 days'), title: __('Add 7 days'), classname: 'btn btn-xs btn-info btn-ajax', icon: 'fa fa-plus', url: 'remotecontrol/member/adddays?days=7', confirm: __('Are you sure you want to add %s days?', 7), refresh: true},
                                {name: 'adddays30', text: __('Add 30 days'), title: __('Add 30 days'), classname: 'btn btn-xs btn-warning btn-ajax', icon: 'fa fa-plus', url: 'remotecontrol/member/adddays?days=30', confirm: __('Are you sure you want to add %s days?', 30), refresh: true},
                                {name: 'adddays', text: __('Custom add'), title: __('Custom add'), classname: 'btn btn-xs btn-primary btn-dialog', icon: 'fa fa-plus-square', url: 'remotecontrol/member/adddays', refresh: true},
                                {name: 'setexpire', text: __('Set expire time'), title: __('Set expire time'), classname: 'btn btn-xs btn-default btn-dialog', icon: 'fa fa-clock-o', url: 'remotecontrol/member/setexpire', refresh: true},
                                {name: 'enable', text: __('Enable control'), title: __('Enable control'), classname: 'btn btn-xs btn-success btn-ajax', icon: 'fa fa-check', url: 'remotecontrol/member/enable', confirm: __('Are you sure you want to enable control?'), refresh: true, hidden: function (row) { return row.control_enabled == 1; }},
                                {name: 'disable', text: __('Disable control'), title: __('Disable control'), classname: 'btn btn-xs btn-danger btn-ajax', icon: 'fa fa-ban', url: 'remotecontrol/member/disable', confirm: __('Are you sure you want to disable control?'), refresh: true, hidden: function (row) { return row.control_enabled != 1; }}
                            ],
                            formatter: Table.api.formatter.operate
                        }
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
        adddays: function () {
            Controller.api.bindevent();
        },
        setexpire: function () {
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

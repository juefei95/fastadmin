define(['jquery', 'bootstrap', 'backend', 'table'], function ($, undefined, Backend, Table) {
    var Controller = {
        index: function () {
            Table.api.init({extend: {index_url: 'remotecontrol/rdp_tunnel/index' + location.search, table: 'remote_rdp_tunnel'}});
            var table = $('#table');
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id', sortName: 'updated_at', sortOrder: 'desc',
                columns: [[
                    {field: 'id', title: 'ID', operate: false},
                    {field: 'user_id', title: '用户 ID', operate: '='},
                    {field: 'device_id', title: '设备 ID', operate: 'LIKE', escape: true},
                    {field: 'hostname', title: '主机名', operate: false, escape: true},
                    {field: 'public_port', title: '公网端口', operate: '='},
                    {field: 'status', title: '状态', operate: '=', formatter: function (value) {
                        var labels = {active: 'label-success', allocated: 'label-info', pending: 'label-info', stopping: 'label-warning', inactive: 'label-default'};
                        var names = {active: '运行中', allocated: '已分配', pending: '已分配', stopping: '等待客户端停止', inactive: '未启用'};
                        return '<span class="label ' + (labels[value] || 'label-default') + '">' + (names[value] || value) + '</span>';
                    }},
                    {field: 'expire_time', title: '会员到期', operate: false, formatter: function (value, row, index) { return value ? Table.api.formatter.datetime(value, row, index) : '-'; }},
                    {field: 'last_error', title: '状态原因', operate: 'LIKE', escape: true},
                    {field: 'updated_at', title: '更新时间', operate: 'RANGE', addclass: 'datetimerange', formatter: function (value, row, index) { return value ? Table.api.formatter.datetime(value, row, index) : '-'; }},
                    {field: 'operate', title: '操作', events: {'click .btn-close-tunnel': function (e, value, row) {
                        Layer.confirm('确认通知客户端停止该 Windows 远程桌面映射吗？', {icon: 3}, function (index) {
                            Layer.close(index);
                            Fast.api.ajax({url: 'remotecontrol/rdp_tunnel/close', data: {ids: row.id}}, function () { table.bootstrapTable('refresh'); });
                        });
                    }}, formatter: function (value, row) {
                        return row.public_port ? '<a class="btn btn-xs btn-danger btn-close-tunnel"><i class="fa fa-power-off"></i> 停用</a>' : '-';
                    }}
                ]]
            });
            Table.api.bindevent(table);
        }
    };
    return Controller;
});

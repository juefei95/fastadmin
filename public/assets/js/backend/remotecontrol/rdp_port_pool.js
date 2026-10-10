define(['jquery', 'bootstrap', 'backend', 'table'], function ($, undefined, Backend, Table) {
    var Controller = {
        index: function () {
            Table.api.init({extend: {index_url: 'remotecontrol/rdp_port_pool/index' + location.search}});
            var table = $('#table');
            var updateAccess = function (open, ports) {
                if (!ports.length) {
                    Layer.msg('请选择端口');
                    return;
                }
                Fast.api.ajax({
                    url: 'remotecontrol/rdp_port_pool/' + (open ? 'openaccess' : 'closeaccess'),
                    data: {ids: ports.join(',')}
                }, function () {
                    table.bootstrapTable('refresh');
                });
            };
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'port',
                sortName: 'port',
                search: true,
                columns: [[
                    {checkbox: true},
                    {field: 'port', title: '端口'},
                    {field: 'allocation_status', title: '端口池状态', formatter: function (value) {
                        return value === 'allocated'
                            ? '<span class="label label-primary">已分配</span>'
                            : '<span class="label label-success">未分配</span>';
                    }},
                    {field: 'pool_status', title: '代理状态', formatter: function (value) {
                        var names = {free: '空闲', rustdesk_online: 'RustDesk在线', reserved_offline: '已分配未上线', reserved_unverified: '已分配/未核验', external_occupied: '未知占用', conflict: '映射冲突', unknown: '监控不可用'};
                        var styles = {free: 'success', rustdesk_online: 'primary', reserved_offline: 'info', reserved_unverified: 'default', external_occupied: 'warning', conflict: 'danger', unknown: 'default'};
                        return '<span class="label label-' + (styles[value] || 'default') + '">' + (names[value] || value) + '</span>';
                    }},
                    {field: 'public_access_status', title: '公网访问状态', formatter: function (value) {
                        var names = {open: '开放', closed: '关闭', unknown: '未知'};
                        var styles = {open: 'success', closed: 'danger', unknown: 'default'};
                        return '<span class="label label-' + (styles[value] || 'default') + '">' + (names[value] || '未知') + '</span>';
                    }},
                    {field: 'user_id', title: '用户 ID'},
                    {field: 'device_id', title: '设备 ID', escape: true},
                    {field: 'mapping_status', title: '后台状态', escape: true},
                    {field: 'expected_proxy_name', title: '预期代理名', escape: true},
                    {field: 'actual_proxy_name', title: 'FRPS 实际代理名', escape: true},
                    {field: 'actual_status', title: 'FRPS 状态', escape: true},
                    {field: 'monitor_error', title: '监控说明', escape: true},
                    {field: 'public_access_error', title: '公网访问说明', escape: true},
                    {field: 'operate', title: '公网访问操作', events: {
                        'click .btn-access-row-open': function (e, value, row) {
                            updateAccess(true, [row.port]);
                        },
                        'click .btn-access-row-close': function (e, value, row) {
                            updateAccess(false, [row.port]);
                        }
                    }, formatter: function () {
                        return '<a class="btn btn-xs btn-success btn-access-row-open"><i class="fa fa-unlock"></i> 开放</a> ' +
                            '<a class="btn btn-xs btn-danger btn-access-row-close"><i class="fa fa-lock"></i> 关闭</a>';
                    }}
                ]]
            });
            $('#toolbar').on('click', '.btn-access-open,.btn-access-close', function () {
                var open = $(this).hasClass('btn-access-open');
                var ports = $.map(table.bootstrapTable('getSelections'), function (row) { return row.port; });
                updateAccess(open, ports);
            });
            Table.api.bindevent(table);
        }
    };
    return Controller;
});


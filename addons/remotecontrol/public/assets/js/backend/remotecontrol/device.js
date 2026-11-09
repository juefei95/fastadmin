define(['jquery', 'bootstrap', 'backend', 'table'], function ($, undefined, Backend, Table) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'remotecontrol/device/index' + location.search,
                    table: 'remote_device'
                }
            });

            var table = $('#table');
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'last_seen_at',
                sortOrder: 'desc',
                columns: [[
                    {field: 'device_id', title: '设备 ID', operate: 'LIKE', escape: true},
                    {field: 'hostname', title: '主机名', operate: 'LIKE', escape: true},
                    {field: 'username', title: '用户名', operate: 'LIKE', escape: true},
                    {field: 'client_version', title: '客户端版本', operate: false, escape: true},
                    {field: 'connection_count', title: '活动连接数', operate: false},
                    {
                        field: 'last_seen_at',
                        title: '最后心跳时间',
                        operate: 'RANGE',
                        addclass: 'datetimerange',
                        formatter: function (value, row, index) {
                            return value ? Table.api.formatter.datetime(value, row, index) : '-';
                        }
                    },
                    {
                        field: 'online',
                        title: '状态',
                        operate: false,
                        sortable: false,
                        formatter: function (value, row) {
                            var online = Number(row.last_seen_at) >= Math.floor(Date.now() / 1000) - 60;
                            return online
                                ? '<span class="label label-success">在线</span>'
                                : '<span class="label label-default">离线</span>';
                        }
                    }
                ]]
            });
            Table.api.bindevent(table);

            var timer = setInterval(function () {
                table.bootstrapTable('refresh', {silent: true});
            }, 15000);
            $(window).one('unload', function () {
                clearInterval(timer);
            });
        }
    };
    return Controller;
});

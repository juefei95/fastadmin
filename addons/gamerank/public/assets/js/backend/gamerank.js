define(['jquery', 'bootstrap', 'backend', 'table'], function ($, undefined, Backend, Table) {
    var Controller = {
        player: function () {
            Table.api.init({extend: {index_url: 'gamerank/player' + location.search, table: 'game_rank_player'}});
            var table = $('#table');
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'total_points',
                sortOrder: 'desc',
                columns: [[
                    {field: 'id', title: __('Id')},
                    {field: 'user_id', title: __('User_id'), formatter: Table.api.formatter.search},
                    {field: 'user.username', title: __('Username')},
                    {field: 'nickname', title: __('Nickname'), formatter: Table.api.formatter.search},
                    {field: 'avatar_url', title: __('Avatar_url'), formatter: Table.api.formatter.image},
                    {field: 'total_points', title: __('Total_points'), sortable: true},
                    {field: 'rank_visible', title: __('Rank_visible'), searchList: {'0': __('No'), '1': __('Yes')}, formatter: function (value) { return value ? __('Yes') : __('No'); }},
                    {field: 'last_played_at', title: __('Last_played_at'), sortable: true, operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                    {field: 'points_updated_at', title: __('Points_updated_at'), sortable: true, operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                    {field: 'createtime', title: __('Createtime'), sortable: true, operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime}
                ]]
            });
            Table.api.bindevent(table);
        },
        pointlog: function () {
            Table.api.init({extend: {index_url: 'gamerank/pointlog' + location.search, table: 'game_rank_point_log'}});
            var table = $('#table');
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                sortOrder: 'desc',
                columns: [[
                    {field: 'id', title: __('Id')},
                    {field: 'user_id', title: __('User_id'), formatter: Table.api.formatter.search},
                    {field: 'user.username', title: __('Username')},
                    {field: 'delta', title: __('Delta'), sortable: true},
                    {field: 'points_before', title: __('Points_before')},
                    {field: 'points_after', title: __('Points_after')},
                    {field: 'source_type', title: __('Source_type'), formatter: Table.api.formatter.search},
                    {field: 'source_ref', title: __('Source_ref'), formatter: Table.api.formatter.search},
                    {field: 'remark', title: __('Remark')},
                    {field: 'createtime', title: __('Createtime'), sortable: true, operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime}
                ]]
            });
            Table.api.bindevent(table);
        }
    };
    return Controller;
});

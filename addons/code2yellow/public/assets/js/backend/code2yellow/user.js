define(['jquery', 'bootstrap', 'backend', 'table'], function ($, undefined, Backend, Table) {
    return {index: function () {
        Table.api.init({extend: {index_url: 'code2yellow/user/index' + location.search, table: 'code2yellow_user'}});
        var table = $('#table');
        table.bootstrapTable({url: $.fn.bootstrapTable.defaults.extend.index_url, pk: 'id', sortName: 'last_login_at', sortOrder: 'desc', columns: [[
            {field: 'user_id', title: '用户 ID', operate: '='},
            {field: 'nickname', title: '昵称', operate: false, escape: true},
            {field: 'created_at', title: '首次登录', operate: false, formatter: Table.api.formatter.datetime},
            {field: 'last_login_at', title: '最近登录', operate: false, formatter: Table.api.formatter.datetime}
        ]]});
        Table.api.bindevent(table);
    }};
});

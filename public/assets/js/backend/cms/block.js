define(['jquery', 'bootstrap', 'backend', 'table', 'form', '../../../addons/cms/js/base'], function ($, undefined, Backend, Table, Form, Base) {

    //设置弹窗宽高
    Fast.config.openArea = ['80%', '80%'];

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'cms/block/index',
                    add_url: 'cms/block/add',
                    edit_url: 'cms/block/edit',
                    del_url: 'cms/block/del',
                    multi_url: 'cms/block/multi',
                    table: 'cms_block',
                }
            });

            var table = $("#table");

            var columns = [
                {checkbox: true},
                {field: 'id', sortable: true, title: __('Id')},
                {field: 'type', title: __('Type'), formatter: Table.api.formatter.search, searchList: Config.typeList},
                {field: 'name', title: __('Name'), formatter: Table.api.formatter.search, operate: 'like'},
                {field: 'title', title: __('Title'), operate: 'like'},
                {field: 'image', title: __('Image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                {field: 'url', title: __('Url'), formatter: Table.api.formatter.url},
                {field: 'createtime', title: __('Createtime'), sortable: true, operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                {field: 'updatetime', title: __('Updatetime'), sortable: true, visible: false, operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                {field: 'weigh', title: __('Weigh'), visible: false},
                {field: 'status', title: __('Status'), searchList: {"normal": __('Normal'), "hidden": __('Hidden')}, formatter: Table.api.formatter.status},
                {field: 'operate', title: __('Operate'), clickToSelect: false, table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
            ];

            //动态主表追加字段
            columns.splice.apply(columns, [-1, 0].concat(Base.api.getCustomFields(Config.fields, table)));

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: columns
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
                $(document).on("keyup", "#c-type_text", function () {
                    $("#c-type").val($(this).val());
                });
                $(document).on("change", "#c-select-name", function () {
                    $("#c-name").val($(this).val());
                });
                $(document).on("click", ".btn-select-link", function () {
                    var url = $(this).data("url");
                    parent.Fast.api.open(url, "选择链接", {
                        callback: function (data) {
                            $("#c-url").val(typeof data === 'string' ? data : data.url);
                        }
                    });
                });
                $(document).on("click", ".btn-richtext", function () {
                    if ($(this).data("current") === 'source') {
                        $("#richtext > textarea").addClass("editor");
                        Form.api.bindevent($("#richtext"));
                        $(this).html("<i class=\"fa fa-code\"></i> 切换为源码模式").data("current", "richtext");
                    } else {
                        var content = $("#richtext [name='row[content]']").val();
                        console.log(content);
                        $("#richtext").html('<textarea id="c-content" class="form-control" rows="15" name="row[content]" cols="50"></textarea>');
                        $("#richtext > textarea").val(content);
                        $(this).html("<i class=\"fa fa-magic\"></i> 切换为富文本模式").data("current", "source");
                    }
                });
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});

define(['jquery', 'bootstrap', 'frontend', 'template', 'form'], function ($, undefined, Frontend, Template, Form) {
    var Controller = {
        my: function () {
            $(document).on('click', '.btn-delete', function () {
                var that = this;
                Layer.confirm("确认删除？删除后将不能恢复", {icon: 3}, function () {
                    var url = $(that).data("url");
                    Fast.api.ajax({
                        url: url,
                    }, function (data) {
                        Layer.closeAll();
                        location.reload();
                        return false;
                    });
                });
                return false;
            });
        },
        post: function () {
            require(['jquery-autocomplete'], function () {
                var search = $("#c-title");
                var form = search.closest("form");
                search.autoComplete({
                    minChars: 1,
                    cache: false,
                    menuClass: 'autocomplete-searchtitle',
                    header: Template('headertpl', {}),
                    footer: '',
                    source: function (term, response) {
                        try {
                            xhr.abort();
                        } catch (e) {
                        }
                        xhr = $.getJSON(search.data("suggestion-url"), {q: term}, function (data) {
                            response($.isArray(data) ? data : []);
                        });
                    },
                    renderItem: function (item, search) {
                        search = search.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
                        var regexp = new RegExp("(" + search.replace(/[\,|\u3000|\uff0c]/, ' ').split(' ').join('|') + ")", "gi");
                        Template.helper("replace", function (value) {
                            return value.replace(regexp, "<b>$1</b>");
                        });
                        return Template('itemtpl', {item: item, search: search});
                    },
                    onSelect: function (e, term, item) {
                        e.preventDefault();
                        if ($(item).data("url")) {
                            location.href = $(item).data("url");
                        }
                        return false;
                    }
                });
            });
            require(['jquery-tagsinput'], function () {
                //标签输入
                var elem = "#c-tags";
                var tags = $(elem);
                tags.tagsInput({
                    width: 'auto',
                    defaultText: '输入后回车确认',
                    minInputWidth: 110,
                    height: '36px',
                    placeholderColor: '#999',
                    onChange: function (row) {
                        $(elem + "_addTag").focus();
                        $(elem + "_tag").trigger("blur.autocomplete").focus();
                    },
                    autocomplete: {
                        url: 'cms.archives/tags_autocomplete',
                        minChars: 1,
                        menuClass: 'autocomplete-tags'
                    }
                });
            });
            $(document).on('change', '#c-channel_id', function () {
                var model = $("option:selected", this).attr("model");
                var value = $(this).val();
                localStorage.setItem('last_channel_id', value);

                Fast.api.ajax({
                    url: 'cms.archives/get_channel_fields',
                    data: {channel_id: $(this).val(), archives_id: Config.archives_id}
                }, function (data) {
                    var extend = $("#extend");
                    if (extend.data("model") != model) {
                        $("div.form-group[data-field]").hide();
                        $.each(data.contributefields, function (i, j) {
                            $("div.form-group[data-field='" + j + "']").show();
                        });
                        extend.html(data.html).data("model", model);
                        Form.api.bindevent(extend);
                    }
                    return false;
                });
                var channelSel = $("#c-channel_ids");
                $("option", channelSel).prop("disabled", true);
                $("option[model!='" + model + "']", channelSel).prop("selected", false);
                $("option[model='" + model + "']:not([disabled])", channelSel).each(function () {
                    $("option[model='" + $(this).attr("model") + "'][value='" + $(this).attr("value") + "']", channelSel).prop("disabled", false);
                });
                if (channelSel.data("selectpicker")) {
                    channelSel.data("selectpicker").refresh();
                }
            });
            $(document).on("fa.event.appendfieldlist", ".downloadlist", function (a) {
                Form.events.plupload(this);
                $(".fachoose", this).off("click");
                Form.events.faselect(this);
            });
            //不可见的元素不验证
            $("form[role=form]").data("validator-options", {
                ignore: ':hidden'
            });
            Form.api.bindevent($("form[role=form]"), function (data, ret) {
                $(".layer-footer [type=submit]", this).addClass("disabled");
                setTimeout(function () {
                    location.href = Fast.api.fixurl('cms.archives/my');
                }, 1500);
            });
            // $("#c-channel_id").trigger("change");
        }
    };
    return Controller;
});

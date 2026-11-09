define(['jquery', 'bootstrap', 'backend'], function ($, undefined, Backend) {
    var Controller = {
        index: function () {
            $('.js-adjust-coin').prop('disabled', false);
            $(document).on('click.walletAdjust', '.js-adjust-coin', function () {
                var button = $(this);
                $('#coin-adjust')[0].reset();
                $('#coin-adjust-user-id').val(button.data('user-id'));
                $('#coin-adjust-user').text('用户：' + button.data('username') + '（ID ' + button.data('user-id') + '）');
                $('#coin-adjust-modal').modal('show');
            });
            $('#coin-adjust').on('submit.walletAdjust', function (e) {
                e.preventDefault();
                var form = this;
                var submit = $(form).find('[type=submit]').prop('disabled', true);
                $.post(form.action, $(form).serialize(), function (result) {
                    if (result.code === 1) {
                        $('#coin-adjust-modal').modal('hide');
                        Toastr.success(result.msg);
                        location.reload();
                    } else {
                        Toastr.error(result.msg || '调整失败');
                    }
                }, 'json').fail(function () {
                    Toastr.error('请求失败');
                }).always(function () {
                    submit.prop('disabled', false);
                });
            });
        },
        ledger: function () {}
    };
    return Controller;
});

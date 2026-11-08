define(['jquery', 'backend', 'upload'], function ($, Backend, Upload) { return { index: function () { Upload.api.upload($('.faupload')); } }; });

//初始化fileinput
let FileInput = function () {
    this.is_uploaded = false;
    this.control = $();
    this.image_show = $();
    this.token = null;
};

FileInput.prototype.Init = function (ctrlName, uploadUrl, deleteUploadedUrl, showName, app=null, token=null) {
    self = this;
    //初始化fileinput控件（第一次初始化）
    this.control = $('#' + ctrlName);
    this.image_show = $('#' + showName);
    let initial_preview = [];
    let initial_preview_config = [];
    if (app) {
        app = JSON.parse(app);
        if (app.image !== undefined && app.image) {
            initial_preview.push(app.image);
            initial_preview_config.push({caption: app.name, size: 0, url: app.image, key: app.id});
            this.is_uploaded = true;
        }
    }
    this.token = token;

    //初始化上传控件的样式
    self.control.fileinput({
        language: 'zh', //设置语言
        uploadUrl: uploadUrl, //上传的地址
        allowedFileExtensions: ['jpg', 'gif', 'png'],//接收的文件后缀
        showUpload: true, //是否显示上传按钮
        showRemove: true, // 是否显示批量移除按钮
        showCaption: false,//是否显示标题
        browseClass: "btn btn-primary", //按钮样式
        // dropZoneEnabled: false,//是否显示拖拽区域
        // minImageWidth: 50, //图片的最小宽度
        // minImageHeight: 50,//图片的最小高度
        // maxImageWidth: 1000,//图片的最大宽度
        // maxImageHeight: 1000,//图片的最大高度
        // maxFileSize: 0,//单位为kb，如果为0表示不限制文件大小
        // minFileCount: 0,
        maxFileCount: 1, //表示允许同时上传的最大文件个数
        enctype: 'multipart/form-data',
        validateInitialCount: true,
        previewFileIcon: "<i class='glyphicon glyphicon-king'></i>",
        msgFilesTooMany: "选择上传的文件数量({n}) 超过允许的最大数值{m}！",
        initialPreview: initial_preview,
        initialPreviewConfig: initial_preview_config,
        initialPreviewAsData: true,
    }).on("fileuploaded", function (event, data) {
        $('img.file-preview-image').attr('src', data.response.result);
        self.is_uploaded = true;
        self.image_show.attr('value', data.response.result);
        self.control.closest('div.btn-file')
            .addClass('disabled')
            .closest('input.form-control')
            .attr('disabled', 'disabled');
    }).on('filepreremove', function (event, id, index) {
        // 未上传的图片删除
        // console.log(event);
    }).on('change', function () {
        $('a.fileinput-upload')
            .removeClass('disabled')
            .removeAttr('disabled');
    }).on('filebatchuploadcomplete', function () {
        self.control.closest('div.btn-file')
            .addClass('disabled')
            .closest('input.form-control')
            .attr('disabled', 'disabled');
    }).on('filesuccessremove', function (event) {
        self.deleteUploadedImage(event, deleteUploadedUrl);
    }).on('filecleared', function () {
        self.control.closest('div.btn-file')
            .removeClass('disabled')
            .closest('input.form-control')
            .removeAttr('disabled');
    }).on('fileclear', function (event) {
        if (!self.is_uploaded) {
            return true;
        }
        self.deleteUploadedImage(event, deleteUploadedUrl, function () {
            // 这里需要在回调之后再删除，否则会导致无限递归，内存溢出
            self.control.fileinput('clear');
        });
        return true;
    }).on('filebeforeclear', function (event) {
    }).on('filepredelete', function (event, key, jqXHR) {
        jqXHR.abort();
        self.is_uploaded = false
        self.image_show.attr('value', '');
        self.control.fileinput('clear');
    });
    if (app && app.image !== undefined && app.image) {
        self.control.closest('div.btn-file')
            .addClass('disabled')
            .closest('input.form-control')
            .attr('disabled', 'disabled');
        $('a.fileinput-upload')
            .addClass('disabled')
            .attr('disabled', 'disabled');
    }
}

FileInput.prototype.deleteUploadedImage = function (event, deleteUploadedUrl, callback = null, title = null, message = null) {
    event.preventDefault();
    confirmEx({
        title: title ? title : "删除已上传的图片",
        message: "<i class='bi bi-question-circle text-warning'></i>" + (message ? message : "是否删除已上传的图片?"),
        cancel: "取消",
        confirm: "确定",
        callback: (result) => {
            if (!result) {
                return;
            }
            let headers = {};
            if (this.token) {
                headers.Authorization = this.token;
            }
            this.control.fileinput('refresh');
            $.ajax({
                type: 'DELETE',
                contentType: 'application/json;charset=UTF-8',
                url: deleteUploadedUrl,
                headers,
                data: JSON.stringify({image_url: this.image_show.attr('value')}),
                success: (data) => {
                    if (data.code !== 0) {
                        messageEx(data.result, 'danger');
                    }
                    this.is_uploaded = false;
                    this.image_show.attr('value', '');
                    callback && callback();
                },
                error: (error) => {
                    console.error(error);
                }
            });
        }
    });
}

// FileInput.prototype.getImageUrl = function () {
//     if (this.is_uploaded) {
//         return this.image_url;
//     }
//     return null;
// }

let EmImg = ""; //定义初始头像  我这里定义为空

//初始化fileinput
let FileInput = function () {
    let oFile = {};

    //初始化fileinput控件（第一次初始化）
    oFile.Init = function (ctrlName, uploadUrl) {
        let control = $('#' + ctrlName);

        //初始化上传控件的样式
        control.fileinput({
            language: 'zh', //设置语言
            uploadUrl: uploadUrl, //上传的地址
            allowedFileExtensions: ['jpg', 'gif', 'png'],//接收的文件后缀
            showUpload: true, //是否显示上传按钮
            showCaption: false,//是否显示标题
            browseClass: "btn btn-primary", //按钮样式
            //dropZoneEnabled: false,//是否显示拖拽区域
            //minImageWidth: 50, //图片的最小宽度
            //minImageHeight: 50,//图片的最小高度
            //maxImageWidth: 1000,//图片的最大宽度
            //maxImageHeight: 1000,//图片的最大高度
            //maxFileSize: 0,//单位为kb，如果为0表示不限制文件大小
            //minFileCount: 0,
            maxFileCount: 1, //表示允许同时上传的最大文件个数
            enctype: 'multipart/form-data',
            validateInitialCount: true,
            previewFileIcon: "<i class='glyphicon glyphicon-king'></i>",
            msgFilesTooMany: "选择上传的文件数量({n}) 超过允许的最大数值{m}！",
        });

        //导入文件上传完成之后的事件
        $("#txt_file").on("fileuploaded", function (event, data, previewId, index) {

            data = data.response;
            let last = data.lastIndexOf("Upload");
            EmImg = data.substring(last + 7);
            //document.getElementById('videoForm').outerHtml = document.getElementById('videoForm').outerHtml;
            //document.getElementById("videoForm").reset();
            $("#Modal").modal('hide');
            $("#imgEmdImg").attr("src", "../FileUpload/Upload/" + EmImg);
            //alert(EmImg);
            // 1.初始化表格
            //var oTable = new TableInit();
            //oTable.Init(data);
        });
    }
    return oFile;
};

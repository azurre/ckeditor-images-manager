/**
 * @date    07.10.2016
 * @version 1.0
 * @author  Aleksandr Milenin admin@azrr.info
 */

var
    dir          = 'upload/',
    imageFullUrl = true;

function uploadByUrl(){
    var url;
    if (url = prompt('Enter image URL:')) {
        send({act: "upload", url: url}, function (response) {
            renderImages(response.list);
        });
    }
}


function sendFile(file, onComplete) {
    var data = {
        act: "upload",
        file: file
    };

    send(data, onComplete);
}

function send(data, onComplete) {
    var formData = new FormData();
    for (var key in data) {
        formData.append(key, data[key]);
    }
    //fd.append( 'file', input.files[0] );

    $.ajax({
        url: "api.php",
        data: formData,
        processData: false,
        contentType: false,
        type: "POST",
        complete: function (response) {
            if (response.responseJSON.error) {
                if (errorHandler.apply(this, [response])){
                    return;
                }
            }

            if (onComplete) {
                onComplete.apply(this, [response.responseJSON]);
            }
        }
    });
}


function createImageBox(image) {
    var
        //path = document.location.protocol + "//" + document.location.host + "/" + dir + image.name,
        path = dir + image.name,
        D = new Date(image.date * 1000),
        size = (image.size / 1024).toFixed(2) + " KB",
        date = D.toLocaleTimeString() + ' ' + D.toLocaleDateString(),
        res = image.width + "x" + image.height;

    return '<div class="image-box">' +
                '<div class="header clearfix">' +
                    '<div class="ico">' + image.ext + '</div>' +
                    '<div class="name" title="' + image.name + '">' + image.name + '</div>' +
                '</div>' +
                '<div class="image">' +
                    '<img src="' + path + '" alt="" />' +
                '</div>' +
                '<div class="footer">' +
                    '<div class="clearfix">' +
                        '<div class="date">' + date + '</div>' +
                        '<div class="size">' + size + ' (' + res + ')</div>' +
                    '</div>' +
                    '<div class="actions">' +
                        '<a class="btn down" href="' + path + '" title="Download image" download></a>' +
                        '<a class="btn insert" title="Insert image"></a>' +
                        '<a class="btn delete" title="Delete image"></a>' +
                    '</div>' +
                '</div>' +
            '</div>'
}

function getUrlParam(paramName) {
    var reParam = new RegExp('(?:[\?&]|&)' + paramName + '=([^&]+)', 'i');
    var match = window.location.search.match(reParam);

    return ( match && match.length > 1 ) ? match[1] : null;
}

function useImage(imgSrc) {
    var funcNum = getUrlParam('CKEditorFuncNum');
    window.opener.CKEDITOR.tools.callFunction(funcNum, imgSrc);
    window.close();
}

function deleteImageHandler( e ) {
    /**
     * @type {jQuery}
     */
    var $link = $(e.currentTarget);

    var data = {
        act: "delete",
        file: e.data.file
    };

    send(data, function(response){
        if(!response.error) {
            $link.closest(".image-box").remove();
        }
    });
}

function addImageBox(image, $list) {
    var $imageBox = $(createImageBox(image));
    $imageBox.prependTo($list);

    $imageBox.find(".insert").on("click", function () {
        var src = $(this).closest(".image-box").find("img").prop("src");
        useImage( imageFullUrl ? src : src.replace(/^.*\/\/[^\/]+/, '') );
    });
    $imageBox.find(".delete").on("click", {file: image.name}, deleteImageHandler);
}

function renderImages(images) {
    var $list = $(".files");
    for (var key in images) {
        addImageBox(images[key], $list);
    }
}

var showDropzone = function () {
    $("#dropzone").show();
    $("#content").hide();
};

var hideDropzone = function () {
    //console.log('dragleave');
    $("#dropzone").hide();
    $("#content").show();

    $(document).off("dragleave", hideDropzone);
};

function loadImages(callback){
    var $list = $(".files");

    send({act: "get-images"}, function (response) {
        dir = response.dir;
        imageFullUrl = response.imageFullUrl;

        $list.empty();
        for (var key in response.list) {
            addImageBox(response.list[key], $list);
        }

        if(callback) {
            callback.apply(this, [response]);
        }
    });
}

function authError(){
    $("#popup").html("<h1>Auth fail</h1>").show();
    $("#main").hide();

}

function errorHandler(response){
    switch (response.responseJSON.error){
        default:
            alert( "Error: " + response.responseJSON.error );
            break;
        case 1: authError(); break;
    }
    return true;
}

$(document).ready(function () {

    loadImages(function(){
        $("#popup").hide();
        $("#main").show();
    });

    $("#uploadForm").on("change", function () {
        var
            _this = this,
            file = $('#file')[0].files[0];

        sendFile(file, function (response) {
            renderImages(response.list);
            _this.reset();
        });
    });

    // hide dropzone if user clicks close
    $("#dropzone").click(function () {
        setTimeout(function () {
            hideDropzone('hide');
        }, 500);
    });

    $(document)
        .on('drag dragstart dragend dragover dragenter dragleave drop', function (e) {
            // preventing the unwanted behaviours
            e.preventDefault();
            e.stopPropagation();
        })
        .on('dragenter', function () {
            //console.log('dragenter');
            showDropzone();

            setTimeout(function () {
                $(document).on('dragleave', hideDropzone);
            }, 100);
        })
        .on('drop', function (e) {
            hideDropzone();
            var droppedFiles = e.originalEvent.dataTransfer.files; // the files that were dropped
            for (var key in droppedFiles) {
                //doesn't work for IE :(
                //  if ( !droppedFiles.hasOwnProperty(key) ) { continue; }
                // so check like this:
                if ( parseInt(key) != key ) { continue; }

                if (droppedFiles[key].type.match("image/*")) {
                    sendFile(droppedFiles[key], function (response) {
                        renderImages(response.list);
                    });
                }
            }
        });
});
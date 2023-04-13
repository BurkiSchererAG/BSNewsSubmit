$(document).ready(function () {

    window.makeSortable = function ($id) {
        $sortableEle = $('#' + $id + '__sort');

        //Jquery UI, not touch compatible
        // $sortableEle.sortable({
        //     stop: function(event, ui) {
        //         upDateUuid($(this));
        //     }
        // });

        //Better
        htmlele = $sortableEle[0];
        if (htmlele !== undefined) {
            new Sortable(htmlele, {
                onEnd: function (event) {
                    upDateUuid($sortableEle);
                }
            });
        }

        makeFileSelectable($sortableEle);
        makeFileRemovable($sortableEle);
        updateUploadLinkAndCounter($id);
    }

    window.makeSelectable = function ($id) {
        $selectableEle = $('#' + $id + '__select');
        makeFileSelectable($selectableEle);
        makeFileRemovable($selectableEle);
        updateUploadLinkAndCounter($id);
    }

    window.activeMetaModelFileFunction = function ($id) {
        makeSortable($id);
        makeSelectable($id);
    }


    /* make also teaser/enclouser removeable */
    $parentEle = $('.widget-upload');
    makeFileRemovable($parentEle);
    /* make also teaser removeable ends */

    /* Below are helper */

    function upDateUuid($parentEle) {
        var els = [],
            i,
            lis = $parentEle.children('li'),
            parentEleId = $parentEle.attr('id'),

            targetEleId = '#ctrl_' + parentEleId,
            $targetEle = $(targetEleId);

        for (i = 0; i < lis.length; i++) {
            els.push($(lis[i]).data('id'));
        }

        $targetEle.val(els.join(','));
    }

    function makeFileRemovable($parentEle) {
        lis = $parentEle.children('li, span.button');
        lis.on('click', function (event) {
            event.preventDefault();
            $deleteBtn = $(event.target);
            del_filename = $(this).find('a').text() || $(this).find('img').attr('title').split(/(\\|\/)/g).pop();
            confirmAction = confirm("Want to delete? " + del_filename);

            if (confirmAction && $deleteBtn.hasClass('delete')) {
                objData = {
                    'pid': $(this).parent('ul').parent('div').attr('id'),
                    'uuid': $(this).data('id')
                };

                $(this).remove();
                upDateUuid($parentEle)
                removeFile(objData);
            }
        });
    }

    function makeFileSelectable($parentEle) {
        $parentEle.on(
            'click',
            function (event) {
                event.stopPropagation();

                $selectBtn = $(event.target);
                if ($selectBtn.hasClass('select')) {
                    if ($parentEle.hasClass('selectable')) {
                        $parentEle.children('li').removeClass('selected');
                    }

                    if ($(event.target).parent('li').hasClass('selected')) {
                        $(event.target).parent('li').removeClass('selected');
                    } else {
                        $(event.target).parent('li').addClass('selected');
                    }

                }
                upDateUuid($parentEle);
            }
        );

    }

    function removeFile(objData) {
        $.ajax({
            method: 'post',
            url: '/file_selection',
            data: {
                'uuid': objData.uuid,
                'field': objData.pid
            }
        })
            .done(function (data) {
                //update fqouta, data-file_qouta
                updateUploadLinkAndCounter(objData.pid, 'plus');
            });
    }

    window.updateUploadLinkAndCounter = function (eleIdStr, mode) {
        var $fileCounter = $('#' + eleIdStr);
        var newQoutaCount = $fileCounter.data("file_maxqouta");
        var itemCount = $fileCounter.find('li').length;

        //Update the upload count.

        if (mode == 'plus') {
            newQoutaCount = $fileCounter.data("file_qouta") + 1;
        }
        else {
            newQoutaCount = $fileCounter.data("file_maxqouta") - itemCount;
        }

        if (newQoutaCount < 0) {
            newQoutaCount = 0;
        }

        $fileCounter.data("file_qouta", newQoutaCount);
        $fileCounter.siblings("p.hint").find("#fqouta").text(newQoutaCount);

        if (newQoutaCount > 0) {
            $fileCounter.siblings("a.uploadBtn").removeClass("disabled");
        } else {
            $fileCounter.siblings("a.uploadBtn").addClass("disabled");
        }

    }

});

//UPLOAD Button
$(document).ready(function () {
    var func = function (e) {
        var ele = $('#mmEditElement').data('eleid'); //Why is this not string?????
        var id = $('#mmEditElement').data('urlid');
        e.preventDefault();

        if ($(this).hasClass('disabled')) {
            $.colorbox({
                className: 'fileUpload', width: '50%',
                html: '<p class="info">Delete some file to upload here</p>'
            });
            return;
        }

        $.ajax({
            method: 'get',
            url: $(this).attr('href'),
            data: {
                'ele': ele,
                'id': id
            }
        })
            .done(function (data) {
                $.colorbox({ className: 'fileUpload', width: '80%', height: '300px', html: data });
            });
    };

    $('a.ajax').on('click', func);
});



$(document).ready(function () {

});

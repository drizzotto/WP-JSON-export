"use strict";

jQuery(document).ready(function ($) {
    let toastContainer = document.querySelector('.toast-container');
    accordion();
    $(".checked-text input[type=text],.checked-text input[type=checkbox]").on('change', function(evt) {
        let value = $(this).parent().children('input[type=hidden]').val();
        let valueObj = (value!=="") ? JSON.parse(value) : {};
        let field = evt.target.id;
        if (($(this).attr('type')==='checkbox')) {
            valueObj['enabled'] = $(this).is(':checked');
        } else {
            valueObj['value'] = evt.target.value;
        }
        $(this).parent().children('input[type=hidden]').val(JSON.stringify(valueObj));
    });

    $( ".bucket, .path, .enabled" ).on( "change", function( event ) {
        let settings = $("#post_jsoner_s3_settings").val();
        let settingObj = (settings!=="") ? JSON.parse(settings) : {};
        let field = event.target.id;
        settingObj[field] = ($(this).attr('type')==='checkbox')
            ? $(this).is(':checked')
            : event.target.value;
        $("#post_jsoner_s3_settings").val(JSON.stringify(settingObj));
    });

    $('#site').on('change', function () {
        let options = $('#sites')[0].options;
        let val = $(this).val();
        for (let i=0;i<options.length;i++){
            if (options[i].value === val) {
                $('#site-id').val($(options[i]).data("id"));
                break;
            }
        }
    });

    function toggleWait() {
        const w = $(".wait.mask");
        const p = $(".progress-gauge");
        console.log(w.is(":visible"));
        if (w.is(":visible")===true) {
            setTimeout(function () {
                p.hide();
                w.hide();
            },3000,{});
        } else {
            w.show();
            p.show();
        }
    }

    function callBulkExport(event) {
        let offset = $("#offset").val();
        const step = 5;

        if (offset===-1) {
            $('#offset').val(0);
            return true;
        }
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            async: true,
            data: {
                action: 'jsoner_bulk',
                page: 'post_jsoner',
                offset: offset,
                step: step
            },
            dataType: "json"
        })
            .done(function (response) {
                toggleWait();
                if (response['success']) {
                    let next = response['next'];
                    $('#offset').val(next);

                    let message = (response['is_multisite'])
                      ? "The request was successful <br>" + (parseInt(offset) + parseInt(response['processed'])) + " sites exported"
                      : "The request was successful <br> Site exported"
                    ;
                    generateToast({
                        toastContainer: toastContainer,
                        message: message,
                        background: "hsl(171 100% 46.1%)",
                        color: "hsl(171 100% 13.1%)",
                        length: "5000ms",
                    });
                    if (response['next'] > -1) {
                        callBulkExport(event);
                    }
                } else {
                    $('#offset').val(-1);
                    generateToast({
                        toastContainer: toastContainer,
                        message: '<h2>Something went wrong.</h2><br>'+response['errors'],
                        background: "hsl(350 100% 66.5%)",
                        color: "hsl(350 100% 13.5%)",
                        length: "5000ms",
                    });
                }
            })
            .fail(function (response) {
                toggleWait();
                $('#offset').val(-1);
                generateToast({
                    toastContainer: toastContainer,
                    message: '<h2>Something went wrong.</h2><br>'+response['errors'],
                    background: "hsl(350 100% 66.5%)",
                    color: "hsl(350 100% 13.5%)",
                    length: "5000ms",
                });
            })
            .always(function (response) {
                toggleWait();
                if (response['next'] === -1) {
                    $('#offset').val(0);
                }
                event.target.reset();
            })
        ;
    }
    /**
     * The file is enqueued from inc/admin/class-admin.php.
     */
    $(document).on('submit','#jsoner-bulk-export-form',function (event) {
        event.preventDefault(); // Prevent the default form submit.
        event.stopPropagation();
        $("wait").show();
        callBulkExport(event);
    });

    $(document).on('submit','#jsoner-site-export-form', function (event) {
        event.preventDefault(); // Prevent the default form submit.
        event.stopPropagation();
        toggleWait();
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            async: true,
            data: {
                action: 'jsoner_site',
                page: 'post_jsoner',
                site: $('#site').val(),
                site_id: $('#site-id').val()
            },
            dataType: "json"
        })
            .done(function (response) {
                toggleWait();
                if (response['success']) {
                    generateToast({
                        toastContainer: toastContainer,
                        message: '<h2>Something went wrong.</h2><br>'+response['errors'],
                        background: "hsl(350 100% 66.5%)",
                        color: "hsl(350 100% 13.5%)",
                        length: "5000ms",
                    });
                } else {
                    generateToast({
                        toastContainer: toastContainer,
                        message: '<h2>Something went wrong.</h2><br>'+response['errors'],
                        background: "hsl(350 100% 66.5%)",
                        color: "hsl(350 100% 13.5%)",
                        length: "5000ms",
                    });
                }
            })
            .fail(function (response) {
                toggleWait();
                generateToast({
                    toastContainer: toastContainer,
                    message: '<h2>Something went wrong.</h2><br>'+response['errors'],
                    background: "hsl(350 100% 66.5%)",
                    color: "hsl(350 100% 13.5%)",
                    length: "5000ms",
                });
            })
            .always(function () {
                $("wait").hide();
                event.target.reset();
            })
        ;
    });
});

function generateToast({   toastContainer,
                           message,
                           background = '#00214d',
                           color = '#fffffe',
                           length = '3000ms',
                       }){

    toastContainer.insertAdjacentHTML('beforeend', `<p class="toast" 
    style="background-color: ${background};
    color: ${color};
    animation-duration: ${length}">
    ${message}
  </p>`)
    const toast = toastContainer.lastElementChild;
    toast.addEventListener('animationend', () => toast.remove())
}

function accordion() {
    const acc = document.getElementsByClassName("accordion");
    let index;

    console.log("acc", acc);

    for (index = 0; index < acc.length; index++) {
        acc[index].addEventListener("click", function() {
            /* Toggle between adding and removing the "active" class,
            to highlight the button that controls the panel */
            this.classList.toggle("active");

            /* Toggle between hiding and showing the active panel */
            const panel = this.nextElementSibling;
            if (panel.style.display === "block") {
                panel.style.display = "none";
            } else {
                panel.style.display = "block";
            }
        });
    }
}
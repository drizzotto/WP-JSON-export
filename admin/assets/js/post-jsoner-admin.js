"use strict";
jQuery(document).ready(function ($) {
    $( "#progressbar" ).progressbar({
        value: false
    });
    $( function() {
        $( "#accordion" ).accordion();
    } );
    $('.wait').hide();

    $( ".bucket, .path, .enabled" ).on( "change", function( event ) {
        let settings = $('#post_jsoner_s3_settings').val();
        let settingObj = (settings!=="") ? JSON.parse(settings) : {};
        let field = event.target.id;
        let newVal = ($(this).attr('type')==='checkbox')
            ? $(this).is(':checked')
            : event.target.value;
        settingObj[field] = newVal;
        $('#post_jsoner_s3_settings').val(JSON.stringify(settingObj));
    });

    $('#site').change(function () {
        let options = $('#sites')[0].options;
        let val = $(this).val();
        for (let i=0;i<options.length;i++){
            if (options[i].value === val) {
                $('#site-id').val($(options[i]).data("id"));
                break;
            }
        }
    });

    function callBulkExport(event) {
        let offset = $('#offset').val();
        const step = 5;

        $('.wait').show();
        if (offset===-1) {
            $('.wait').hide();
            $('#offset').val(0);
            return true;
        }
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            async: true,
            data: {
                action: 'jsoner_bulk',
                offset: offset,
                step: step
            },
            dataType: "json"
        })
            .done(function (response) {
                if (response['success']) {
                    let next = response['next'];
                    $('#offset').val(next);

                    let message = (response['is_multisite'])
                      ? "The request was successful <br>" + (parseInt(offset) + parseInt(response['processed'])) + " sites exported"
                      : "The request was successful <br> Site exported"
                    ;
                    $.toast({
                        heading: 'Success',
                        text: message,
                        showHideTransition: 'slide',
                        icon: 'success'
                    })
                    if (response['next'] > -1) {
                        $('.wait').show();
                        callBulkExport(event);
                    }
                } else {
                    $('#offset').val(-1);
                    $.toast({
                        heading: 'Error',
                        text: '<h2>Something went wrong.</h2><br>'+response['errors'],
                        showHideTransition: 'slide',
                        icon: 'error'
                    });
                }
            })
            .fail(function (response) {
                $('#offset').val(-1);
                $.toast({
                    heading: 'Error',
                    text: '<h2>Something went wrong.</h2><br>'+response['errors'],
                    showHideTransition: 'slide',
                    icon: 'error'
                });
            })
            .always(function (response) {
                if (response['next'] === -1) {
                    $('.wait').hide();
                    $('#offset').val(0);
                }
                event.target.reset();
            })
        ;
    }
    /**
     * The file is enqueued from inc/admin/class-admin.php.
     */
    $('#jsoner-bulk-export-form').submit(function (event) {
        event.preventDefault(); // Prevent the default form submit.
        event.stopPropagation();
        $('.wait').show();
        callBulkExport(event);
    });

    $('#jsoner-site-export-form').submit(function (event) {
        event.preventDefault(); // Prevent the default form submit.
        event.stopPropagation();
        $('.wait').show();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            async: true,
            data: {
                action: 'jsoner_site',
                site: $('#site').val(),
                site_id: $('#site-id').val()
            },
            dataType: "json"
        })
            .done(function (response) {
                if (response['success']) {
                    $.toast({
                        heading: 'Error',
                        text: '<h2>Something went wrong.</h2><br>'+response['errors'],
                        showHideTransition: 'slide',
                        icon: 'error'
                    });
                } else {
                    $.toast({
                        heading: 'Error',
                        text: '<h2>Something went wrong.</h2><br>'+response['errors'],
                        showHideTransition: 'slide',
                        icon: 'error'
                    });
                }
            })
            .fail(function (response) {
                $.toast({
                    heading: 'Error',
                    text: '<h2>Something went wrong.</h2><br>'+response['errors'],
                    showHideTransition: 'slide',
                    icon: 'error'
                });
            })
            .always(function () {
                $('.wait').hide();
                event.target.reset();
            })
        ;
    });
});
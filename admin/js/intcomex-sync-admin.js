(function ($) {
    'use strict';

    jQuery(document).ready(function ($) {
        $('#down_csv').click(function (e) {
            e.preventDefault();
            // $('#progress_bar').show();
            $('#loader').show();
            let data = {
                action: 'download_csv'
            };
            $.ajax({
                type: 'POST',
                url: intcomex.ajax_url,
                data: data,
                success: function (response) {
                    console.error(response);
                    $('#loader').hide();
                    if (response.success) {
                        // $('#progress').width('100%');
                        alert(response.message);
                        console.log(response.message);
                    } else {
                        // $('#progress').width('50%');
                        alert(response.message);
                        console.error(response.message);
                    }
                },
            });

            // e.stopImmediatePropagation();
            // e.preventDefault();
            // $('#loader').show();
            //
            // let data = {
            //     action: 'download_csv'
            // };
            // jQuery.post(
            //     intcomex.ajax_url,
            //     data,
            //     async function (response) {
            //         $('#loader').hide();
            //
            //         if ( response.success ) {
            //         	$( '#loader' ).hide();
            //         	console.log( response.data );
            //             window.location = document.location.href;
            //         }
            //         else {
            //             $('#loader').hide();
            //         }
            //     }
            // );
        });

        $('#down_json').click(function (e) {
            e.stopImmediatePropagation();
            e.preventDefault();
            $('#loader3').show();

            let data = {
                action: 'get_api_json'
            };
            jQuery.post(
                intcomex.ajax_url,
                data,
                async function (response) {
                    if (!response.success) {
                        $('#loader3').hide();
                        console.log(response.data);
                    } else {
                        window.location = document.location.href;
                    }
                }
            );
        });

        $('#import_button').click(function (e) {
            e.stopImmediatePropagation();
            e.preventDefault();
            $('#import_button').hide();
            $('#loader2').show();
            let data = {
                action: 'importar'
            };
            jQuery.post(
                intcomex.ajax_url,
                data,
                async function (response) {
                    console.log(response);
                    $('#loader2').hide();
                    $('#import_button').show();
                }
            )
        });

        $(document).on(
            "click",
            ".up_prd_button",
            function (e) {
                e.stopImmediatePropagation();
                e.preventDefault();

                let postId = e.target.id;
                let productSku = $(this).attr("prd_sku");

                $(this).hide();
                $('#syncLoading' + postId).show();

                let data = {
                    action: 'intcomex_update_product_stock',
                    prod_sku: productSku
                };

                jQuery.post(
                    intcomex.ajax_url,
                    data,
                    async function (response) {
                        window.location = document.location.href;
                    }
                );
            }
        );
    });


})(jQuery);

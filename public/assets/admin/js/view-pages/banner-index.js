"use strict";
function readURL(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function (e) {
            $('#viewer').attr('src', e.target.result);
        }
        reader.readAsDataURL(input.files[0]);
    }
}

$("#customFileEg1").change(function () {
    readURL(this);
});

var zone_id = [];

$(document).on('ready', function () {
    $('#zone').on('change', function(){
        if($(this).val())
            {
            zone_id = $(this).val();
            get_items();
        }
        else
        {
            zone_id = [];
        }
    });
    
    
    // INITIALIZATION OF SELECT2
    // =======================================================
    $('.js-select2-custom').each(function () {
        var select2 = $.HSCore.components.HSSelect2.init($(this));
    });
});

$('#item_wise,#keyword').hide();
$('#default').hide();


$('#banner_type').on('change', function () {
    let order_type = $(this).val();
    if (order_type == 'item_wise') {
        $('#store_wise').hide();
        $('#item_wise').show();
        $("#keyword").hide();
        $('#default,#category_wise_wrapper').hide();
        
    } else if (order_type == 'store_wise') {
        $('#store_wise').show();
        $("#keyword").hide();
        $('#item_wise').hide();
        $('#default,#category_wise_wrapper').hide();
    } else if (order_type == 'default') {
        $('#default').removeClass('d-none').show();
        $('#store_wise').hide();
        $('#item_wise').hide();
        $("#keyword,#category_wise_wrapper").hide();
    } else if (order_type == 'keyword') {
        $('#keyword').removeClass('d-none').show();
        $('#store_wise').hide();
        $('#item_wise').hide();
        $('#default,#category_wise_wrapper').hide();
    }
    else if (order_type === 'category_wise') {
        $('#category_wise_wrapper').removeClass('d-none').show();
          $('#item_wise').hide();
        $('#store_wise').hide();
        $('#default').hide();
        $("#keyword").hide();
    }
    
    
    else {
        $('#item_wise').hide();
        $('#store_wise').hide();
        $('#default').hide();
        $("#keyword").hide();
        
    }
    
})

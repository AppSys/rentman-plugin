// ----- JavaScript functions for product deletion ----- \\
// Show delete message in the menu
jQuery().ready(function()
{
    jQuery("#deleteStatus").html(string1 + "0%");
    applyAjax();
});

// Recursive function that sends product indices to PHP until the
// whole array has been covered
function applyAjax(){
    jQuery.ajax({
        type: "POST",
        url: 'admin.php?page=rentman-shop&delete_products',
        datatype: "json",
        data: JSON.stringify({ prod_array : products, array_index : arrayindex }),
        contentType: 'application/json; charset=utf-8',
        success: function(){
            var endindex = parseInt(arrayindex) + 10;
            if (endindex > products.length)
                endindex = products.length;
            if (products.length == 0)
                var percentage = 100;
            else
                var percentage = Math.round((endindex / products.length) * 100);
            jQuery("#deleteStatus").html(string1 + percentage + '%');
            arrayindex = parseInt(arrayindex) + 10;
            if (arrayindex < products.length){
                applyAjax();
            } else {
                removeFolders();
            }
        }
    });
}

// Calls PHP function that removes all empty product categories from WooCommerce
function removeFolders(){
    jQuery.ajax({
        type: "GET",
        url: 'admin.php?page=rentman-shop&remove_folders',
        data: '',
        success: function(){
            jQuery("#deleteStatus").append(string2);
        }
    });
}

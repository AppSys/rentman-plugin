// ----- JavaScript functions for product import ----- \\
// Show import message in the menu
var taxwarning = "";
jQuery().ready(function(){
    jQuery("#importMelding").show();
    jQuery(".lasttime").html(pluginlasttime);
    rentman_check_for_updates(0,skus);
});
function rentman_check_for_updates(arrayindex,skus){
    var skukeys = new Array();
    var endindex = parseInt(arrayindex) + 800;
    var skuslength = skus.length;
    var lastkey = skus[skus.length-1];
    if (endindex > skuslength){
      endindex = skuslength;
    }
    for (i = arrayindex; i < endindex; i++){
      skukeys.push(skus[i]);
    }
    jQuery("#importStatus").html(string5 + arrayindex + "-" + endindex +  " / " + skus.length);
    jQuery.ajax({
        type: "POST",
        url: 'admin.php?page=rentman-shop&check_for_updates',
        datatype: "json",
        data: JSON.stringify({ keys : skukeys, array_index : arrayindex, last_key : lastkey }),
    	   contentType: 'application/json; charset=utf-8',
         success: function(){
            if (endindex < skuslength){
                rentman_check_for_updates(endindex,skus);
            } else {
                jQuery("#importStatus").html(string5 + skus.length +  " / " + skus.length);
                rentman_do_import();
            }
        }
    });
}

function rentman_do_import(){
    jQuery.ajax({
        url: ajaxurl, // or example_ajax_obj.ajaxurl if using on frontend
        data: {
            'action': 'rentman_start_import'
        },
        success:function(data) {
            if(data["products"] > 0){
              taxwarning = data["taxwarning"];
              jQuery("#importStatus").html(string1 + "0 / " + parseInt(data["products"]));
              jQuery("#rentman_products_to_import").text(tablabelimport1 + " (" + parseInt(data["products"]) + ")");
              jQuery("#rentman_actual_import").text(tablabelimport2 + " (0/" + parseInt(data["products"]) + ")" );
              jQuery("#rentman_import").text(tablabel3 + " (0/" + parseInt(data["products"]) + ")" );
              startImportRentman(0,data["products"],data["uploadbatch"]);
            }else{
              jQuery("#importMelding").html("<h3>" + string6 + "</h3>");
              jQuery("#importStatus").html("");
            }
        },
        dataType:"json"
    })
}

// Recursive function that sends product indices to PHP until the
// whole array has been covered
function startImportRentman(arrayindex,products,uploadbatch){
    jQuery.ajax({
        type: "POST",
        url: 'admin.php?page=rentman-shop&import_products',
        datatype: "json",
        //data: JSON.stringify({ file_array : folders, array_index : arrayindex, prod_array : products, pdf_array : pdfs, basic_to_advanced: basictoadvanced, upload_batch: uploadbatch}),
        data: JSON.stringify({ array_index : arrayindex, upload_batch: uploadbatch}),
    	  contentType: 'application/json; charset=utf-8',
        success: function(data){            
            var endindex = parseInt(arrayindex) + parseInt(uploadbatch);
            if (endindex > parseInt(products))
                endindex = parseInt(products);
            jQuery("#importStatus").html(string1 + endindex + " / " + parseInt(products));
            jQuery("#rentman_products_to_import").text(tablabelimport1 + " (" + parseInt(products) + ")");
            jQuery("#rentman_actual_import").text(tablabelimport2 + " (" + endindex + "/" + parseInt(products) + ")" );
            jQuery("#rentman_import").text(tablabel3 + " (" + endindex + "/" + parseInt(products) + ")" );
            arrayindex = parseInt(arrayindex) + parseInt(uploadbatch);
            //showImportLog();
            if (arrayindex < parseInt(products)){
                startImportRentman(arrayindex,products,uploadbatch);
            } else {
                if(basictoadvanced == 2) {
                  basictoAdvancedFirstImport();
                }
                removeFolders();
            }
        }
    });
}

function basictoAdvancedFirstImport(){
    jQuery.ajax({
        type: "GET",
        url: 'admin.php?page=rentman-shop&basic_to_advanced',
        data: '',
        success: function(){
            //console.log("First import from basic to advanced done");
        }
    });
}

// Calls PHP function that removes all empty product categories from WooCommerce
function removeFolders(){
    jQuery("#importStatus").html(string2);
    jQuery("#taxWarning").html("<br>" + taxwarning);
    jQuery.ajax({
        type: "GET",
        url: 'admin.php?page=rentman-shop&remove_folders',
        data: '',
        success: function(){
            jQuery("#deleteStatus").html(string3);
            jQuery("#importStatus").html("");
            jQuery("#importMelding").html(string4);
        }
    });
}

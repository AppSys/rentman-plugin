// ----- JavaScript functions for tabs in wp-admin ----- \\
// Make the tabs accessible without reloading the page
var debugloglength = 0;
var rentmanactivetab = "#rentman_products_to_import_log";
jQuery().ready(function(){
    jQuery(document).ready(function(){
      jQuery('.venobox').venobox();
    });
    jQuery(".nav-tab-wrapper a.nav-tab").click(function() {
        jQuery(".nav-tab-wrapper a.nav-tab").removeClass('nav-tab-active');
        jQuery(this).addClass('nav-tab-active');
        divid = "#rentman-" + jQuery(this).attr('href').replace("#", "");
        jQuery('#rentman-login,#rentman-settings,#rentman-import,#importlog,#rentman-importlog').hide();
        jQuery(divid).show();
    });

    jQuery(".nav-tab-wrapper-import a.nav-tab").click(function(e) {
        if(jQuery("#rentman_import_refresh").is(':hidden')){
          jQuery(".nav-tab-wrapper-import a.nav-tab").removeClass('nav-tab-active');
          jQuery(this).addClass('nav-tab-active');
          jQuery('#rentman_products_to_import_log,#rentman_actual_import_log,#rentman_errors_log').hide();
          rentmanactivetab = "#" + jQuery(this).attr('id') + "_log";
          jQuery("#" + jQuery(this).attr('id') + "_log").show();
        }else{
          e.preventDefault();
        }
    });

    jQuery("#rentman_importlog").click(function() {
        if(jQuery("#rentman_import_refresh").is(':hidden')){
          jQuery('#rentman_products_to_import_log,#rentman_actual_import_log,#rentman_errors_log').hide();
          jQuery('#rentman_import_refresh').show();
          showImportLog();
        }
    });

    jQuery("#activate-account").click(function() {      
      jQuery('<form>', {
          'action': 'https://license.appsysit.be',
          'target': '_top',
          'method': 'POST'
      }).append(jQuery('<input>', {
          'name': 'jwt',
          'value': jQuery('#plugin-rentman-jwt-appsys').val(),
          'type': 'hidden'
      })).appendTo('body').submit().remove();
    });
});

function showImportLog(){
    jQuery.ajax({
        url: ajaxurl, // or example_ajax_obj.ajaxurl if using on frontend
        data: {
            'action': 'rentman_get_import_log',
            'lastline' : debugloglength
        },
        success:function(data) {
          var rentmanimportlog = data.split("log_length = ");
          debugloglength = rentmanimportlog[1];
          if(rentmanimportlog[0].indexOf('---ACTUAL IMPORT---<br>') > 0){
            var rentmanimportlog = rentmanimportlog[0].split("---ACTUAL IMPORT---<br>");
            jQuery("#rentman_products_to_import_log").append(rentmanimportlog[0]);
            jQuery("#rentman_actual_import_log").append(rentmanimportlog[1]);
          }else{
            if(rentmanimportlog[0] == "no log found"){
              jQuery("#rentman_products_to_import_log").append(rentmanimportlog[0]);
            }
            jQuery("#rentman_actual_import_log").append(rentmanimportlog[0]);
          }
          jQuery(rentmanactivetab).show();
          jQuery('#rentman_import_refresh').hide();
          showWarningLog();
        },
        dataType:"json"
    })
}

function showWarningLog(){
    jQuery("#rentman_errors_log").html("");
    var rentmanerrorcount = 0;
    var rentmanerrormessage = "";
    var rentmanlog = jQuery("#rentman_actual_import_log").html().split("<br>");
    var rentmanloglength = rentmanlog.length;
    var rentmanloghulpvar = "";
    var rentmanlogtoarray = new Array();
    for (i = 0; i < rentmanloglength; i++) {
      if(i == 0){
        rentmanloghulpvar=rentmanlog[i] + "<br>";
      }else{
        if(rentmanlog[i].substring(0,30) == "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"){
          rentmanloghulpvar+=rentmanlog[i] + "<br>";
        }else{
          rentmanlogtoarray.push(rentmanloghulpvar);
          rentmanloghulpvar=rentmanlog[i] + "<br>";
        }
      }
    }
    for (i = 0; i < rentmanlogtoarray.length; i++) {
      if(rentmanlogtoarray[i].indexOf("Rentman image doesn't exist on amazon") >= 0){
        rentmanerrorcount++;
        rentmanerrormessage+= "&#x26a0; " + warning1;
      }
      if(rentmanlogtoarray[i].indexOf("This file format is not supported") >= 0){
        rentmanerrorcount++;
        rentmanerrormessage+= "&#x26a0; " + warning2;
      }
      if(rentmanerrormessage != ""){
          jQuery("#rentman_errors").html(tablabelimport3 + " (<span style='color:#ca4a1f;'>" + rentmanerrorcount + "</span>)");
          jQuery("#rentman_errors_log").append(rentmanlogtoarray[i] + rentmanerrormessage + "<hr>");
          rentmanerrormessage = "";
      }
    }
    if(rentmanerrorcount == 0){
      jQuery("#rentman_errors_log").append(nowarningfound);
    }
}

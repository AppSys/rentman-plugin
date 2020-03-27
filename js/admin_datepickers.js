// ----- JavaScript functions for updating the datepickers ----- \\
// Attach function to #changePeriod button
var rentmandisableddays = "";
jQuery().ready(function() {
  if (typeof subtotalTextoverwrite !== 'undefined') {
    subtotalText = subtotalTextoverwrite;
  }
  jQuery("table tr.cart-subtotal>th:first").html(subtotalText);
  var rentmandatepickerformat = datePickerFormat;
  var rentmandatepickerlng = jQuery("#start-date").attr("data-language");

  jQuery( document.body ).on( 'updated_cart_totals', function(){
      jQuery("table tr.cart-subtotal>th:first").html(subtotalText);
      createDatePicker(rentmandatepickerformat,rentmandatepickerlng);
  });
  createDatePicker(rentmandatepickerformat,rentmandatepickerlng);
});

function createDatePicker(rentmandatepickerformat,rentmandatepickerlng){
  // Check if rentmandisableddaysoverwrite is defined
  // Used for disabling specific days of the week, for instance weekend days ( var rentmandisableddaysoverwrite = [0, 6]; )
  if (typeof rentmandisableddaysoverwrite !== 'undefined') {
    rentmandisableddays = rentmandisableddaysoverwrite;
  }

  jQuery('#start-date, #end-date').datepicker({
    minDate: changeDateformatMin(jQuery("#start-date").attr( "min" )),
    language: rentmandatepickerlng,
    autoClose: true,
    dateFormat: rentmandatepickerformat,
    moveToOtherMonthsOnSelect: true,
    onSelect: function (fd, d, picker) {
      startdate = changeDateformat(jQuery('#start-date').val());
      enddate = changeDateformat(jQuery('#end-date').val());
      if(startdate > enddate){
        enddateyear = parseInt(String(startdate).substring(0,4));
        enddatemonth = parseInt(String(startdate).substring(4,6)) - 1;
        enddateday = parseInt(String(startdate).substring(6,8));
        jQuery('#end-date').datepicker().data('datepicker').selectDate(new Date(enddateyear,enddatemonth,enddateday));
      }
      // Check if the function 'show_datepicker_message' exists, used for custom development -> interaction with user input (like a wordpress hook)
      if ( typeof show_datepicker_message == 'function' ) {show_datepicker_message(changeDateformat(jQuery('#start-date').val()),changeDateformat(jQuery('#end-date').val()));}
    }
    // Disable days if var 'rentmandisableddaysoverwrite' is defined
    ,onRenderCell: function (date, cellType) {
        if (cellType == 'day') {            
            var day = date.getDay(),
                isDisabled = rentmandisableddays.indexOf(day) != -1;
            return {
                disabled: isDisabled
            }
        }
    }
  }).attr('readonly','readonly');

  startdate = changeDateformat(jQuery('#start-date').val());
  enddate = changeDateformat(jQuery('#end-date').val());
  startdateyear = parseInt(String(startdate).substring(0,4));
  startdatemonth = parseInt(String(startdate).substring(4,6)) - 1;
  startdateday = parseInt(String(startdate).substring(6,8));
  enddateyear = parseInt(String(enddate).substring(0,4));
  enddatemonth = parseInt(String(enddate).substring(4,6)) - 1;
  enddateday = parseInt(String(enddate).substring(6,8));
  jQuery('#start-date').datepicker().data('datepicker').selectDate(new Date(startdateyear,startdatemonth,startdateday));
  jQuery('#end-date').datepicker().data('datepicker').selectDate(new Date(enddateyear,enddatemonth,enddateday));

  jQuery("#changePeriod").click(function(){
    // Show preloaders before page is posted on cart page
    jQuery( 'div.cart_totals, .woocommerce-cart-form' ).addClass( 'processing' ).block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
		});
    ajax_post_date();
  });
}

function changeDateformat(dates) {
  if(datePickerFormat == "dd-mm-yyyy"){dates = parseInt(dates.substring(6,10) + dates.substring(3,5) + dates.substring(0,2));}
  if(datePickerFormat == "mm-dd-yyyy"){dates = parseInt(dates.substring(6,10) + dates.substring(0,2) + dates.substring(3,5));}
  if(datePickerFormat == "yyyy-mm-dd"){dates = parseInt(dates.substring(0,4) + dates.substring(5,7) + dates.substring(8,10));}
  if(datePickerFormat == "yyyy-dd-mm"){dates = parseInt(dates.substring(0,4) + dates.substring(8,10) + dates.substring(5,7));}
  return dates;
}

function changeDateformatMin(mindate) {
  mindate = changeDateformat(mindate);
  mindateyear = parseInt(String(mindate).substring(0,4));
  mindatemonth = parseInt(String(mindate).substring(4,6)) - 1;
  mindateday = parseInt(String(mindate).substring(6,8));
  return new Date(mindateyear, mindatemonth, mindateday);
}

// Update the dates in the session
function ajax_post_date() {
	var data = {
		'action' : 'wdm_add_user_custom_data_options',
    'start_date' : jQuery('#start-date').val(),
		'end_date' : jQuery('#end-date').val()
	};
	jQuery.post(ajax_file_path, data, function(response) {
		location.reload();
	})
}

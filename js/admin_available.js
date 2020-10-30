// ----- JavaScript functions for availability ----- \\
// Initiate the availability functions
var rentmandisableddays = "";
jQuery().ready(function() {
  // Check if rentmandisableddaysoverwrite is defined
  // Used for disabling specific days of the week, for instance weekend days ( var rentmandisableddaysoverwrite = [0, 6]; )
  if (typeof rentmandisableddaysoverwrite !== 'undefined') {
    rentmandisableddays = rentmandisableddaysoverwrite;
  }

  jQuery('#start-date, #end-date').datepicker({
    minDate: changeDateformatMin(jQuery("#start-date").attr( "min" )),
    language: jQuery(this).attr("data-language"),
    autoClose: true,
    dateFormat: datePickerFormat,
    moveToOtherMonthsOnSelect: true,
    onSelect: function (fd, d, picker) {
      startdate = changeDateformat(jQuery('#start-date').val());
      enddate = changeDateformat(jQuery('#end-date').val());
      if(startdate > enddate){
        enddateyear = parseInt(String(startdate).substring(0,4));
        enddatemonth = parseInt(String(startdate).substring(4,6)) - 1;
        enddateday = parseInt(String(startdate).substring(6,8));        
        jQuery('#end-date').datepicker().data('datepicker').selectDate(new Date(enddateyear,enddatemonth,enddateday));
      }else{
        quickCheck();
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
  attachFunction();
  quickCheck();
});

function changeDateformat(dates) {
  if(typeof dates !== 'undefined'){
    if(datePickerFormat == "dd-mm-yyyy"){dates = parseInt(dates.substring(6,10) + dates.substring(3,5) + dates.substring(0,2));}
    if(datePickerFormat == "mm-dd-yyyy"){dates = parseInt(dates.substring(6,10) + dates.substring(0,2) + dates.substring(3,5));}
    if(datePickerFormat == "yyyy-mm-dd"){dates = parseInt(dates.substring(0,4) + dates.substring(5,7) + dates.substring(8,10));}
    if(datePickerFormat == "yyyy-dd-mm"){dates = parseInt(dates.substring(0,4) + dates.substring(8,10) + dates.substring(5,7));}
  }
  return dates;
}

function changeDateformatMin(mindate) {
  mindate = changeDateformat(mindate);
  mindateyear = parseInt(String(mindate).substring(0,4));
  mindatemonth = parseInt(String(mindate).substring(4,6)) - 1;
  mindateday = parseInt(String(mindate).substring(6,8));
  return new Date(mindateyear, mindatemonth, mindateday);
}

// Adds availability function to the 'amount' field
function attachFunction() {
    var input = document.getElementsByClassName("input-text qty text")[0];
    if (typeof input != 'undefined')
    	input.addEventListener ("change", quickCheck, false);
}

// Function that applies the availability check when changes are made on the page
function quickCheck() {
    if (jQuery("#start-date").length > 0){
        if(datePickerFormat == "dd-mm-yyyy" || datePickerFormat == "mm-dd-yyyy"){
            re = /^\d{1,2}\-\d{1,2}\-\d{4}$/;
        }
        if(datePickerFormat == "yyyy-mm-dd" || datePickerFormat == "yyyy-dd-mm"){
            re = /^\d{4}\-\d{1,2}\-\d{1,2}$/;
        }

        if(!jQuery("#start-date").val().match(re)) {
          jQuery("#start-date").val(jQuery("#start-date").attr("min"));
        }
        if(!jQuery("#end-date").val().match(re)) {
          jQuery("#end-date").val(jQuery("#start-date").attr("min"));
        }
        startdate = changeDateformat(jQuery('#start-date').val());
        enddate = changeDateformat(jQuery('#end-date').val());
        if(startdate > enddate){
          jQuery("#end-date").val(jQuery("#start-date").val());
          enddate = startdate;
        }

        jQuery(".availLog").html("...");
        jQuery(".availLog").css("color", "#4C4C4C");
        if (document.contains(document.getElementsByName("start_date")[0])) {
            var fromDate = startdate;
            var toDate = enddate;
            if (fromDate != null && fromDate != "" && toDate != null && toDate != null){
                var incDate = new Date(String(toDate).substring(0,4) + "-" + String(toDate).substring(4,6) + "-" + String(toDate).substring(6,8));
                toDate = new Date(incDate.getTime()+1000*60*60*24).toISOString().substring(0,10) + "T00:00:00";
            }
        } else {
            var fromDate = startdate;
            var toDate = enddate;
        }
        if (fromDate == null || toDate == null || fromDate == ""|| toDate == "" || fromDate > toDate){
            document.getElementsByClassName("availLog")[0].innerHTML = unavailable;
            document.getElementsByClassName("availLog")[0].style = "color:red";
        } else {
            var productID = productSku;
            // Check if the quantity field exists
            var input = document.getElementsByClassName("input-text qty text")[0];
            if (typeof input != 'undefined')
            	var amount = document.getElementsByClassName("input-text qty text")[0].value;
            else
            	var amount = 1;
            // Do the actual request
            xhr = new XMLHttpRequest();
            var url = endPoint;
            var account = rm_account;
            var token = rm_token;
            var totalamount = parseInt(amount) + parseInt(cart_amount);
            xhr.open("POST", url, true);
            xhr.setRequestHeader("Content-type", "application/json");
            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    var json = JSON.parse(xhr.responseText);
                    var maxcon = json.response.value.maxconfirmed;
                    var maxopt = json.response.value.maxoption;
                    // Show correct message depending on the values of maxconfirmed and maxoption
                    if (maxcon < 0){
                        document.getElementsByClassName("availLog")[0].innerHTML = unavailable;
                        document.getElementsByClassName("availLog")[0].style = "color:red";
                    }
                    else if (maxcon >= 0 & maxopt < 0){
                        document.getElementsByClassName("availLog")[0].innerHTML = maybe;
                        document.getElementsByClassName("availLog")[0].style = "color:orange";
                    }
                    else{
                        document.getElementsByClassName("availLog")[0].innerHTML = available;
                        document.getElementsByClassName("availLog")[0].style = "color:green";
                    }
                }
            }
            var fromDate = String(fromDate).substring(0,4) + "-" + String(fromDate).substring(4,6) + "-" + String(fromDate).substring(6,8);
            var data = JSON.stringify({"requestType":"modulefunction","client":{"language":1,"type":"webshopplugin",
                "version":"5.1.1"},"account":account,"token":token,"module":"Availability","parameters":{
                "van":fromDate,"tot":toDate,"materiaal":productID,"aantal":totalamount},"method":"is_available"});
            xhr.send(data);
        }
    }
}

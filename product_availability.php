<?php
  // ------------- Adding the date fields for the rental period ------------- \\
  # Adds date fields to 'Rentable' products in the store
  function add_custom_field(){
      global $post;
      $pf = new WC_Product_Factory();
      $product = $pf->get_product($post->ID);
      # Display calculated stock quantity for simple rentman products (= not rentables)
      $displaysimplestock = "";
      echo(apply_filters( 'rentman_get_simple_stock', $displaysimplestock, $product));
      # Display calculated stock quantity for simple rentman products (= not rentables) - end
      if ($product->get_type() == 'rentable'){
          # Display stock quantity of current product if enabled
          if (get_option('plugin-rentman-checkstock') == 1){
              $stock = __(' in stock', 'rentalshop');
              $nostock = __('Total stock unknown', 'rentalshop');
              /*$url = receive_endpoint();
              $message = json_encode(available_request(get_option('plugin-rentman-token'), $product->get_sku(), 0, true, date('c'), date('c')), JSON_PRETTY_PRINT);
              # Send Request & Receive Response
              $received = do_request($url, $message);
              $parsed = json_decode($received, true);
              $parsed = parseResponse($parsed);
              # Get values from parsed response
              $maxconfirmed = $parsed['response']['value']['maxconfirmed'];
              $maxoption = $parsed['response']['value']['maxoption'];
              echo("maxconfirmed = " . $maxconfirmed . "<br>");
              echo("maxoption = " . $maxoption . "<br>");*/
              /*$residual = $quantity + $maxconfirmed; # Total amount of available items
              echo("residual = quantity + maxconfirmed = " . $residual . "<br>");
              $optional = $maxoption * (-1);
              echo("optional = " + $optional . "<br>");
              $possible = min($optional, 20); # Amount of items that might be available
              echo("possible = " + $possible . "<br>");*/
              /*if ($maxconfirmed == ''){
                  echo '<p class="rentman-product-stock">' . $nostock . '</p>';
              }else if ($maxconfirmed == 0){
                  echo '<p class="rentman-product-stock">&#10005; ' . $maxconfirmed . $stock . '</p>';
              }else{
                  echo '<p class="rentman-product-stock">&#10003; ' . $maxconfirmed . $stock . '</p>';
              }*/
              if ($product->get_stock_quantity() == '')
                  echo '<p class="rentman-product-stock">' . $nostock . '</p>';
              else if ($product->get_stock_quantity() == 0)
                  echo '<p class="rentman-product-stock">&#10005; ' . $product->get_stock_quantity() . $stock . '</p>';
              else
                  echo '<p class="rentman-product-stock">&#10003; ' . $product->get_stock_quantity() . $stock . '</p>';
          }
          # Checks if there already is a 'Rentable' product in the shopping cart
          $rentableProduct = false;
          if(is_object(WC()) && isset(WC()->cart) && method_exists(WC()->cart, 'get_cart')){
              foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item){
                  $product = $cart_item['data'];
                  if ($product->get_type() == 'rentable'){
                      $rentableProduct = true;
                      break;
                  }
              }
          }
          echo '<div class="rentman-fields">';

          # If there isn't, display the date input fields
          if ($rentableProduct == false){
              $dates = get_dates();
              $fromDate = $dates['from_date'];
              $toDate = $dates['to_date'];
              $today = date("Y-m-d");
              $today = apply_filters( 'rentman_change_min_datepicker', $today);
              $datepickerlanguage = get_datepicker_translation(get_locale());
              # Check if the 'from' date is earlier than the 'to' date
              if (strtotime($fromDate) < strtotime($today))
                  $fromDate = $today;
              if (strtotime($toDate) < strtotime($today))
                  $toDate = $today;
              ?>
              <!-- actual HTML code for the date input fields -->
              <span class="rentman-date-from"><?php _e('From:', 'rentalshop');?></span>
              <input type="text" name="start_date" id="start-date" onchange="quickCheck()" value="<?php echo(format_date_picker_date($fromDate)); ?>" data-language='<?php echo($datepickerlanguage); ?>' min="<?php echo(format_date_picker_date($today)); ?>" /><br />
              <span class="rentman-date-to"><?php _e('To:', 'rentalshop');?></span>
              <input type="text" name="end_date" id="end-date" onchange="quickCheck()" value="<?php echo(format_date_picker_date($toDate)); ?>" data-language='<?php echo($datepickerlanguage); ?>' />
              <p><?php _e('Important: You can change the rent dates for other materials that you want to rent in the cart!', 'rentalshop');?></p>
              <?php
          }
          else{ # Else, display the dates from the products in your shopping cart
              $dates = get_dates();
              ?>
              <?php _e('<h3>Selected dates: </h3> <p><b>From </b>', 'rentalshop'); echo(format_date_picker_date($dates['from_date'])); _e('<b> to </b>', 'rentalshop'); echo(format_date_picker_date($dates['to_date'])); ?></p>
              <?php
          }
          # Only show the availability messages when 'check availability for sending' is allowed
          if (get_option('plugin-rentman-checkavail') == 1){
              echo '<p class="availLog"></p>';
          } else{
              echo '<p class="availLog" hidden></p>';
          }
          echo '</div>';
      }
  }
  
  # Also adds date fields to the checkout and cart menu
  function add_date_checkout(){
      $pf = new WC_Product_Factory();
      $rentableProduct = false;
      $today = date("Y-m-d");
      $today = apply_filters( 'rentman_change_min_datepicker', $today);

      # Again check if the shopping cart contains any 'Rentable' products
      foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item){
          $product = $cart_item['data'];
          if ($product->get_type() == 'rentable'){
              $rentableProduct = true;
              break;
          }
      }
      # If it does, add the date fields
      if ($rentableProduct){
          if (apply_filters('rentman/show_checkout_dates', true)) {
              init_datepickers();
              ?><p></p>
              <?php _e('<h2>Rental Period</h2>', 'rentalshop');
              $dates = get_dates();
              $startdate = $dates['from_date'];
              $enddate = $dates['to_date'];
              $sdate =& $startdate;
              $edate =& $enddate;
              $datepickerlanguage = get_datepicker_translation(get_locale());
              ?>
              <form method="post">
                  <div class="from-datepicker-wrapper-rentman">
                      <div class="from-label-rentman"><?php _e('From:', 'rentalshop'); ?></div>
                      <div class="from-datepicker-rentman"><input type="text" name="start_date" id="start-date" value="<?php echo(format_date_picker_date($startdate)); ?>" data-language='<?php echo($datepickerlanguage); ?>' min="<?php echo(format_date_picker_date($today)); ?>" /></div>
                  </div>
                  <div class="to-datepicker-wrapper-rentman">
                      <div class="to-label-rentman"><?php _e('To:', 'rentalshop'); ?></div>
                      <div class="to-datepicker-rentman"><input type="text" name="end_date" id="end-date" value="<?php echo(format_date_picker_date($enddate)); ?>" data-language='<?php echo($datepickerlanguage); ?>' min="<?php echo(format_date_picker_date($today)); ?>" /></div>
                  </div>
                  <p></p>
                  <!-- Update Button-->
                  <input type="hidden" name="rm-update-dates">
                  <input type="button" class="button button-primary" id="changePeriod" value="<?php _e('Update Dates', 'rentalshop');?>">
                  <input type="hidden" name="backup-start" value="<?php echo $sdate;?>">
                  <input type="hidden" name="backup-end" value="<?php echo $edate;?>">
                  <br /><br />
              </form>
              <?php
          }
      }
  }

  # Display the selected dates in the checkout menu
  function show_selected_dates(){
      $pf = new WC_Product_Factory();
      $rentableProduct = false;
      # Again check if the shopping cart contains any 'Rentable' products
      foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item){
          $product = $cart_item['data'];
          if ($product->get_type() == 'rentable'){
              $rentableProduct = true;
              break;
          }
      }
      # If it does, add the date fields
      if ($rentableProduct){
          $dates = get_dates();
          ?>
          <?php _e('<h3 class="rentman-selected-dates">Selected dates </h3> <p class="rentman-rental-period"><b>From </b>', 'rentalshop'); echo(format_date_picker_date($dates['from_date'])); _e('<b> to </b>', 'rentalshop'); echo(format_date_picker_date($dates['to_date'])); ?></p>
          <?php
          wp_register_script('admin_show_daily_price_label', plugins_url('js/admin_show_daily_price_label.js?' . get_plugin_data(realpath(dirname(__FILE__)) . '/rentman.php')['Version'], __FILE__ ));
          wp_localize_script('admin_show_daily_price_label', 'subtotalText', __("Daily price","rentalshop"));
          wp_enqueue_script('admin_show_daily_price_label');
      }
  }

  // ------------- Template Changes ------------- \\
  # Changes text of the 'add to cart' button
  function woo_custom_cart_button_text(){
      global $post;
      $pf = new WC_Product_Factory();
      $product = $pf->get_product($post->ID);
      if ($product->get_type() == 'rentable'){
          return __('Reserve', 'rentalshop');
      }
      return __('Add to cart', 'rentalshop');
  }

  # Adds a template for 'Rentable' products
  function add_to_cart_template(){
      global $post;
      $pf = new WC_Product_Factory();
      $product = $pf->get_product($post->ID);
      if ($product->get_type() == 'rentable'){
          if (apply_filters('rentman/add_to_cart_template', true)) {
              do_action('woocommerce_before_add_to_cart_form'); ?>
              <form class="cart rentman-extra-margin" method="post" enctype='multipart/form-data'>
                  <?php do_action('woocommerce_before_add_to_cart_button'); ?>
                  <?php
                  if (!$product->is_sold_individually())
                      $getstockquantity = $product->get_stock_quantity();
                      if(get_option('plugin-rentman-checkavail') == "0"){
                        $getstockquantity = '';
                      }
                      woocommerce_quantity_input(array(
                          'min_value' => apply_filters('woocommerce_quantity_input_min', 1, $product),
                          'max_value' => apply_filters('woocommerce_quantity_input_max', $product->backorders_allowed() ? '' : $getstockquantity, $product)
                      ));

                      $button='<input type="hidden" name="add-to-cart" value="' . esc_attr($product->get_id()) . '"/>';
                      $button.='<button type="submit" class="single_add_to_cart_button button alt">' . $product->single_add_to_cart_text() . '</button>';
                      echo(apply_filters( 'change_add_to_cart_template', $button, $product));
                      do_action('woocommerce_after_add_to_cart_button'); ?>
              </form>
              <?php do_action('woocommerce_after_add_to_cart_form');
          }
      }
  }

  // ------------- API Request Functions ------------- \\
  # Apply the check_available function on updated products in the cart
  function update_amount($passed, $cart_item_key, $values, $quantity){
      $product = $values['data'];
      return check_available($passed, $product->get_id(), $quantity, 'from_CART');
  }

  # Apply availability check for each item in the cart for the new dates and update the
  # dates in the current session if all products are available
  function update_dates(){
      $pf = new WC_Product_Factory();
      $checkergroup = true;
      foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item){
          $product = $cart_item['data'];
          if ($product->get_type() == 'rentable'){
              $checkergroup = check_available($checkergroup, $product->get_id(), $cart_item[''], 'checkout');
              if ($checkergroup == false)
                  break;
          }
      } # Only update the dates when all materials are available in the new time period
      if ($checkergroup){
          echo 'Materials are available, updating the dates in the current session';
          $_SESSION['rentman_rental_session']['from_date'] = format_date_picker_post_date($_POST['start_date']);
          $_SESSION['rentman_rental_session']['to_date'] = format_date_picker_post_date($_POST['end_date']);
      } else {
          echo 'Materials are not available, so do not update the dates of the current session';
          echo 'SESSION from date = ' . $_SESSION['rentman_rental_session']['from_date'];
          echo 'SESSION to date = ' . $_SESSION['rentman_rental_session']['to_date'];
      }
      wp_die();
  }

  # Set the availability functions
  function set_functions(){
      # Check if product is already in the cart
      global $product;
      $quantity = 0;
      if(is_object(WC()) && isset(WC()->cart) && method_exists(WC()->cart, 'get_cart')){
          foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item){
              $cartproduct = $cart_item['data'];
              if ($cartproduct->get_title() == $product->get_title()){
                  $quantity += $cart_item['quantity'];
                  break;
              }
          }
      }

      # Adjust the ending date to 00:00 on the following day
      $dates = get_dates();
      $enddate = $dates['to_date'];
      $enddate = date("Y-m-d", strtotime("+1 day", strtotime($enddate)));

      if(!function_exists('get_plugin_data')){
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
      }

      # Register and localize the availability script
      wp_register_style('style_datepicker', plugins_url('css/datepicker.css', __FILE__ ));
      wp_enqueue_style('style_datepicker');
      wp_register_script('admin_datepicker', plugins_url('js/admin_datepicker.js?' . get_plugin_data(realpath(dirname(__FILE__)) . '/rentman.php')['Version'], __FILE__ ));
      wp_enqueue_script('admin_datepicker');
      wp_register_script('admin_datepicker_translation', plugins_url('js/admin_datepicker_translation.js?' . get_plugin_data(realpath(dirname(__FILE__)) . '/rentman.php')['Version'], __FILE__ ));
      wp_enqueue_script('admin_datepicker_translation');
      wp_register_script('admin_availability', plugins_url('js/admin_available.js?' . get_plugin_data(realpath(dirname(__FILE__)) . '/rentman.php')['Version'], __FILE__ ));
      wp_localize_script('admin_availability', 'startDate', $dates['from_date']);
      wp_localize_script('admin_availability', 'endDate', $enddate);
      wp_localize_script('admin_availability', 'productSku', $product->get_sku());
      wp_localize_script('admin_availability', 'cart_amount', (string)$quantity);
      wp_localize_script('admin_availability', 'unavailable', __("Product is not available!", "rentalshop"));
      wp_localize_script('admin_availability', 'maybe', __("Product might not be available!", "rentalshop"));
      wp_localize_script('admin_availability', 'available', __("Product is available!", "rentalshop"));
      wp_localize_script('admin_availability', 'ajax_file_path', admin_url('admin-ajax.php'));
      wp_localize_script('admin_availability', 'datePickerFormat', DATEPICKERFORMAT);
      wp_enqueue_script('admin_availability');
  }

  # Attach script to the 'update rental period' button
  function init_datepickers(){
      # Register and localize the datepickers script
      wp_register_style('style_datepicker', plugins_url('css/datepicker.css', __FILE__ ));
      wp_enqueue_style('style_datepicker');
      wp_register_script('admin_datepicker', plugins_url('js/admin_datepicker.js?' . get_plugin_data(realpath(dirname(__FILE__)) . '/rentman.php')['Version'], __FILE__ ));
      wp_enqueue_script('admin_datepicker');
      wp_register_script('admin_datepicker_translation', plugins_url('js/admin_datepicker_translation.js?' . get_plugin_data(realpath(dirname(__FILE__)) . '/rentman.php')['Version'], __FILE__ ));
      wp_enqueue_script('admin_datepicker_translation');
      wp_register_script('admin_datepickers', plugins_url('js/admin_datepickers.js?' . get_plugin_data(realpath(dirname(__FILE__)) . '/rentman.php')['Version'], __FILE__ ));
      wp_localize_script('admin_datepickers', 'ajax_file_path', admin_url('admin-ajax.php'));
      wp_localize_script('admin_datepickers', 'datePickerFormat', DATEPICKERFORMAT);
      wp_localize_script('admin_datepickers', 'subtotalText', __("Daily price","rentalshop"));
      wp_enqueue_script('admin_datepickers');
  }

  # Format date for Jquery datepicker
  function format_date_picker_date($formatdate) {
      if(DATEPICKERFORMAT == "dd-mm-yyyy"){
          $dates = substr($formatdate, -2) . "-" . substr($formatdate, 5, 2) . "-" . substr($formatdate, 0, 4);
      }
      if(DATEPICKERFORMAT == "mm-dd-yyyy"){
          $dates = substr($formatdate, 5, 2) . "-" . substr($formatdate, -2) . "-" . substr($formatdate, 0, 4);
      }
      if(DATEPICKERFORMAT == "yyyy-mm-dd"){
          $dates = substr($formatdate, 0, 4) . "-" . substr($formatdate, 5, 2) . "-" . substr($formatdate, -2);
      }
      if(DATEPICKERFORMAT == "yyyy-dd-mm"){
          $dates = substr($formatdate, 0, 4) . "-" . substr($formatdate, -2) . "-" . substr($formatdate, 5, 2);
      }
      return $dates;
  }

  function format_date_picker_post_date($formatdate) {
      if(DATEPICKERFORMAT == "dd-mm-yyyy"){
          $dates = substr($formatdate, -4) . "-" . substr($formatdate, 3, 2) . "-" . substr($formatdate, 0, 2);
      }
      if(DATEPICKERFORMAT == "mm-dd-yyyy"){
          $dates = substr($formatdate, 6, 10) . "-" . substr($formatdate, 0, 2) . "-" . substr($formatdate, 3, 2);
      }
      if(DATEPICKERFORMAT == "yyyy-mm-dd"){
          $dates = $formatdate;
      }
      if(DATEPICKERFORMAT == "yyyy-dd-mm"){
          $dates = substr($formatdate, 0, 4) . "-" . substr($formatdate, -2) . "-" . substr($formatdate, 5, 2);
      }
      return $dates;
  }

  # Get language code for datepicker
  function get_datepicker_translation($languagecode){
      $transvalues[] = ["cs", "cs_CZ"];
      $transvalues[] = ["da", "da_DK"];
      $transvalues[] = ["de", "de_DE"];
      $transvalues[] = ["de", "de_CH"];
      $transvalues[] = ["en", "en_US"];
      $transvalues[] = ["en", "en_AU"];
      $transvalues[] = ["en", "en_CA"];
      $transvalues[] = ["en", "en_GB"];
      $transvalues[] = ["es", "es_AR"];
      $transvalues[] = ["es", "es_CL"];
      $transvalues[] = ["es", "es_CO"];
      $transvalues[] = ["es", "es_MX"];
      $transvalues[] = ["es", "es_PE"];
      $transvalues[] = ["es", "es_ES"];
      $transvalues[] = ["es", "es_PR"];
      $transvalues[] = ["es", "es_VE"];
      $transvalues[] = ["fi", "fi"];
      $transvalues[] = ["fr", "fr_BE"];
      $transvalues[] = ["fr", "fr_CA"];
      $transvalues[] = ["fr", "fr_FR"];
      $transvalues[] = ["hu", "hu_HU"];
      $transvalues[] = ["it", "it_IT"];
      $transvalues[] = ["nl", "nl_BE"];
      $transvalues[] = ["nl", "nl_NL"];
      $transvalues[] = ["pl", "pl_PL"];
      $transvalues[] = ["pt-BR", "pt_BR"];
      $transvalues[] = ["pt", "pt_PT"];
      $transvalues[] = ["ro", "ro_RO"];
      $transvalues[] = ["sk", "sk_SK"];
      $transvalues[] = ["zh", "zh_CN"];
      $transvalues[] = ["zh", "zh_HK"];
      $transvalues[] = ["zh", "zh_TW"];
      $key = array_search($languagecode, array_column($transvalues,1));
      if($key==""){
        return "en";
      }else{
        return $transvalues[$key][0];
      }
  }

  # Main function for the availability check and relevant API requests
  function check_available($passed, $product_id, $quantity, $variation_id = '', $variations = ''){
     # Get the product and chosen dates
     $pf = new WC_Product_Factory();
     $product = $pf->get_product($product_id);
     $startDate = "";
     $endDate = "";
     if(isset($_POST['start_date'])){
       $startDate = format_date_picker_post_date($_POST['start_date']);
     }
     if(isset($_POST['end_date'])){
       $endDate = format_date_picker_post_date($_POST['end_date']);
     }
     if ($startDate == '' or $endDate == ''){
         $dates = get_dates();
         $startDate = $dates['from_date'];
         $endDate = $dates['to_date'];
     }

     # Only apply availability check on products that were
     # imported from Rentman (so with the 'rentable' product type)
     if ($product->get_type() == 'rentable'){
         if (apply_filters('rentman/availability_check', true)) {
             if ($variation_id != 'checkout') {
                 $_SESSION['rentman_rental_session']['from_date'] = $startDate;
                 $_SESSION['rentman_rental_session']['to_date'] = $endDate;
                 $dates = get_dates();
                 $sdate = $dates['from_date'];
                 $edate = $dates['to_date'];
             } else {
                 $sdate = $startDate;
                 $edate = $endDate;
             }
             # Check if any of the input dates are empty or the rental period is invalid
             if ($sdate == '' or $edate == '' or (strtotime($edate) < strtotime($sdate))) {
                 $passed = false;
                 wc_add_notice(__('Something went wrong.. Did you provide correct dates?', 'rentalshop'), 'error');
             } else {
                 # Continue with the check if 'Check availability for sending' is set to yes
                 if (get_option('plugin-rentman-checkavail') == 1) {
                     $url = receive_endpoint();
                     # Check if the item is already in the cart and adjust
                     # the input quantity accordingly
                     if ($variation_id != 'from_CART') {
                         foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                             $cartproduct = $cart_item['data'];
                             //if ($cartproduct->get_title() == $product->get_title()) {
                             if ($cartproduct->get_sku() == $product->get_sku()) {
                                 $quantity += $cart_item['quantity'];
                                 break;
                             }
                         }
                     }
                     # Setup Request to send JSON
                     $message = json_encode(available_request(get_option('plugin-rentman-token'), $product->get_sku(), $quantity, true, $sdate, $edate), JSON_PRETTY_PRINT);
                     # Send Request & Receive Response
                     $received = do_request($url, $message);
                     $parsed = json_decode($received, true);
                     $parsed = parseResponse($parsed);

                     # Get values from parsed response
                     $maxconfirmed = $parsed['response']['value']['maxconfirmed'];
                     $maxoption = $parsed['response']['value']['maxoption'];
                     $residual = $quantity + $maxconfirmed; # Total amount of available items
                     $optional = $maxoption * (-1);
                     $possible = min($optional, $quantity); # Amount of items that might be available

                     # The actual Availability Check
                     # Comparing values of 'maxconfirmed' and 'maxoption'
                     if ($maxconfirmed < 0) { # Products are definitely not available
                         $passed = false;
                         $notice = __('There are only ', 'rentalshop') . $residual . ' ' . $product->get_title() . __(' available in that time period.', 'rentalshop');
                         wc_add_notice($notice, 'error');
                     } else if ($maxconfirmed >= 0 and $maxoption < 0) { # Products might be available, depending on confirmation of other orders
                         $notice = __('Important: ', 'rentalshop') . $possible . __(' out of ', 'rentalshop') . $quantity . ' ' . $product->get_title() . __(' might not be available in that time period..', 'rentalshop');
                         wc_add_notice($notice, 'error');
                     } else { # Products are available and are added to the cart
                         $notice = __('Your selected amount of ', 'rentalshop') . $product->get_title() . __(' is available in that time period!', 'rentalshop');
                         wc_add_notice($notice, 'success');
                     }
                 }
             }
         }
     }
     return $passed;
 }

function rentman_is_available()
{
    $url = receive_endpoint();
    $identifier = $_POST['productID'];
    $quantity = $_POST['totalAmount'];
    $sdate = $_POST['fromDate'];
    $edate = $_POST['toDate'];

    //input validation
    if (!is_numeric($identifier)) {
        exit("Invalid productID");
    }

    if (!is_numeric($quantity) || $quantity < 1) {
        exit("Invalid totalAmount");
    }

    if (preg_match("/(?<startYear>\d{4})-(?<startMonth>\d{2})-(?<startDay>\d{2})/", $sdate, $matches)) {
        if ($matches['startYear'] < date('Y') || $matches['startYear'] > 2099) {
            exit("Invalid fromDate");
        }
        if ($matches['startMonth'] < 1 || $matches['startMonth'] > 12) {
            exit("Invalid fromDate");
        }
        if ($matches['startDay'] < 1 || $matches['startDay'] > 31 || ($matches['startDay'] > 29 && $matches['startMonth'] == 2) || ($matches['startDay'] > 30 && in_array($matches['startMonth'], [2, 4, 6, 9, 11]))) {
            exit("Invalid fromDate");
        }
    } else {
        exit("Invalid fromDate");
    }

    if (preg_match("/(?<endYear>\d{4})-(?<endMonth>\d{2})-(?<endDay>\d{2})/", $edate, $matches)) {
        if ($matches['endYear'] < date('Y') || $matches['endYear'] > 2099) {
            exit("Invalid toDate");
        }
        if ($matches['endMonth'] < 1 || $matches['endMonth'] > 12) {
            exit("Invalid toDate");
        }
        if ($matches['endDay'] < 1 || $matches['endDay'] > 31 || ($matches['endDay'] > 29 && $matches['endMonth'] == 2) || ($matches['endDay'] > 30 && in_array($matches['endMonth'], [2, 4, 6, 9, 11]))) {
            exit("Invalid toDate");
        }
    } else {
        exit("Invalid toDate");
    }

    # Setup Request to send JSON
    $message = json_encode(available_request(get_option('plugin-rentman-token'), $identifier, $quantity, true, $sdate, $edate), JSON_PRETTY_PRINT);
    # Send Request & Receive Response
    $received = do_request($url, $message);
    $parsed = parseResponse(json_decode($received, true));

    $json['maxconfirmed'] = $parsed['response']['value']['maxconfirmed'];
    $json['maxoption'] = $parsed['response']['value']['maxoption'];

    echo(json_encode($json));
    wp_die();
}

?>

<?php
    // ------------- Main Product Price Functions ------------- \\

    # Show the customer discount and tax on the product page
    function show_discount(){
        global $post;
        if(!is_cart()){
            if (get_post_meta($post->ID, 'rentman_imported', true) == true){
                if (get_option('plugin-rentman-checkdisc') == 1){
                    if (apply_filters('rentman/add_discount_fields', true)) {
                        $current_user = wp_get_current_user();
                        if ($current_user->ID != 0) {
                            $pf = new WC_Product_Factory();
                            $product = $pf->get_product($post->ID);
                            $_tax = new WC_Tax();
                            $product_tax_class = $product->get_tax_class();
                            $rates = $_tax->get_rates($product_tax_class);
                            $rate = current($rates);
                            $tax = 1 + (floatval($rate['rate']) / 100);

                            # Receive endpoint and token
                            $url = receive_endpoint();
                            $token = get_option('plugin-rentman-token');

                            # Setup request to send JSON
                            $message = json_encode(setup_check_request($token, $current_user->user_email), JSON_PRETTY_PRINT);

                            # Send request and receive response
                            $received = do_request($url, $message);
                            $parsed = json_decode($received, true);
                            $parsed = parseResponse($parsed);
                            $contactarr = $parsed['response']['items']['Contact'];
                        } else {
                            $contactarr = array();
                        }
                        if (empty($contactarr)) {
                            # User not found, so don't add the discount
                            return;
                        } else { # Calculate the total customer discount
                            # Get contact and relevant materials
                            $contact = current($contactarr);
                            $contact_id = $contact['data']['id'];
                            $materials = array($product->get_sku());

                            # Setup request, send request and receive response
                            $message = json_encode(setup_discount_request($token, $contact_id, $materials), JSON_PRETTY_PRINT);
                            $received = do_request($url, $message);
                            $parsed = json_decode($received, true);
                            $parsed = parseResponse($parsed);

                            # Receive array of discounts
                            $discounts = $parsed['response']['value'];
                            $notice = __('Discount price: ', 'rentalshop');

                            # Display discount if there is one
                            $taxprice = $product->get_price() * $tax;
                            $discountprice = (1 - current($discounts)) * $taxprice;

                            if (1 - current($discounts) == 1){
                                return;
                            }
                            echo '<h4 style="color:#8b0000">' . $notice . '€' . number_format(round($discountprice, 2), 2) . '</h4>';
                        }
                    }
                }
            }
        }
    }

    # Apply staffel on the prices of the products
    function apply_staffel(){
        if(is_cart() || is_checkout()){
            $staffels = array();
            $pf = new WC_Product_Factory();

            # Get token and dates
            $dates = get_dates();
            $fromDate = strtotime($dates['from_date']);
            $endDate = strtotime($dates['to_date']);            
            $totaldays = abs($endDate - $fromDate);
            $totaldays = ceil($totaldays / (3600*24)) + 1;
            $totaldays = apply_filters( 'rentman_change_staffel_total_days', $totaldays);
            $token = get_option('plugin-rentman-token');

            # Fill staffel array with data from the cart
            $items = WC()->cart->get_cart();
            foreach ($items as $item => $values){
                $product = wc_get_product($values['data']->get_id());
                $staffelgroup = get_staffelgroup($token, $product->get_sku());
                if ($staffelgroup == null){
                    $staffels[$product->get_sku()] = '1.0';
                }else{
                    $staffels[$product->get_sku()] = get_staffel($token, $totaldays, $staffelgroup);
                }
            }

            # Calculate the additional fee and add it to the cart
            $fee = calculate_fee($staffels);
            apply_customer_discount($staffels);

            /*Adding  filter for converting to weeks instead of days if wanted*/
            $returnfields = apply_filters( 'rentman_change_staffel_to_weeks', $totaldays);

            if(is_array($returnfields)){
                if($returnfields[0] > 0){
                    WC()->cart->add_fee(__('Extra ','rentalshop') . $returnfields[0] . $returnfields[1], $fee, true, 'standard');
                }
            }else{
                WC()->cart->add_fee(__('Extra ','rentalshop') . ($totaldays - 1) . __(' days','rentalshop'), $fee, true, 'standard');
            }
        }
    }

    # Apply customer discount on the prices of the products
    function apply_customer_discount($staffels){
        # Check if discount check is enabled
        if (get_option('plugin-rentman-checkdisc') == 1){
            if (apply_filters('rentman/apply_discount', true)) {
                $current_user = wp_get_current_user();
                if ($current_user->ID != 0) {
                    # Receive endpoint and token
                    $url = receive_endpoint();
                    $token = get_option('plugin-rentman-token');

                    # Setup request to send JSON
                    $message = json_encode(setup_check_request($token, $current_user->user_email), JSON_PRETTY_PRINT);

                    # Send request and receive response
                    $received = do_request($url, $message);
                    $parsed = json_decode($received, true);
                    $parsed = parseResponse($parsed);
                    $contactarr = $parsed['response']['items']['Contact'];
                } else {
                    # User not found, so don't add the discount
                    return;
                }

                if (empty($contactarr)) { # User not found, so don't add the discount
                    return;
                } else { # Calculate the total customer discount
                    # Get contact and relevant materials
                    $contact = current($contactarr);
                    $contact_id = $contact['data']['id'];
                    $materials = array();
                    $items = WC()->cart->get_cart();
                    foreach ($items as $item => $values) {
                        $product = wc_get_product($values['data']->get_id());
                        array_push($materials, $product->get_sku());
                    }

                    # Setup request, send request and receive response
                    $message = json_encode(setup_discount_request($token, $contact_id, $materials), JSON_PRETTY_PRINT);
                    $received = do_request($url, $message);
                    $parsed = json_decode($received, true);
                    $parsed = parseResponse($parsed);

                    # Receive array of discounts
                    $discounts = $parsed['response']['value'];
                    $totaldiscount = $contact['data']['totaalkorting'];
                    $discount = calculate_discount($discounts, $staffels, $totaldiscount);
                    WC()->cart->add_fee(__('Customer Discount', 'rentalshop'), $discount, true, 'standard');
                }
            }
        }
    }

    # Get daily fee multiplier from Rentman
    function get_staffel($token, $totaldays, $staffelgroup){
        $url = receive_endpoint();

        # Setup request to send JSON
        $message = json_encode(setup_staffel_request($token, $totaldays, $staffelgroup), JSON_PRETTY_PRINT);

        # Send request and receive response
        $received = do_request($url, $message);

        $parsed = json_decode($received, true);
        $parsed = parseResponse($parsed);
        $stafObject = current($parsed['response']['items']['Staffel']);

        return $stafObject['data']['staffel'];
    }

    # Get staffelgroup of products in cart
    function get_staffelgroup($token, $product_id){
        if(is_cart() || is_checkout()){
            $url = receive_endpoint();

            # Setup request to send JSON
            $message = json_encode(setup_staffelgroup_request($token, $product_id), JSON_PRETTY_PRINT);

            # Send request and receive response
            $received = do_request($url, $message);

            $parsed = json_decode($received, true);
            $parsed = parseResponse($parsed);
            $stafObject = current($parsed['response']['items']['Materiaal']);

            # If product is only for sale
            if ($stafObject['data']['verhuur'] == false)
                return null;

            return $stafObject['data']['staffelgroep'];
        }
    }

    # Get staffel array for project export
    function get_staffels($order_id){
        $order = new WC_Order($order_id);
        $staffels = array();

        # Get token and dates
        $dates = get_dates();
        $fromDate = strtotime($dates['from_date']);
        $endDate = strtotime($dates['to_date']);
        $totaldays = abs($endDate - $fromDate);
        $totaldays = ceil($totaldays / (3600*24)) + 1;
        $token = get_option('plugin-rentman-token');

        # Get staffel data for all items
        foreach($order->get_items() as $key => $lineItem){
            $product_id = $lineItem['product_id'];
            $product = wc_get_product($product_id);
            # Receive the right staffel depending on the staffelgroup
            $staffelgroup = get_staffelgroup($token, $product->get_sku());
            if ($staffelgroup == null)
                $staffels[$product->get_sku()] = '1,0';
            else
                $staffels[$product->get_sku()] = get_staffel($token, $totaldays, $staffelgroup);
        }
        return $staffels;
    }

    # Get discount array for project export
    function get_all_discounts($order_id, $contact_id){
        if (get_option('plugin-rentman-checkdisc') == 1) {
            $order = new WC_Order($order_id);
            $materials = array();
            $pf = new WC_Product_Factory();

            # Receive endpoint and token
            $url = receive_endpoint();
            $token = get_option('plugin-rentman-token');

            # Create array with all materials from the order
            foreach ($order->get_items() as $key => $lineItem) {
                $product_id = $lineItem['product_id'];
                $product = $pf->get_product($product_id);
                array_push($materials, $product->get_sku());
            }

            # Setup request, send request and receive response
            $message = json_encode(setup_discount_request($token, $contact_id, $materials), JSON_PRETTY_PRINT);
            $received = do_request($url, $message);
            $parsed = json_decode($received, true);
            $parsed = parseResponse($parsed);

            # Receive array of discounts
            $discounts = $parsed['response']['value'];
        } else {
            $discounts = array();
        }
        return $discounts;
    }

    # Calculate the total staffel fee of the shopping cart
    function calculate_fee($staffels){
        $totalprice = 0;
        $items = WC()->cart->get_cart();
        foreach ($items as $item => $values){
            $amount = $items[$item]["quantity"];
            $product = wc_get_product($values['data']->get_id());
            $staffel = $staffels[$product->get_sku()];
            $carttotals = $product->get_price() * $amount;
            $staffelprice = $carttotals * $staffel;
            $totalprice += $staffelprice - $carttotals;
        }
        return $totalprice;
    }

    # Calculate the total customer discount of the shopping cart
    function calculate_discount($discounts, $staffels, $totaldisc){
        $totalprice = 0;
        $items = WC()->cart->get_cart();
        foreach ($items as $item => $values){
            $amount = $items[$item]["quantity"];
            $product = wc_get_product($values['data']->get_id());
            $discount = $discounts[$product->get_sku()];
            $carttotals = $product->get_price() * $staffels[$product->get_sku()] * $amount;
            $percentage = 1 - $discount;
            $discountprice = $carttotals * $percentage;
            $extradiscount = $discountprice * $totaldisc;
            $totalprice += $carttotals - $discountprice + $extradiscount;
        }
        return $totalprice*(-1);
    }

?>

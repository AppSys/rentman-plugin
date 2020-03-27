<?php
    // ------------- Main User Export Function ------------- \\

    # Checks if customer from new order already exists
    # in Rentman and adds them to Rentman if not
    function export_users($order_id){
        # Check for rentable products in the order
        global $wpdb;
        $order = new WC_Order($order_id);
        $rentableProduct = false;
        $items = $order->get_items();
        foreach ( $items as $item ) {
          if (get_post_meta($item->get_product_id(), 'rentman_imported', true) == true){
              $rentableProduct = true;
              break;
          }
        }        

        # If it contains Rentman products, add customer as contact to Rentman and create a new project
        # Check if alternative shipping address has been filled in and create a separate contact
        if ($rentableProduct){
            if (apply_filters('rentman/add_customer', true)) {
                $url = receive_endpoint();
                $token = get_option('plugin-rentman-token');
                $billing = $order->get_billing_address_1();
                $shipping = $order->get_shipping_address_1();
                $contact_person = "";
                $location_contact = "";

                # Setup Request to send JSON
                $message = json_encode(setup_check_request($token, $order->get_billing_email()), JSON_PRETTY_PRINT);

                # Send Request & Receive Response
                $received = do_request($url, $message);

                $parsed = json_decode($received, true);
                $parsed = parseResponse($parsed);

                $contactarr = $parsed['response']['items']['Contact'];
                if (empty($contactarr)) {
                    # Contact doesn't exist yet, so do a create request
                    # Setup Request to send JSON
                    $message = json_encode(setup_newuser_request($token, $order_id), JSON_PRETTY_PRINT);

                    # Send Request & Receive Response
                    $received = do_request($url, $message);
                    $parsed = json_decode($received, true);
                    $parsed = parseResponse($parsed);
                    $contact_id = current($parsed['response']['items']['Contact']);
                    $contact_person = isset(current($parsed['response']['items']['Contact'])['data']['contactpersoon']) ? current($parsed['response']['items']['Contact'])['data']['contactpersoon'] : '';
                    $fees = array();
                    for ($x = 0; $x <= 2; $x++) {
                        array_push($fees, 0);
                    }
                } else {
                    # Get discounts from Rentman 4G account
                    $contact_id = current($parsed['response']['items']['Contact']);
                    $fees = array();
                    array_push($fees, $contact_id['data']['personeelkorting']);
                    array_push($fees, $contact_id['data']['totaalkorting']);
                    array_push($fees, $contact_id['data']['transportkorting']);
                }

                if ($billing != $shipping && $shipping != '') { # Get Rentman Contact for location
                    # Setup Request to send JSON
                    $message = json_encode(setup_location_request($token, $order->get_shipping_address_1(), $order->shipping_email), JSON_PRETTY_PRINT);

                    # Send Request & Receive Response
                    $received = do_request($url, $message);
                    $parsed = json_decode($received, true);
                    $parsed = parseResponse($parsed);

                    $contactarr = $parsed['response']['items']['Contact'];
                    if (empty($contactarr)) {
                        # Contact doesn't exist yet, so do a create request
                        # Setup Request to send JSON
                        $message = json_encode(setup_newlocation_request($token, $order_id), JSON_PRETTY_PRINT);
                        # Send Request & Receive Response
                        $received = do_request($url, $message);
                        $parsed = json_decode($received, true);
                        $parsed = parseResponse($parsed);
                        $transport_id = current($parsed['response']['items']['Contact']);
                        $location_contact = current($parsed['response']['items']['Contact'])['data']['contactpersoon'];
                    } else {
                        $transport_id = current($parsed['response']['items']['Contact']);
                    }
                } else # Billing and shipping addresses are exactly the same
                    $transport_id = $contact_id;

                # Add the project and finish the session
                add_project($order_id, $contact_id['data']['id'], $transport_id['data']['id'], $fees, $contact_person, $location_contact);
            }
        }
    }
?>

<?php
    # convert date to timezone and give it a format
    function convertdate($modifieddate){
        $oDate = new DateTime($modifieddate);
        $oDate->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $oDate->format('Y-m-d H:i:s');
    }

    function display_array($array_name){
      echo("<pre>");
      print_r($array_name);
      echo("</pre>");
    }

    function rentman_import_log($value, $reset){
        $file_path = wp_upload_dir()["basedir"] . '/rentman/debug.txt';
        $currentContent = "";
        if($reset == 0){
            if (file_exists($file_path)) {
              $currentContent = file_get_contents($file_path);
            }
        }else{
            if(isset($_SESSION["rentman_import_log"])){
                unset($_SESSION["rentman_import_log"]);
            }
        }
        $currentContent.= $value;
        $_SESSION["rentman_import_log"][] = $value;
        file_put_contents($file_path, $currentContent);
    }

    // ------------- Main Product Import Functions ------------- \\
    # Handles import of products from Rentman to your WooCommerce Shop
    function import_products($token){
        if ($token == "fail"){
            _e('<h4>Import Failed! Did you provide the correct credentials?</h4>', 'rentalshop');
        } else{
            if (apply_filters('rentman/import_products', true)) {
                gettaxrates();

                # Get the url of the most latest endpoint
                $url = receive_endpoint();

                # First, obtain all the products from Rentman
                $message = json_encode(setup_import_request($token), JSON_PRETTY_PRINT);

                # Send Request & Receive Response
                $received = do_request($url, $message);
                $parsed = json_decode($received, true);
                $parsed = parseResponse($parsed);

                # Multiste import
                $parsed = apply_filters( 'rentman_import_multishop', $parsed );

                # Receive ID's of first and last product in response
                $listLength = sizeof($parsed['response']['links']['Materiaal']);

                if ($listLength > 0) { # At least one product has been found
                    $id_sku = array();
                    $skus = array_keys($parsed['response']['items']['Materiaal']);
                    $woocommerce_rentman_products = array();
                    # GET all the rentman products already in the woocommerce database (postid and sku)
                    $args = array('post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_key' => 'rentman_imported', 'meta_value' => true, 'meta_compare' => '=', 'fields' => 'ids');
                    $posts = get_posts($args);
                    foreach($posts as $postid) {
                      $sku = get_post_meta($postid, "_sku",true);
                      $id_sku[] = array("postid" => $postid, "sku" => $sku);
                      array_push($woocommerce_rentman_products, $sku);
                      if (array_key_exists($sku, $parsed['response']['items']['Materiaal'])){
                        $parsed['response']['items']['Materiaal'][$sku]['data']['woocommercepostid'] = $postid;
                      }
                    }

                    # Delete the rentman Woocommerce products that are not in the rentmanapp anymore
                    $remainder = array_diff($woocommerce_rentman_products, $parsed['response']['links']['Materiaal']);
                    if (sizeof($remainder) > 0){
                        # Now delete the posts in the remainder array
                        foreach ($remainder as $item){
                            $key = array_search($item, array_column($id_sku, 'sku'));
                            if(is_numeric($key)){
                              # Delete attached images
                              $media = get_children(array('post_parent' => $id_sku[$key]["postid"], 'post_type' => 'attachment'));
                              foreach ($media as $file){
                                  wp_delete_post($file->ID);
                              }
                              wp_delete_post($id_sku[$key]["postid"]);
                            }
                        }
                    }
                    update_option('plugin-rentman-parsed', $parsed);

                    $timestamp = time();
                    $date = new DateTime("now", new DateTimeZone(TIMEZONE)); //first argument "must" be a string
                    //adjust the object to correct timestamp
                    $date->setTimestamp($timestamp);
                    $pluginlasttime = $date->format('d-m-Y H:i:s');
                    update_option('plugin-rentman-lasttime', $pluginlasttime);

                    wp_register_script('rentman_do_import', plugins_url('js/admin_do_import.js?' . get_plugin_data(realpath(dirname(__FILE__)) . '/rentman.php')['Version'], __FILE__));
                    wp_localize_script('rentman_do_import', 'pluginlasttime', $pluginlasttime);
                    wp_localize_script('rentman_do_import', 'basictoadvanced', BASICTOADVANCED);
                    wp_localize_script('rentman_do_import', 'skus', $skus);
                    wp_localize_script('rentman_do_import', 'string1', __('<b>Products finished:</b> ', 'rentalshop'));
                    wp_localize_script('rentman_do_import', 'string2', __('<br>Processing..', 'rentalshop'));
                    wp_localize_script('rentman_do_import', 'string3', __('<br>Removed irrelevant products and categories', 'rentalshop'));
                    wp_localize_script('rentman_do_import', 'string4', __('<h3>Import complete!</h3>', 'rentalshop'));
                    wp_localize_script('rentman_do_import', 'string5', __('<b>Checking all products for updates:</b> ', 'rentalshop'));
                    wp_localize_script('rentman_do_import', 'string6', __('<br><b>No new Rentman updates have been found!</b> ', 'rentalshop'));
                    wp_localize_script('rentman_do_import', 'tablabel3', __( 'Import', 'rentalshop' ));
                    wp_enqueue_script('rentman_do_import');
                    unset($skus);
                    unset($id_sku);
                    unset($parsed);
                } else { # No rentable products have been found
                    _e('<br>No products have been found on your Rentman Account..<br>', 'rentalshop');
                    remove_empty_categories();
                }
            }
        }
    }

    function check_for_updates($sku_keys,$arrayindex,$last_key){
        if (sizeof($sku_keys) > 0){
          global $customFields;
          global $modDate;
          $prodList = array(); # array that stores new/updated products
          $parsed = get_option('plugin-rentman-parsed');
          $lastbatch = "no";
          if($arrayindex == 0){
            update_option('plugin-rentman-products-update', "");
          }
          foreach($sku_keys as $x) {
            if($x == $last_key){
              $lastbatch = "yes";
            }
            $product_id = "";
            if(isset($parsed['response']['items']['Materiaal'][$x]['data']['woocommercepostid'])){
              $product_id = $parsed['response']['items']['Materiaal'][$x]['data']['woocommercepostid'];
            }

            # Get initial modification date from main table Materiaal and create a date to compare with
            $modDate = convertdate($parsed['response']['items']['Materiaal'][$x]['data']['modified']);

            # Force update for all the products, necessary on first import when the plugin is upgraded from Basic to Advanced.
            # After the import is completely done the option will be set to '1' again. (see also rentman.php)
            if(BASICTOADVANCED == 2){
              $modDate = convertdate(date('c'));
            }

            # What to do with every record (nothing, insert or update)
            $whatToDo = "nothing";

            # Create arrays for linked files (images,pdf's where in_shop = true)
            # Check all modified dates of linked files and update $modDate if necessary
            $nbrOfFiles = 0;
            if(isset($parsed['response']['items']['Materiaal'][$x]['links']['Files'])) {
                $nbrOfFiles =  sizeof($parsed['response']['items']['Materiaal'][$x]['links']['Files']);
            }
            $imageList = array();
            $imageIds = "";
            $pdfIds = "";

            $pdfList = array();
            for ($y = 0; $y < $nbrOfFiles; $y++) {
                $fileId = $parsed['response']['items']['Materiaal'][$x]['links']['Files'][$y];
                $imageCheck = $parsed['response']['items']['Files'][$fileId]['data']['image'];
                $pdfCheck = strtolower(substr($parsed['response']['items']['Files'][$fileId]['data']['name'], -3));
                $inshopCheck = $parsed['response']['items']['Files'][$fileId]['data']['in_shop'];

                if($inshopCheck) {
                    if($imageCheck) {
                        if(strtolower(substr($parsed['response']['items']['Files'][$fileId]['data']['name'], -4)) != "blob"){
                            array_push($imageList, array(
                                "file" => "",
                                "rentman_id" => $parsed['response']['items']['Files'][$fileId]['data']['id'],
                                "description" => $parsed['response']['items']['Files'][$fileId]['data']['description'],
                                "modified" => $parsed['response']['items']['Files'][$fileId]['data']['modified']
                            ));
                            $imageIds.=$parsed['response']['items']['Files'][$fileId]['data']['id'] . ",";
                        }
                    }
                    if($pdfCheck=="pdf") {
                        array_push($pdfList, array(
                            "file" => "",
                            "rentman_id" => $parsed['response']['items']['Files'][$fileId]['data']['id'],
                            "description" => str_replace('"', '', preg_replace('~[\r\n]+~', ' ', $parsed['response']['items']['Files'][$fileId]['data']['description'])),
                            "modified" => $parsed['response']['items']['Files'][$fileId]['data']['modified'],
                            "name" => $parsed['response']['items']['Files'][$fileId]['data']['name']
                        ));
                        $pdfIds.=$parsed['response']['items']['Files'][$fileId]['data']['id'] . ",";
                    }
                    # If the modified time of the file is bigger then the general $modDate, $modDate becomes this modified time
                    if(convertdate($parsed['response']['items']['Files'][$fileId]['data']['modified']) > $modDate) {
                        $modDate = convertdate($parsed['response']['items']['Files'][$fileId]['data']['modified']);
                    }
                }
            }

            # Check all modified dates of tags and update $modDate if necessary
            $taglist = preg_replace('/\s/', ' ', $parsed['response']['items']['Materiaal'][$x]['data']['taglist']);
            $nbrOfTags = 0;
            if(isset($parsed['response']['items']['Materiaal'][$x]['links']['Taglink'])) {
                $nbrOfTags =  sizeof($parsed['response']['items']['Materiaal'][$x]['links']['Taglink']);
            }

            for ($y = 0; $y < $nbrOfTags; $y++) {
                $tagId = $parsed['response']['items']['Materiaal'][$x]['links']['Taglink'][$y];
                if(convertdate($parsed['response']['items']['Taglink'][$tagId]['data']['modified']) > $modDate) {
                    $modDate = convertdate($parsed['response']['items']['Taglink'][$tagId]['data']['modified']);
                }
            }
            if($product_id) {
                # Function checks if a product needs te be updated
                $whatToDo = check_updated($x, $modDate, $product_id, $taglist, $imageIds,$pdfIds);
            }else{
                $whatToDo = "insert";
            }
            if($whatToDo == "nothing") {
                continue;
            }else{
                # Get the value for product featured on home
                $featured_on_home = ""; // = WooCommerce 3.0 or higher
                $featured_on_home_old = "no"; // < WooCommerce 3.0
                if ($parsed['response']['items']['Materiaal'][$x]['data']['shop_featured']) {
                  $featured_on_home = "featured";
                  $featured_on_home_old = "yes";
                }

                # Put all product data in array
                # If woocommerce_productid is empty an insert will take place, else the product already exists and will be updated
                $product_data = array(
                    "id" => $x,
                    "name" => trim($parsed['response']['items']['Materiaal'][$x]['data']['naam']),
                    "cost" => $parsed['response']['items']['Materiaal'][$x]['data']['verhuurprijs'],
                    "purchase_price" => $parsed['response']['items']['Materiaal'][$x]['data']['inhuurprijs'],
                    "long_desc" => $parsed['response']['items']['Materiaal'][$x]['data']['shop_description_long'],
                    "short_desc" => $parsed['response']['items']['Materiaal'][$x]['data']['shop_description_short'],
                    "folder_id" => $parsed['response']['items']['Materiaal'][$x]['data']['folder'],
                    "mod_date" => $modDate,
                    "weight" => $parsed['response']['items']['Materiaal'][$x]['data']['gewicht'],
                    "btw" => $parsed['response']['items']['Materiaal'][$x]['data']['standaardtarief'],
                    "verhuur" => $parsed['response']['items']['Materiaal'][$x]['data']['verhuur'],
                    "length" => $parsed['response']['items']['Materiaal'][$x]['data']['length'],
                    "width" => $parsed['response']['items']['Materiaal'][$x]['data']['width'],
                    "height" => $parsed['response']['items']['Materiaal'][$x]['data']['height'],
                    "amount" => $parsed['response']['items']['Materiaal'][$x]['data']['aantalberekend'],
                    "lowstocktreshold" => $parsed['response']['items']['Materiaal'][$x]['data']['verkoopalarm'],
                    "tag_list" => strtolower(preg_replace('/\s/', ' ', $parsed['response']['items']['Materiaal'][$x]['data']['taglist'])),
                    "seo_title" => $parsed['response']['items']['Materiaal'][$x]['data']['shop_seo_title'],
                    "seo_metadesc" => $parsed['response']['items']['Materiaal'][$x]['data']['shop_seo_description'],
                    "seo_focuskw" => $parsed['response']['items']['Materiaal'][$x]['data']['shop_seo_keyword'],
                    "featured_on_home" => $featured_on_home,
                    "featured_on_home_old" => $featured_on_home_old,
                    "images" => $imageList,
                    "pdfs" => $pdfList,
                    "woocommerce_productid" => $product_id,
                );

                //add rentman custom fields if wanted (=defined in product_customfields.php)
                foreach ($customFields as $customField) {
                  $product_data[$customField[1]] = $parsed['response']['items']['Materiaal'][$x]['data'][$customField[0]];
                }
                array_push($prodList, $product_data);
            }
          }
          $pluginrentmanproductsupdate = get_option('plugin-rentman-products-update');
          if($pluginrentmanproductsupdate == ""){
            $pluginrentmanproductsupdate = $prodList;
          }else{
            $pluginrentmanproductsupdate = array_merge($pluginrentmanproductsupdate, $prodList);
          }
          update_option('plugin-rentman-products-update', $pluginrentmanproductsupdate);
          if($lastbatch == "yes"){
            # Put all the images in $pluginrentmanproductsupdate into seperate array => $images_array
            $nbrOfProducts = sizeof($pluginrentmanproductsupdate);
            $images_array = array();
            $pdf_array = array();
            $import_log = "Import: " . date("d-m-Y h:i:s a") . " (=server time)\n";

            for ($z = 0; $z < $nbrOfProducts; $z++) {
                $import_log.= ($z + 1) . ": " . $pluginrentmanproductsupdate[$z]["name"]  . "\n";
                $key = $pluginrentmanproductsupdate[$z]["id"];
                foreach ($pluginrentmanproductsupdate[$z]["images"] as $imgFile){
                    if (!isset($images_array[$key])){
                         $images_array[$key] = array(array("", $imgFile["rentman_id"], $imgFile["description"], convertdate($imgFile["modified"])));
                    } else { # If more than one file is attached to the product
                        array_push($images_array[$key], array("", $imgFile["rentman_id"], $imgFile["description"], convertdate($imgFile["modified"])));
                    }
                }

                # Put all the pdfs in $pluginrentmanproductsupdate into seperate array => $pdf_array
                foreach ($pluginrentmanproductsupdate[$z]["pdfs"] as $pdfFile){
                    if (!isset($pdf_array[$key])){
                         $pdf_array[$key] = array(array("", $pdfFile["rentman_id"], $pdfFile["description"], convertdate($pdfFile["modified"]), $pdfFile["name"]));
                    } else { # If more than one file is attached to the product
                        array_push($pdf_array[$key], array("", $pdfFile["rentman_id"], $pdfFile["description"], convertdate($pdfFile["modified"]), $pdfFile["name"]));
                    }
                }
            }
            rentman_import_log($import_log . "---ACTUAL IMPORT---\n", 1);
            update_option('plugin-rentman-images', $images_array);
            update_option('plugin-rentman-pdfs', $pdf_array);
            unset($images_array);
            unset($pdfs_array);
          }
          unset($parsed);
          unset($prodList);
          unset($pluginrentmanproductsupdate);
        }
    }

    function import_product_categories($token, $url){
        # Import the product categories
        $message = json_encode(setup_folder_request($token), JSON_PRETTY_PRINT);
        $received = do_request($url, $message);
        $parsed = json_decode($received, true);
        $parsed = parseResponse($parsed);

        # Arrange the response data in a new clear array
        $folder_arr = arrange_folders($parsed);

        # Create new Categories by using the imported folder data
        foreach ($folder_arr as $folder) {
            add_category($folder);
        }
    }

    # Imports five products from the product array, starting from the received index
    //function array_to_product($prod_array, $images_array, $pdfs_array, $startIndex,$token, $upload_batch){
    function array_to_product($startIndex,$token, $upload_batch){
        $prod_array = get_option('plugin-rentman-products-update');
        if (sizeof($prod_array) > 0){
            _e('<br><br><b>Imported Products:</b><br>', 'rentalshop');

            # Import the products in the WooCommerce webshop
            $endIndex = $startIndex + ($upload_batch - 1);
            $total_products = sizeof($prod_array);
            for ($index = $startIndex; $index <= $endIndex; $index++){
                if ($index >= $total_products)
                    break;

                if($index == $startIndex){
                    //rentman_import_log("\n", 0);
                }

                if($endIndex > $total_products){
                    //rentman_import_log(($index + 1) . " / " . $total_products . " - ", 0);
                    rentman_import_log(($index + 1) . " - ", 0);
                }else{
                    rentman_import_log(($index + 1) . " - ", 0);
                }

                import_product($prod_array[$index], $token);
                echo 'Index of Product:' . $prod_array[$index]['id'];
            }
        }
    }


    # Compare the list of updated products with the current product list
    # and delete all products that aren't available in Rentman anymore
    function compare_and_delete($checkList){
        global $wpdb;
        $all_products = get_product_list_rentman();
        $remainder = array_diff($all_products, $checkList);

        if (sizeof($remainder) > 0){
            # Now delete the posts in the remainder array
            foreach ($remainder as $item){
                $postID = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $item));
                if (get_post_meta($postID, 'rentman_imported', true) == true){
                  # Delete attached images
                  $media = get_children(array('post_parent' => $postID, 'post_type' => 'attachment'));
                  foreach ($media as $file){
                      wp_delete_post($file->ID);
                  }
                  wp_delete_post($postID);
                }
            }
        }
    }

    # Get all 'Rentable' products stored in WooCommerce
    function get_product_list_rentman(){
        $full_product_list = array();
        $args = array('post_type' => 'product', 'post_status' => 'any', 'posts_per_page' => -1, 'rentman_imported' => true, 'fields' => 'ids');
        $posts = get_posts($args);
        foreach($posts as $array) {
          array_push($full_product_list, get_post_meta($array, "_sku",true));
        }
        return $full_product_list;
    }

    # Use the array of imported projects from Rentman to create
    # new products in WooCommerce
    function import_product($product, $token){
        global $customFields;

        if (!isset($_SESSION['rentman_taxrates_session'])){
          gettaxrates();
        }

        if (empty($product['long_desc'])){
            $content = __('No information available', 'rentalshop');
        } else{
            $content = $product['long_desc'];
        }

        # Create new product (IF "ID" is equal to something other than 0, the post with that ID will be updated. Default 0.)
        $menu_order = 0;
        if($product['woocommerce_productid'] != ""){
          $thispost = get_post($product['woocommerce_productid']);
          $menu_order = $thispost->menu_order;
        }

        $new_product = array(
            "post_name" => $product['name'],
            "post_title" => $product['name'],
            "post_content" => $content,
            "post_excerpt" => $product['short_desc'],
            "post_date" => $product['mod_date'],
            "post_date_gmt" => $product['mod_date'],
            "post_status" => "publish",
            "post_type" => "product",
            "menu_order" => $menu_order,
            "ID" =>  $product['woocommerce_productid']
        );

        # Check Category
        $categoryIDs = get_option('plugin-rentmanIDs', []);
        $checkterm = get_term($categoryIDs[$product['folder_id']], 'product_cat');

        # Insert post (or update)
        $post_id = wp_insert_post($new_product, TRUE);

        # Other method for setting category
        wp_set_object_terms($post_id, $checkterm->term_id, 'product_cat');

        $tags = "";
        if($product['tag_list'] != ""){
          $tags = explode(",", $product['tag_list']);
        }
        wp_set_object_terms($post_id, $tags, 'product_tag');


        # If it is a 'verhuur' product, the type is set to 'rentable'
        if ($product['verhuur']) {
            wp_set_object_terms($post_id, 'rentable', 'product_type');
        }else{ # Otherwise it is a 'simple product'
            wp_set_object_terms($post_id, 'simple_product', 'product_type');
        }

        #Generate seo-title if nothing is filled in
        if($product['seo_title'] == ""){
          $product['seo_title'] = $product['name'] . " - " . get_bloginfo();
        }

        #Generate seo-metadesc if nothing is filled in
        if ($product['seo_metadesc'] == "") {
          if ($product['short_desc'] != "") {
            $product['seo_metadesc'] = $product['short_desc'];
          }
          if ($product['long_desc'] != "") {
            $product['seo_metadesc'] = $product['long_desc'];
          }
        }

        # Add/update the post meta of the product
        update_post_meta($post_id, 'rentman_imported', true);
        update_post_meta($post_id, '_rentman_tax', $product['btw']);
        if($product['btw'] == $_SESSION['rentman_taxrates_session']['reduced_rate'] && $_SESSION['rentman_taxrates_session']['reduced_rate']!=0){
          update_post_meta($post_id, '_tax_status', "taxable");
          update_post_meta($post_id, '_tax_class', "reduced-rate");
        }
        if($product['btw'] == $_SESSION['rentman_taxrates_session']['standard_rate'] && $_SESSION['rentman_taxrates_session']['standard_rate']!=0){
          update_post_meta($post_id, '_tax_status', "taxable");
          update_post_meta($post_id, '_tax_class', "");
        }
        if($product['btw'] == 0){
          update_post_meta($post_id, '_tax_status', "taxable");
          update_post_meta($post_id, '_tax_class', "zero-rate");
        }
        update_post_meta($post_id, '_visibility', 'visible');
        update_post_meta($post_id, '_stock_status', 'instock');
        update_post_meta($post_id, 'total_sales', '0');
        update_post_meta($post_id, '_downloadable', 'no');
        update_post_meta($post_id, '_virtual', 'no');
        update_post_meta($post_id, '_regular_price', $product['cost']);
        update_post_meta($post_id, '_purchase_note', "");
        update_post_meta($post_id, '_featured', $product['featured_on_home_old']);
        update_post_meta($post_id, '_weight', $product['weight']);
        update_post_meta($post_id, '_length', $product['length']);
        update_post_meta($post_id, '_width', $product['width']);
        update_post_meta($post_id, '_height', $product['height']);
        update_post_meta($post_id, '_sku', $product['id']);
        //update_post_meta($post_id, '_product_attributes', array());
        update_post_meta($post_id, '_sale_price_dates_from', "");
        update_post_meta($post_id, '_sale_price_dates_to', "");
        update_post_meta($post_id, '_price', $product['cost']);
        update_post_meta($post_id, '_purchase_price', $product['purchase_price']);
        update_post_meta($post_id, '_sold_individually', "");
        update_post_meta($post_id, '_manage_stock', "no");
        update_post_meta($post_id, '_backorders', "no");
        update_post_meta($post_id, '_stock', $product['amount']);
        update_post_meta($post_id, '_yoast_wpseo_title', $product['seo_title']);
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $product['seo_metadesc']);
        update_post_meta($post_id, '_yoast_wpseo_focuskw', $product['seo_focuskw']);
        update_post_meta($post_id, '_yoast_wpseo_focuskw_text_input', $product['seo_focuskw']);
        //update_post_meta($post_id, 'qwc_enable_quotes', on);
        wp_update_post( array('ID' => $post_id, 'comment_status' => 'open'));

        $extraimportfields = apply_filters( 'rentman_save_extra_importfields', $product,$post_id);

        foreach ($customFields as $customField) {
          update_post_meta($post_id, $customField[1], $product[$customField[1]]);
        }

        # Product featured on homepage? If $product['featured_on_home'] is empty it will be deleted, else inserted
        # WooCommerce 3.0 or higher
        wp_set_object_terms($post_id, $product['featured_on_home'], 'product_visibility');

        # Attach the media files to the post
        $uploadbatch = apply_filters( 'rentman_change_batch_upload', '5');
        if($uploadbatch!='20'){
            $file_list = get_option('plugin-rentman-images');
            $pdf_list = get_option('plugin-rentman-pdfs');
            attach_media($file_list[$product['id']], $post_id, $product['id'], $product['name'], $token);
            attach_pdf($pdf_list[$product['id']], $post_id, $product['id'], $product['name'], $token);
            unset($file_list);
            unset($pdf_list);
        }
    }

    // ------------- Various Functions ------------- \\

    # Delete products with a javascript function
    function reset_rentman(){
        $full_product_list = array();
        $args = array('post_type' => 'product', 'posts_per_page' => -1, 'post_status' => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash'), 'rentman_imported' => true, 'fields' => 'ids');
        $posts = get_posts($args);
        foreach($posts as $array) {
          array_push($full_product_list, get_post_meta($array, "_sku",true));
        }

        $categoryIDs = get_option('plugin-rentmanIDs', []); // get global category array
        if (!is_array($categoryIDs)){
            $categoryIDs = [];
            update_option('plugin-rentmanIDs', $categoryIDs); // update if not an array
        }

        # Register and localize the admin_delete.js file, which handles the reset
        wp_register_script('admin_del_product', plugins_url('js/admin_delete.js?' . get_plugin_data(realpath(dirname(__FILE__)) . '/rentman.php')['Version'], __FILE__ ));
        wp_localize_script('admin_del_product', 'products', $full_product_list);
        wp_localize_script('admin_del_product', 'arrayindex', '0');
        wp_localize_script('admin_del_product', 'string1', __('<b>Products deleted:</b> ', 'rentalshop'));
        wp_localize_script('admin_del_product', 'string2', __('<br>Reset was successful!', 'rentalshop'));
        wp_enqueue_script('admin_del_product');
    }

    # Delete up to 15 products from the array starting with a certain index
    function delete_by_index($posts, $startIndex){
        global $wpdb;
        $endIndex = $startIndex + 9;
        for ($index = $startIndex; $index <= $endIndex; $index++){
            if ($index >= sizeof($posts))
                break;
            $object = $posts[$index];
            $product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $object));
            if (get_post_meta($product_id, 'rentman_imported', true) == true){
                # Delete product and the attached images
                $media = get_children(array('post_parent' => $product_id, 'post_type' => 'attachment'));
                foreach ($media as $file){
                    wp_delete_post($file->ID, true);
                }
                wp_delete_post($product_id);
            }
        }
    }

    # Checks if last modified date is different
    function check_updated($sku, $rentdate, $post_id, $taglist, $imageids,$pdfIds){
        global $wpdb;
        global $modDate;
        # Check if $rentdate (=highest Rentman modified date) > $woodate
        //$postID = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku));
        $postID = $post_id;
        //$woodate = convertdate(get_the_date('c', $postID));
        $woodate = get_the_date('Y-m-d H:i:s', $postID);
        //$trash = get_post_status($post_id);
        //if ($woodate < $rentdate && $trash != 'trash') {
        if ($woodate < $rentdate) {
            return "update"; # Needs to be updated
        }

        # Check if tags are the same in Rentman and WordPress, needed in case tags are deleted in Rentman (no modified date in Rentman if tags are deleted)
        $terms = get_the_terms($post_id, 'product_tag');
        $termscheck = [];
        if (!empty($terms) && !is_wp_error($terms)){
            foreach ( $terms as $term ) {
                $termscheck[]= strtolower($term->name);
            }
            $taglist = explode(", ", strtolower($taglist));
            sort($taglist);
            sort($termscheck);
            if($taglist != $termscheck) {
                return "update";
            }
        }

        # Check if images are the same in Rentman and WordPress, needed in case images are deleted in Rentman (no modified date in Rentman if images are deleted)
        # Also check if modified dates are the same. For instance: When description for alt tag is changed, Modificationdate wil be modified too
        $images = get_children( array (
          'post_parent' => $post_id,
          'post_type' => 'attachment',
          'post_mime_type' => 'image',
          'orderby' => 'ID',
          'order' => 'ASC'
        ));

        $updatetime = false;
        $filescheck = [];
        if (!empty($images) ) {
            foreach ( $images as $attachment_id => $attachment ) {
                $image_id_rentman = explode("-", $attachment->post_title);
                $filescheck[]=  $image_id_rentman[count($image_id_rentman) - 1];
                if($modDate < $attachment->post_date){
                    $modDate = $attachment->post_date;
                    $updatetime = true;
                }
            }
        }
        $pos = strpos($imageids, ",");
        if ($pos !== false) {
          $imageids = substr($imageids, 0, -1);
          $imageids = explode(",", $imageids);
        }else{
          $imageids = [];
        }
        sort($imageids);
        sort($filescheck);
        if($imageids != $filescheck || $updatetime) {
            return "update";
        }

        # Check if pdfs are the same in Rentman and WordPress, needed in case pdfss are deleted in Rentman (no modified date in Rentman if pdfs are deleted)
        # Also check if modified dates are the same. For instance: When description is changed, Modificationdate wil be modified too
        $filescheck = [];
        $pdfs = get_children( array (
          'post_parent' => $post_id,
          'post_type' => 'attachment',
          'post_mime_type' => 'application/pdf',
          'orderby' => 'ID',
          'order' => 'ASC'
        ));

        $updatetime = false;
        if (!empty($pdfs) ) {
            foreach ( $pdfs as $attachment_id => $attachment ) {
                $pdf_id_rentman = explode("-", substr($attachment->guid, 0, -4));
                $filescheck[]=  $pdf_id_rentman[count($pdf_id_rentman) - 1];
                if($modDate < $attachment->post_date){
                    $modDate = $attachment->post_date;
                    $updatetime = true;
                }
            }
        }

        $pos = strpos($pdfIds, ",");
        if ($pos !== false) {
          $pdfIds = substr($pdfIds, 0, -1);
          $pdfIds = explode(",", $pdfIds);
        }else{
          $pdfIds = [];
        }
        sort($pdfIds);
        sort($filescheck);

        if($filescheck != $pdfIds || $updatetime) {
            return "update";
        }
        return "nothing";
    }

    # Returns list of identifiers when given a list of products
    function list_of_ids($prodList){
        $id_list = array();
        foreach ($prodList as $item){
            array_push($id_list, $item['id']);
        }
        return $id_list;
    }

    # Returns list of identifiers of all imported Rentman products
    function rentman_ids(){
        $full_product_list = array();
        $args = array('post_type' => 'product', 'posts_per_page' => -1, 'rentman_imported' => true);
        $pf = new WC_Product_Factory();
        $posts = get_posts($args);
        for ($x = 0; $x < sizeof($posts); $x++){
            $post = $posts[$x];
            $product = $pf->get_product($post->ID);
            array_push($full_product_list, $product->get_sku());
        }
        return $full_product_list;
    }

    # Register 'Rentable' Product Type for imported products
    function register_rental_product_type(){
        class WC_Product_Rentable extends WC_Product{ # Extending Product Class

            public function __construct($product){
                $this->product_type = 'rentable';
                parent::__construct($product);
            }

            public function add_to_cart_text(){
                return apply_filters('woocommerce_product_add_to_cart_text', __('Read more','rentalshop'), $this);
            }

            public function add_to_cart_url(){
                $url = get_permalink($this->id);
                return apply_filters('woocommerce_product_add_to_cart_url', $url, $this);
            }

            public function needs_shipping(){
                return true;
            }
        }
    }

    # Make new product type selectable in Wordpress admin menu
    function add_rentable_product($types){
        $types['rentable'] = 'Rentable';
        return $types;
    }

    # Get WooCommerce Standard and recuced vat rate and store in session
    function gettaxrates(){
      $taxes = WC_Tax::get_base_tax_rates('');
      $taxes = array_shift($taxes);
      $vatStandardRate = round($taxes['rate'])/100;

      $taxes = WC_Tax::get_base_tax_rates('reduced-rate');
      $taxes = array_shift($taxes);
      $vatReducedRate = round($taxes['rate'])/100;

      $_SESSION['rentman_taxrates_session'] = array(
          'standard_rate' => $vatStandardRate,
          'reduced_rate' => $vatReducedRate
      );
    }

    # Use database query to check if the product that
    # is going to be imported already exists
    function wp_exist_post_by_title($title){
        global $wpdb;
        $query = $wpdb->get_row("SELECT ID FROM wp_posts WHERE post_title = '" . $title . "' && post_status = 'publish'
            && post_type = 'product' ", 'ARRAY_N');
        if (empty($query)){
            return false; # Product does not exist
        } else{
            return true; # Product already exists
        }
    }

?>

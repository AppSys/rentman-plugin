<?php
    // ------------- Rentman Product Category Functions ------------- \\

    # Names of (sub)folders have been imported
    # Create the categories in Wordpress
    function add_category($folder){
        # Insert WooCommerce Term
        $categoryIDs = get_option('plugin-rentmanIDs', []); // get global category array

        if (!is_array($categoryIDs)){
            $categoryIDs = [];
            update_option('plugin-rentmanIDs', $categoryIDs); // update if not an array
        }

        # Check if the array contains the ID
        if (!isset($categoryIDs[$folder[0]]))
            $receive_term = '';
        else
            $receive_term = get_term($categoryIDs[$folder[0]], 'product_cat');

        # Add the category if it does not exist yet
        if ($receive_term == ''){
            $parent_term = isset($categoryIDs[$folder[2]]) ? get_term($categoryIDs[$folder[2]], 'product_cat') : null;
            $parent_term_id = !is_null($parent_term) ? $parent_term->term_id : null;
            $category = wp_insert_term(
                $folder[1], # Name of term
                'product_cat', # Taxonomy
                array(
                    'parent' => $parent_term_id
                )
            );
            if (!is_wp_error($category)){
                $current_id = $category['term_id']; // get ID of the category that was just created
                add_term_meta($current_id, "source", 'Rentman'); // add Rentman as source
                $categoryIDs = get_option('plugin-rentmanIDs', []); // get global category array
                $categoryIDs[$folder[0]] = $current_id; // add the product ID to array
                update_option('plugin-rentmanIDs', $categoryIDs); // update the array
            }
        }else{
          # If folder name is changed, update in WordPress
            if($folder[1] != $receive_term->name) {
                wp_update_term($receive_term->term_id, 'product_cat', array(
                    'name' => $folder[1],
                    'slug' => sanitize_title($folder[1])
                ));
            }
        }
    }

    # Arranges folder data from API response
    function arrange_folders($parsed){
        $folderList = [];
        $idList = [];
        $switch = true;
        $counter = 1;
		    $lastkey = end($parsed['response']['items']['Folder'])['data']['id'];
        $categoriesLeft = true;

        # Organize the folders based on their parent
        while ($categoriesLeft){
            while ($switch){
                $name = isset($parsed['response']['items']['Folder'][$counter]['data']['naam']) ? $parsed['response']['items']['Folder'][$counter]['data']['naam'] : null;
                $id = isset($parsed['response']['items']['Folder'][$counter]['data']['id']) ? $parsed['response']['items']['Folder'][$counter]['data']['id'] : null;
                $parent = isset($parsed['response']['items']['Folder'][$counter]['data']['parent']) ? $parsed['response']['items']['Folder'][$counter]['data']['parent'] : null;
                //$sortorder = isset($parsed['response']['items']['Folder'][$counter]['data']['volgorde']) ? $parsed['response']['items']['Folder'][$counter]['data']['parent'] : null;
                if (!isset($idList[$id])){ # Check if the item already has been added
                    if ($parent == null) {
                        //array_push($folderList, array($id, $name, $parent,$sortorder));
                        array_push($folderList, array($id, $name, $parent));
                        $idList[$id] = true;
                    }
                    else{ # Check if parent has already been created
                        if (isset($idList[$parent])){
                            //array_push($folderList, array($id, $name, $parent,$sortorder));
                            array_push($folderList, array($id, $name, $parent));
                            $idList[$id] = true;
                        }
                    }
                }
                if ($id == $lastkey) # Last folder in the array has been reached
                    $switch = false;
                else
                    $counter++;
            }
            # Check whether to reset the previous while loop
            if (sizeof($folderList) < sizeof($parsed['response']['items']['Folder'])){
                $switch = true;
                $counter = 1;
            }
            else # Finished if all categories have been added
                $categoriesLeft = false;
        }
        return $folderList;
    }

    # Delete all irrelevant product subcategories in the webshop (in other words, the empty ones)
    function remove_empty_categories(){
        $args = array(
            'taxonomy'     => 'product_cat',
            'orderby'      => 'name',
            'show_count'   => 0,
            'pad_counts'   => 0,
            'hierarchical' => 1,
            'title_li'     => '',
            'hide_empty'   => 0
        );
        $all_categories = get_categories($args);
        foreach($all_categories as $cat){
            if($cat->category_parent == 0){
                # Check the children of the category
                $children = get_categories(array('taxonomy' => 'product_cat', 'parent' => $cat->term_id, 'hide_empty' => 0));
                $itemCount = max($cat->count, displayChildren($children));
                # Delete term if the category and all children are empty
                if ($itemCount == 0){
                    $source = get_term_meta($cat->term_id, 'source', true);
                    if ($source == 'Rentman'){ # Check if the category originates from Rentman
                        $categoryIDs = get_option('plugin-rentmanIDs', []);
                        $keychain = array_search($cat->term_id, $categoryIDs);
                        unset($categoryIDs[$keychain]);
                        update_option('plugin-rentmanIDs', $categoryIDs); // update the array
                        wp_delete_term($cat->term_id, 'product_cat');
                    }
                }
            }
        }
        update_option('plugin-rentman-parsed', '');
        update_option('plugin-rentman-products-update', '');
        update_option('plugin-rentman-images', '');
        update_option('plugin-rentman-pdfs', '');
    }

    # Check item count for children
    function displayChildren($children){
        $itemCount = 0;
        foreach ($children as $child){
            $itemCount = max($itemCount, $child->count);
            # Recursively keep checking the size of all children
            $grandchildren = get_categories(array('taxonomy' => 'product_cat', 'parent' => $child->term_id, 'hide_empty' => 0));
            $thiscount = displayChildren($grandchildren);
            $itemCount = max($itemCount, $thiscount);
            # Delete term if the category and all children are empty
            if (max($thiscount, $child->count) == 0){
                $source = get_term_meta($child->term_id, 'source', true);
                if ($source == 'Rentman') { # Check if the category originates from Rentman
                    $categoryIDs = get_option('plugin-rentmanIDs', []);
                    $keychain = array_search($child->term_id, $categoryIDs);
                    unset($categoryIDs[$keychain]);
                    update_option('plugin-rentmanIDs', $categoryIDs); // update the array
                    wp_delete_term($child->term_id, 'product_cat');
                }
            }
        }
        return $itemCount;
    }

?>

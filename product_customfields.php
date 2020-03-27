<?php
    // ------------- custom fields to import ------------- \\

    # Custom fields for products if necessary (has to match with rentman)
    # $customFields[][0] = fieldname in Rentman
    # $customFields[][1] = fieldname in WordPress -> Table wp_postmeta -> field meta_key
    $customFields = [];
    #If customfields are set in wp_options
    if(is_array(get_option('plugin-rentman-customfields'))){
      $customFields = get_option('plugin-rentman-customfields');
    }
?>

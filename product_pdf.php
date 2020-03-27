<?php
  // ------------- Pdf File Attachment Function ------------- \\
  
  # Adds a pdf download section to the description of a single product page
  function show_product_pdf($content){
      // Only for single product pages (woocommerce)
      if (is_product()){
        global $post;
        $custom_content = "";
        if(isset($post->_pdf_id)){
          $pdfs = get_children( array (
            'post_parent' => $post->ID,
            'post_type' => 'attachment',
            'post_mime_type' => 'application/pdf',
            'orderby' => 'ID',
            'order' => 'ASC'
          ));
          if (!empty($pdfs)) {
            $custom_content = __("<p><strong><u>Downloads</u></strong></p>", "rentalshop");
            $custom_content.= "<p>";
            foreach ( $pdfs as $attachment_id => $attachment ) {
              $custom_content.='<a href="' . $attachment->guid . '" target="_blank"><i class="fa fa-file-pdf-o" aria-hidden="true"></i> ' . $attachment->post_title . '</a><br>';
            }
            $custom_content.= "</p>";
            $content.= $custom_content;
          }
        }
      }
      return $content;
  }

  // Filter used to check if there are pdfs attached to a single product and display them
  function check_for_pdfs(){
      add_filter('the_content', 'show_product_pdf', 10, 5);
  }
?>

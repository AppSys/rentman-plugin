// ----- JavaScript functions for updating the label of daily price ----- \\
jQuery().ready(function() {
  if (typeof subtotalTextoverwrite !== 'undefined') {
    subtotalText = subtotalTextoverwrite;
  }
  jQuery("table tr.cart-subtotal>th:first").html(subtotalText);
  jQuery("table tr.cart-subtotal>th:first").show();
  
  jQuery( document.body ).on( 'updated_checkout', function(){
      jQuery("table tr.cart-subtotal>th:first").html(subtotalText);
      jQuery("table tr.cart-subtotal>th:first").show();
  });
});

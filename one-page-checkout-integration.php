<?php
// I promise, I only resorted to using regex to parse the checkout page as a last resort.
// I've seen, laughed, and now cried looking at this stackoverflow answer:
// https://stackoverflow.com/questions/1732348/regex-match-open-tags-except-xhtml-self-contained-tags/1732454#1732454
// If you want to know why this is built this way, try doing this:
// 		`do_shortcode("[woocommerce_checkout]");`

function getCheckoutPageContentCallBack() {
	sleep(1);
	global $woocommerce;
	$checkout_url = $woocommerce->cart->get_checkout_url() . "?persistant-cart=true";

	$cookies = "";
	foreach ( $_COOKIE as $key => $value ) {
		$cookies .= "$key=$value;";
	}

	// Create a stream
        $opts = array(
       		'http'=>array(
			'method'=>"GET",
			'header'=>"Accept-language: en\r\n" .
              		"Cookie: $cookies"
  	        )
	);
	$context = stream_context_create($opts);

	// Open the file using the HTTP headers set above
	$raw_html = file_get_contents($checkout_url, false, $context);
	// Trim all of the newlines
	$raw_html = trim(preg_replace('/\s\s+/', ' ', $raw_html));
	$raw_html = trim(preg_replace('/\n/', ' ', $raw_html));

	// Remove Javascript comments
	$raw_html = trim(preg_replace("/\/\* \<\!\[CDATA\[ \*\//", ' ', $raw_html));

	preg_match_all("/<script[^\>]*>[^<]*<\/script>/", $raw_html, $scripts_array);
	$scripts = implode("\n",$scripts_array[0]);

	//preg_match_all("/<div class=\\"woocommerce\\">.*<\/form>.*<\/form>.*<\/div>/", $input_lines, $output_array);
	preg_match_all("/<div class=\"woocommerce\">.*<\/form>.*<\/form>/", $raw_html, $html_array);
	$html = $html_array[0][0] . "</div>";

	if ( $html == "" ) {
		$html = ""; // Put error message here
	}
	// Fix for Divi theme
	$html = explode("</div> <!-- .entry-content -->", $html)[0];
	//$html = htmlspecialchars($html);
	return "$html $scripts";
}

function add_query_string_if_missing(){
	if ( !isset( $_GET['persistant-cart']) ) {
		$url_with_query_string = add_query_arg(
			'persistant-cart',
			'true',
			(isset($_SERVER['HTTPS']) ? "https" : "http")."://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"
		);
		@header('Location: '. $url_with_query_string);
		exit();
	}
}

function gn_woocommerce_one_page_checkout( $atts ){
	add_query_string_if_missing();

  // Get product ids from product guids
  $product_ids = explode( ',', $atts['product_ids'] );

  $out = "";

  // Compare what is currently in the cart with what we want to add
  $current_cart_items = array();
  foreach( WC()->cart->get_cart() as $cart_item ){
    // compatibility with WC +3
    if( version_compare( WC_VERSION, '3.0', '<' ) ){
      $product_id = $cart_item['data']->id; // Before version 3.0
    } else {
      $product_id = $cart_item['data']->get_id(); // For version 3 or more
    }
    array_push( $current_cart_items, $product_id );
  }
  $cart_items_already_added = $current_cart_items == $product_ids;

  // If there are products we want to use this checkout with, empty the cart and add them all in.
  if (!$cart_items_already_added) {
    // Empty the cart
    WC()->cart->empty_cart( false );

    // Put all of the products into the cart
    for ($i=0; $i < count($product_ids); $i++) {
      $product_id = $product_ids[$i] * 1;
      WC()->cart->add_to_cart( $product_id, 1, null, null);
    }
	  $pageHTML = getCheckoutPageContentCallBack();
		if (
			stripos($pageHTML,'login') !== false &&
			stripos($pageHTML,'expired') !== false ||
			stripos($pageHTML,'woocommerce') === false ||
			strlen($pageHTML) < 10
		) {
			@header("Refresh:0");
			return '<img src="http://genoolabs.com/lms-updates/img/tenor.gif" style="margin: 0 auto; display: block;"/>';
		}
  } else {
    $out = "<!-- No product added -->";
  }

  $additional_css = "<style>
@import url(\"wp-content/plugins/woocommerce/assets/css/woocommerce.css\");
@import url(\"wp-content/plugins/woocommerce-subscriptions/assets/css/checkout.css\");
@import url(\"wp-content/plugins/woocommerce/assets/css/select2.css?ver=3.3.5\");
@import url(\"".get_stylesheet_directory_uri()."/style.css\");
[id^=\"checkout-\"] + *{display: none;}
[for=\"payment_method_stripe\"] { width: 100%; clear: both; }
.stripe-visa-icon { clear: both; }
.stripe-icon { width: 40px; }
#customer_details .col-1 { width: 100%; }
#customer_details .col-2 { display: none; }
.woocommerce button.button:not([name=\"apply_coupon\"]) {
  background: #449944 !important;
  border: none !important;
  color: #ffffff !important;
  width: 100%;
}
.woocommerce button.button:hover{
  opacity: 0.8;
}</style>";

  return $additional_css . getCheckoutPageContentCallBack();
}

add_shortcode('gn_woocommerce_one_page_checkout', 'gn_woocommerce_one_page_checkout');

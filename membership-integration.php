<?php
/*
Plugin Name: Genoo WPMktgEngine eCommerce Tools
Description: Essential plugin for member websites to integrate nicely between LifterLMS, WooCommerce, One Page Checkout and WPMktgEngine plugins
Author: Genoo LLC
Version: 2.31
Author URI: http://www.genoo.com/
Text Domain: woocommerce-lifterlms-membership-extention
*/

function enroll_student_on_connected_site_wpme_genoo_etools( $email, $username, $membership_id ){
  $url = get_option('satellite_site_settings')["satellite_site_url"];
  $token = get_option('satellite_site_settings["satellite_site_token"]');

  // Generate a random password for the new user
  $possible_characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $password = substr(str_shuffle(str_repeat($x=$possible_characters, ceil(14/strlen($x)) )),1,14);

  // Prepare new cURL resource
  $data = array(
    'username' => $username,
    'password' => $password,
    'email' => $email,
    'website' => get_site_url(),
    'memberships' => "$membership_id"
  );
  $payload = json_encode($data);
  // echo "$url/wp-json/wp/v2/satellite/new_user/ -- $payload";

  $ch = curl_init("$url/wp-json/wp/v2/satellite/new_user/");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLINFO_HEADER_OUT, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  $headers = array(
    "Authorization:Basic $token"
  );
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  // Submit the POST request
  $result = json_decode(curl_exec($ch));

  // Close cURL session handle
  curl_close($ch);
  return $result;
}

add_action( 'woocommerce_thankyou', 'wpme_llms_catch_checkout_to_add_memberships');
add_action( 'woocommerce_payment_complete', 'wpme_llms_catch_checkout_to_add_memberships');
function wpme_llms_catch_checkout_to_add_memberships( $order_id ){
  $order = new WC_Order( $order_id );
  $items = $order->get_items();
  $user_id = get_current_user_id();

  foreach ( $items as $item ) {
		$id = $item->get_product_id();
		$memberships_bought = json_decode(str_replace("'","\"",get_post_meta( $id, 'connected_memberships', true )));

    if ( $memberships_bought->domain == get_site_url() ) {
		$student = new LLMS_Student( $user_id );
  		llms_enroll_student( $student, $memberships_bought->ID, get_site_url() );
    } else {
      $userdata = get_userdata( $user_id );
      enroll_student_on_connected_site_wpme_genoo_etools( $userdata->user_email, $userdata->user_login, $memberships_bought->ID );
    }

  }
}



// function that creates the new metabox that will show on post
function connected_memberships_metabox() {
    add_meta_box(
        'connected_memberships',  // unique id
        'Membership to assign users to when they buy this product',  // metabox title
        'connected_memberships_display',  // callback to show the dropdown
        'product'   // post type
    );
}
// action to add meta boxes
add_action( 'add_meta_boxes', 'connected_memberships_metabox' );

function getConnectedSiteMemberships( $url ) {
  if ( !isset($url) || !$url ) {
	return array();
  };

  // Prepare new cURL resource
  $ch = curl_init("$url/wp-json/wp/v2/llms_membership?per_page=100&order=desc");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLINFO_HEADER_OUT, true);
  curl_setopt($ch, CURLOPT_GET, true);
  $token = get_option('satellite_site_settings["satellite_site_token"]');
  $headers = array("Authorization:Basic $token");
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  // Submit the POST request
  $result = json_decode(curl_exec($ch));

  // Close cURL session handle
  curl_close($ch);

  return $result;
}

// llms_woo dropdown display
function connected_memberships_display( $post ) {
  // get current value
  $dropdown_value = get_post_meta( get_the_ID(), 'connected_memberships', true );
  $dropdown_value = str_replace("'", "\"", $dropdown_value);
  $dropdown_value = json_decode( $dropdown_value );

  // Use nonce for verification
  wp_nonce_field( basename( __FILE__ ), 'connected_memberships_nonce' );
  $connected_url = get_option('satellite_site_settings')["satellite_site_url"];

  $connected_memberships = getConnectedSiteMemberships($connected_url);
  $connected_memberships_options = "";

  if ( count($connected_memberships) != 0 ) {
    $connected_memberships_options .= "<optgroup label=\"$connected_url\">";
    for ($i=0; $i < count($connected_memberships); $i++) {
      $id = $connected_memberships[$i]->id;
      $title = $connected_memberships[$i]->title->rendered;
      $is_selected = $dropdown_value->ID == $id  ? 'selected' : '';
      $connected_memberships_options .= "<option value=\"{'domain': '$connected_url','ID': '$id'}\" $is_selected>$title</option>";
    }
    $connected_memberships_options .= "</optgroup>";
  }
  ?>
    <select name="connected_memberships" id="connected_memberships">
			<option value="">--- None ---</option>
      <?= $connected_memberships_options ?>
      <optgroup label="<?= get_site_url(); ?>">
  			<?php
  				$args = array(
  					'numberposts' => 999,
  					'post_type'   => 'llms_membership'
  				);
  				$memberships = get_posts( $args );
  				foreach ( $memberships as $post ) :
  					$is_selected = $dropdown_value->ID == $post->ID;
  					?><option value="{'domain': '<?= get_site_url() ?>', 'ID':<?= $post->ID ?>}" <?=$is_selected ? 'selected' : ''?>><?=$post->post_title?></option><?php
  		    endforeach;
  		    wp_reset_postdata();
  			?>
      </optgroup>
    </select>
  <?php
}

// dropdown saving
function connected_memberships_save( $post_id ) {

    // if doing autosave don't do nothing
  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
      return;

  // verify nonce
  if ( !wp_verify_nonce( $_POST['connected_memberships_nonce'], basename( __FILE__ ) ) )
      return;


  // Check permissions
  if ( 'page' == $_POST['post_type'] )
  {
    if ( !current_user_can( 'edit_page', $post_id ) )
        return;
  }
  else
  {
    if ( !current_user_can( 'edit_post', $post_id ) )
        return;
  }

  // save the new value of the dropdown
  $new_value = $_POST['connected_memberships'];
  update_post_meta( $post_id, 'connected_memberships', $new_value );
}
// action on saving post
add_action( 'save_post', 'connected_memberships_save' );

include("change-global-user-enrollment-date.php");
include("redirect-unlogged-in-users.php");
include("lesson-links-widget.php");
include("lesson-forum-metabox.php");
include("satellite-settings.php");
include("course-reordering.php");
include("shortcodes.php");

require 'plugin-update-checker-4.4/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://raw.githubusercontent.com/genoo-source/genoo-membership-plugin/master/details.json',
	__FILE__,
	'unique-plugin-or-theme-slug'
);

add_action( 'init', 'woocommerce_clear_cart_url' );
function woocommerce_clear_cart_url() {


  global $woocommerce;
  try {
  	$isWooFunnelsPage = strpos($_SERVER['REQUEST_URI'],'\/checkouts\/') != false;
    $doing_ajax = defined('DOING_AJAX') && DOING_AJAX;
    if ( !$doing_ajax && !$isWooFunnelsPage && is_admin() && $_GET['persistant-cart'] != "true" && isset($woocommerce->cart) ) {
      $woocommerce->cart->empty_cart();
    }
   } catch( \EXCEPTION $e ) {

   }
}

function change_template_if_membership( $template ){
  if (get_post_type() == "llms_membership" ) {
    $template = plugin_dir_path(__FILE__) . 'membership-page.php';
  }
  return $template;
}
add_filter('template_include', 'change_template_if_membership');

?>

<?php
/*
Plugin Name: Genoo WPMktgEngine eCommerce Tools
Description: Essential plugin for member websites to integrate nicely between LifterLMS, WooCommerce, One Page Checkout and WPMktgEngine plugins
Author: Genoo LLC
Version: 2.15
Author URI: http://www.genoo.com/
Text Domain: woocommerce-lifterlms-membership-extention
*/

add_shortcode('facebook_comments', function($atts){
    extract(shortcode_atts(
            array(
                'shareurl' => '',
            ), $atts)
    );
    $url = get_site_url(); // \WordPress\Request::getUrl();
    $r = '<div id="fb-root"></div>
    <script type="text/javascript">(function(d, s, id) {
      var js, fjs = d.getElementsByTagName(s)[0];
      if (d.getElementById(id)) return;
      js = d.createElement(s); js.id = id;
      js.src = "//connect.facebook.net/en_GB/sdk.js#xfbml=1&version=v2.5";
      fjs.parentNode.insertBefore(js, fjs);
        }(document, \'script\', \'facebook-jssdk\'));</script>';
    $r .= '<br />';
    $r .= '<div class="fb-like" data-href="'. $url .'" data-layout="standard" data-action="like" data-show-faces="false" data-share="false"></div>';
    $r .= '<div class="fb-share-button" data-href="'. $url .'" data-layout="button_count"></div>';
    $r .= '<div class="clear"></div>';
    $r .= '<br />';
    if(isset($shareurl) && !empty($shareurl)){
        $url = $shareurl;
    }
    $r .= '<div class="fb-comments" data-href="'. $url .'" data-numposts="5" data-order-by="reverse_time"></div>';
    return $r;
});

add_action( 'woocommerce_thankyou', 'wpme_llms_catch_checkout_to_add_memberships');
add_action( 'woocommerce_payment_complete', 'wpme_llms_catch_checkout_to_add_memberships');
function wpme_llms_catch_checkout_to_add_memberships( $order_id ){
  $order = new WC_Order( $order_id );
  $items = $order->get_items();
  $user_id = get_current_user_id();
  $student = new LLMS_Student( $user_id );

  foreach ( $items as $item ) {
		$id = $item->get_product_id();
		$memberships_bought = get_post_meta( $id, 'connected_memberships', true );
		if ( isset($memberships_bought) ) {

			$memberships_bought = explode(',', $memberships_bought);
			foreach ( $memberships_bought as $membership_id ) {
        llms_enroll_student( $student, $membership_id );
      }
		}
  }
}

add_shortcode( 'test-membership-shortcode', 'test_membership_shortcode' );
function test_membership_shortcode(){
  $user_id = get_current_user_id();
  $student = new LLMS_Student( $user_id );
  var_dump( $student );

  llms_enroll_student( $student, 1052 );

  return "Okay, check that user: $user_id is enrolled in membership: 1052";
}

function multi_membership_chooser() {
  // Get all of the memberships this person has (And the links to the membership pages)
  $args = array('post_type' => 'llms_membership', 'posts_per_page'   => -1);
  $query = new WP_Query( $args );
  $membership_ids = array();
  if ( $query->have_posts() ) {
  	while ( $query->have_posts() ) {
  		$query->the_post();
      $user_has_membership = llms_is_user_enrolled( get_current_user_id(), get_the_ID() );
      if ( $user_has_membership ) {
    		$membership_ids[] = get_the_ID();
      }
  	}
  	/* Restore original Post Data */
  	wp_reset_postdata();
  }

  // What happens if there is only one membership?
  if ( count($membership_ids) == 0) {
    return "<h2>You are not currently enrolled in any memberships</h2>";
  }

  // If this person only has one membership, redirect them to that membership page
  if ( count($membership_ids) == 1) {
    $membership_link = get_permalink( $membership_ids[0] );
    //header('Location: '. $membership_link);
    return "<script>window.location.replace(\"$membership_link\");</script>";
  } else {
    // Otherwise, if there are more than one memberships, display them with this shortcode.
    $out = "<div class=\"llms-loop\"><ul class=\"llms-loop-list llms-course-list cols-3\">";
    for ($i=0; $i < count($membership_ids); $i++) {
      $title = get_post($membership_ids[$i])->post_title;
      $permalink = get_permalink($membership_ids[$i]);

      // TODO: Get membership's featured image
      $featured_image = wp_get_attachment_url(get_post_thumbnail_id(get_post($membership_ids[$i])->ID));
      // Placeholder Image
      if ( $featured_image == "" ) {
        $featured_image = apply_filters( 'lifterlms_placeholder_img_src', LLMS()->plugin_url() . '/assets/images/placeholder.png' );
      }

      get_post($membership_ids[$i])->post_title;
      $out .= "
        <li class=\"llms-loop-item membership type-membership status-publish\">
          <div class=\"llms-loop-item-content\">
            <a class=\"llms-loop-link\" href=\"$permalink\">
              <img src=\"$featured_image\" alt=\"Featured Image for $title\" class=\"llms-placeholder llms-featured-image wp-post-image\">
              <h4 class=\"llms-loop-title\">$title</h4>
            </a><!-- .llms-loop-link -->
          </div><!-- .llms-loop-item-content -->
        </li><!-- .llms-loop-item -->
      ";
      // <h3><a href=\"$permalink\">$title</a></h3>
    }
    $out .= "</ul></div>";
  	return $out;
  }
}
add_shortcode( 'multi-membership-chooser', 'multi_membership_chooser' );

function llms_courses_in_membership( $atts ) {
  $membership_id = get_the_ID();
  $out = '';

  $args = array(
	  'post_type' => 'llms_access_plan',
	  'posts_per_page'   => -1,
	  'order' => 'ASC',
	  'order_by' => 'date'
  );
  $query = new WP_Query( $args );
  $course_ids = array();
  if ( $query->have_posts() ) {
  	while ( $query->have_posts() ) {
  	  $query->the_post();
      $access_plan = get_post_meta(get_the_ID());
      $connected_course = $access_plan["_llms_product_id"][0];

      if ( strpos(json_encode($access_plan["_llms_availability_restrictions"]), strval($membership_id)) !== false ) {
        $course_ids[] = $connected_course;
      }
  	}
  	/* Restore original Post Data */
  	wp_reset_postdata();
  }

  // What happens if there is only one course?
  if ( count($course_ids) == 0) {
    return "<h2>This membership does not have any courses</h2>";
  }

  if ( count($course_ids) == 1 ) {
    $course_link = get_permalink( $course_ids[0] );
    @header('Location: '. $course_link);
    return "<script>window.location.replace(\"$course_link\");</script>";
  } else {
    $out .= "<div class=\"llms-loop\"><ul class=\"llms-loop-list llms-course-list cols-3\">";
    for ($i=0; $i < count($course_ids); $i++) {
      $title = get_post($course_ids[$i])->post_title;
      $permalink = get_permalink($course_ids[$i]);

      // TODO: Get membership's featured image
      $featured_image = wp_get_attachment_url(get_post_thumbnail_id(get_post($course_ids[$i])->ID));
      // Placeholder Image
      if ( $featured_image == "" ) {
        $featured_image = apply_filters( 'lifterlms_placeholder_img_src', LLMS()->plugin_url() . '/assets/images/placeholder.png' );
      }

      // get_post($course_ids[$i])->post_title;
      if ($title!="") {
		  $out .= "
			<li class=\"llms-loop-item membership type-membership status-publish\">
			  <div class=\"llms-loop-item-content\">
				<a class=\"llms-loop-link\" href=\"$permalink\">
				  <img src=\"$featured_image\" alt=\"Featured Image for $title\" class=\"llms-placeholder llms-featured-image wp-post-image\">
				  <h4 class=\"llms-loop-title\">$title</h4>
				</a><!-- .llms-loop-link -->
			  </div><!-- .llms-loop-item-content -->
			</li><!-- .llms-loop-item -->
		  ";
	  }
    }
    $out .= "</ul></div>";
    return $out;
  }
}
add_shortcode( 'llms_courses_in_membership', 'llms_courses_in_membership' );

// action to add meta boxes
add_action( 'add_meta_boxes', 'connected_memberships_metabox' );
// action on saving post
add_action( 'save_post', 'connected_memberships_save' );



// function that creates the new metabox that will show on post
function connected_memberships_metabox() {
    add_meta_box(
        'connected_memberships',  // unique id
        'Membership to assign users to when they buy this product',  // metabox title
        'connected_memberships_display',  // callback to show the dropdown
        'product'   // post type
    );
}

// llms_woo dropdown display
function connected_memberships_display( $post ) {

  // Use nonce for verification
  wp_nonce_field( basename( __FILE__ ), 'connected_memberships_nonce' );

  // get current value
  $dropdown_value = get_post_meta( get_the_ID(), 'connected_memberships', true );
  ?>
    <select name="connected_memberships" id="connected_memberships">
			<option value="">--- None ---</option>
			<?php
				$args = array(
					'numberposts' => 999,
					'post_type'   => 'llms_membership'
				);
				$memberships = get_posts( $args );
				foreach ( $memberships as $post ) :
					$is_selected = $dropdown_value == $post->ID;
					?><option value="<?= $post->ID ?>" <?=$is_selected ? 'selected' : ''?>><?=$post->post_title?></option><?php
		    endforeach;
		    wp_reset_postdata();
			?>
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

include("one-page-checkout-integration.php");
include("change-global-user-enrollment-date.php");
include("redirect-unlogged-in-users.php");
include("lesson-links-widget.php");
include("lesson-forum-metabox.php");
include("course-reordering.php");

require 'plugin-update-checker-4.4/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://genoolabs.com/lms-updates/details.json',
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

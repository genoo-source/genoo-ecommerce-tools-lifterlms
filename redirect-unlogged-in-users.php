<?php

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

function redirect_non_logged_users_to_specific_page() {
  // Dont redirect if lifterLMS is not installed
  if ( !is_plugin_active('lifterlms/lifterlms.php') ) {
    return;
  }
  $isPageBuilderPage = get_post_type() == "wpme-landing-pages";
  $urlEncodedHomePage = urlencode(get_home_url());
  $loginUrl        = get_site_url(null, '/wp-login.php?redirect_to='.$urlEncodedHomePage.'&reauth=1');
  $isForgotPasswordPage = strpos( $_SERVER['REQUEST_URI'], "lost-password" );
  $isLoginUrl        = get_permalink() === $loginUrl;
  $onPublicPage      = (
    is_feed() ||
    $isPageBuilderPage ||
    $isLoginUrl ||
    $isForgotPasswordPage
  );

  if ( class_exists( 'WooCommerce' ) ) {
  	$onPublicPage = $onPublicPage || is_checkout();
  }
  if ( is_user_logged_in() || $onPublicPage ) return;

  wp_redirect( $loginUrl );
  exit;
}
add_action( 'template_redirect', 'redirect_non_logged_users_to_specific_page' );

function redirect_affiliates() {
	$user_id = get_current_user_id();
	$isaffiliate = isset(get_user_meta($user_id)["affwp_referral_notifications"]);
	$membership_information = get_user_meta($user_id)["_llms_restricted_levels"];
	$has_membership = isset($membership_information) && false === @strpos(json_encode($membership_information), "a:0");
	$on_affiliate_page   = is_page( 'affiliate-area' );
	$is_page = get_post_type() == "page";
	if ( $isaffiliate && !$has_membership && !$on_affiliate_page && $is_page) {
		$affiliate_page = get_site_url(null, '/affiliate-area/');
		wp_redirect( $affiliate_page );
		exit;
	}
}
add_action( 'template_redirect', 'redirect_affiliates' );

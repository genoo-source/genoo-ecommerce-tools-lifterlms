<?php

add_action( 'admin_menu', 'satellite_site_add_admin_menu' );
add_action( 'admin_init', 'satellite_site_settings_init' );

function satellite_site_add_admin_menu(  ) {
    add_options_page( 'Satellite Site', 'Satellite Site', 'manage_options', 'settings-api-page', 'satellite_site_options_page' );
}

function satellite_site_settings_init(  ) {
    register_setting( 'stpPlugin', 'satellite_site_settings' );
    add_settings_section(
        'satellite_site_stpPlugin_section',
        "",
        'satellite_site_settings_section_callback',
        'stpPlugin'
    );

    add_settings_field(
        'satellite_site_url',
        __( 'Website URL (include the http(s):// at the beginning)', 'wordpress' ),
        'satellite_site_url_render',
        'stpPlugin',
        'satellite_site_stpPlugin_section'
    );

    add_settings_field(
        'satellite_site_username',
        __( 'Admin Username', 'wordpress' ),
        'satellite_site_username_render',
        'stpPlugin',
        'satellite_site_stpPlugin_section'
    );

    add_settings_field(
        'satellite_site_password',
        __( 'Admin Password', 'wordpress' ),
        'satellite_site_password_render',
        'stpPlugin',
        'satellite_site_stpPlugin_section'
    );
}

function satellite_site_url_render() {
    $options = get_option( 'satellite_site_settings' );
    ?>
    <input type='text' name='satellite_site_settings[satellite_site_url]' value='<?php echo $options['satellite_site_url']; ?>'>
    <?php
}

function satellite_site_username_render(  ) {
    $options = get_option( 'satellite_site_settings' );
    ?>
    <input type='text' name='satellite_site_settings[satellite_site_username]' value='<?php echo $options['satellite_site_username']; ?>'>
    <?php
}

function satellite_site_password_render(  ) {
    $options = get_option( 'satellite_site_settings' );
    ?>
    <input type='password' name='satellite_site_settings[satellite_site_password]' value='<?php echo $options['satellite_site_password']; ?>'>
    <?php
}

function satellite_site_options_page(  ) {
    $notice = "";

    $options  = get_option( 'satellite_site_settings' );
    $username = $options['satellite_site_username'];
    $password = $options['satellite_site_password'];
    $token    = base64_encode("$username:$password");
    echo "--- $token ---";
    $url      = $options['satellite_site_url'];

    if ( isset($options['token']) ) {
      // Check if token exists
      $notice = "<p style=\"color: green;\">Your site is now connected!</p>";
    } else {
      // If it doesn't, check if we have username and password. Try to get a token.
      // Once we get the token, delete the username and password
      if ( isset($username) && isset($password) && isset($url) ) {

        if ( $token ) {
          update_option( 'satellite_site_settings["satellite_site_token"]', $token);
          $notice = "<p style=\"color: green;\">Your site is connected!</p>";
        } else {
          $notice = "<p style=\"color: red;\">There was an error when logging in</p>";
        }
      }

    }
    ?>
    <form action='options.php' method='post'>

        <h2>Satellite Site Settings</h2>
        <p>If you'd like to connect products on this site to memberships on another, add the information for that other site below:</p>

        <?= $notice; ?>

        <?php
        settings_fields( 'stpPlugin' );
        do_settings_sections( 'stpPlugin' );
        submit_button("Connect to Website");
        ?>

    </form>
    <?php
}


# Registering the API route
add_action('rest_api_init', 'register_satellite_routes');
function register_satellite_routes() {
    register_rest_route('wp/v2', '/satellite/new_user/', array(
        'methods' => 'POST',
        'callback' => 'create_new_user_from_api',
        'permission_callback' => function () {
          return current_user_can( 'edit_others_posts' );
        }
    ));
}

/**
 * This actually enrolls a user from the API request
 * to the membership. Fixed 2020-08-26 to create a user
 * only if needed.
 */
function create_new_user_from_api(WP_REST_Request $request) {
  
  $params      = $request->get_params();
  $username    = $params["username"];
  $password    = $params["password"];
  $email       = $params["email"];
  $website     = $params["website"];
  $memberships = $params["memberships"]; // comma separated

  // Try getting a user first
  $userByEmail = get_user_by('email', $email);
  if($userByEmail !== false){
    // User exists
    $user_id = $userByEmail->ID;
  } else {
    // Create the new user
    $user_id = wp_create_user($username, $password, $email);
  }

  // Enroll the new user into the membership
  $student = new LLMS_Student($user_id);
  $memberships_bought = explode(',', $memberships);
  foreach ( $memberships_bought as $membership_id ) {
    llms_enroll_student( $student, $membership_id, $website );
  }

  return "$user_id $memberships_bought Success";
}

/**
 * Plugin Name: JSON Basic Authentication
 * Description: Basic Authentication handler for the JSON API, used for development and debugging purposes
 * Author: WordPress API Team
 * Author URI: https://github.com/WP-API
 * Version: 0.1
 * Plugin URI: https://github.com/WP-API/Basic-Auth
 */
function json_basic_auth_handler_genoo_wpme_etools( $user ) {
	global $wp_json_basic_auth_error;
	$wp_json_basic_auth_error = null;
	// Don't authenticate twice
	if ( ! empty( $user ) ) {
		return $user;
	}
	// Check that we're trying to authenticate
	if ( !isset( $_SERVER['PHP_AUTH_USER'] ) ) {
		return $user;
	}
	$username = $_SERVER['PHP_AUTH_USER'];
	$password = $_SERVER['PHP_AUTH_PW'];
	/**
	 * In multi-site, wp_authenticate_spam_check filter is run on authentication. This filter calls
	 * get_currentuserinfo which in turn calls the determine_current_user filter. This leads to infinite
	 * recursion and a stack overflow unless the current function is removed from the determine_current_user
	 * filter during authentication.
	 */
	remove_filter( 'determine_current_user', 'json_basic_auth_handler_genoo_wpme_etools', 20 );
	$user = wp_authenticate( $username, $password );
	add_filter( 'determine_current_user', 'json_basic_auth_handler_genoo_wpme_etools', 20 );
	if ( is_wp_error( $user ) ) {
		$wp_json_basic_auth_error = $user;
		return null;
	}
	$wp_json_basic_auth_error = true;
	return $user->ID;
}
add_filter( 'determine_current_user', 'json_basic_auth_handler_genoo_wpme_etools', 20 );
function json_basic_auth_error_genoo_wpme_etools( $error ) {
	// Passthrough other errors
	if ( ! empty( $error ) ) {
		return $error;
	}
	global $wp_json_basic_auth_error;
	return $wp_json_basic_auth_error;
}
add_filter( 'rest_authentication_errors', 'json_basic_auth_error_genoo_wpme_etools' );

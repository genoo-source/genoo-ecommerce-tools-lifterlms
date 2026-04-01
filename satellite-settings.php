<?php

add_action( 'admin_menu', 'satellite_site_add_admin_menu' );
add_action( 'admin_init', 'satellite_site_settings_init' );

function satellite_site_add_admin_menu(  ) {
    add_options_page( 'Satellite Site', 'Satellite Site', 'manage_options', 'settings-api-page', 'satellite_site_options_page' );
}

function satellite_site_settings_init(  ) {
    register_setting('stpPlugin', 'satellite_site_settings' );
    add_settings_section(
        'satellite_site_stpPlugin_section',
        "stp_satellite_site_settings_section",
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
    $options = get_option( 'satellite_site_settings', array() );
    ?>
    <input type='text' name='satellite_site_settings[satellite_site_url]' value='<?php echo esc_url( $options['satellite_site_url'] ?? '' ); ?>'>
    <?php
}

function satellite_site_username_render() {
    $options = get_option( 'satellite_site_settings', array() );
    ?>
    <input type='text' name='satellite_site_settings[satellite_site_username]' value='<?php echo esc_attr( $options['satellite_site_username'] ?? '' ); ?>'>
    <?php
}

function satellite_site_password_render() {
    $options = get_option( 'satellite_site_settings', array() );
    ?>
    <input type='password' name='satellite_site_settings[satellite_site_password]' value='<?php echo esc_attr( $options['satellite_site_password'] ?? '' ); ?>'>
    <?php
}

function satellite_site_settings_section_callback()
{
    return true;
}

function satellite_site_options_page() {
    $notice = '';

    $options  = get_option( 'satellite_site_settings', array() );
    $username = $options['satellite_site_username'] ?? '';
    $password = $options['satellite_site_password'] ?? '';
    $url      = $options['satellite_site_url'] ?? '';

    // Token is stored inside the settings array, not as a separate option.
    $stored_token = $options['satellite_site_token'] ?? '';

    if ( $stored_token ) {
        $notice = '<p style="color: green;">Your site is now connected!</p>';
    } else {
        if ( $username && $password && $url ) {
            $token = base64_encode( $username . ':' . $password );
            if ( $token ) {
                // Store token inside the settings array (not as a malformed key).
                $options['satellite_site_token'] = $token;
                // Clear stored credentials now that we have the token.
                unset( $options['satellite_site_username'], $options['satellite_site_password'] );
                update_option( 'satellite_site_settings', $options );
                $notice = '<p style="color: green;">Your site is connected!</p>';
            } else {
                $notice = '<p style="color: red;">There was an error when logging in.</p>';
            }
        }
    }
    ?>
    <form action='options.php' method='post'>

        <h2>Satellite Site Settings</h2>
        <p>If you'd like to connect products on this site to memberships on another, add the information for that other site below:</p>

        <?php echo wp_kses_post( $notice ); ?>

        <?php
        settings_fields( 'stpPlugin' );
        do_settings_sections( 'stpPlugin' );
        submit_button( 'Connect to Website' );
        ?>

    </form>
    <?php
}


/**
 * REST API route: genoo/v1/satellite/new_user/
 *
 * Authentication uses WordPress Application Passwords (built-in since WP 5.6).
 * On the satellite site, create an Application Password for an admin user and
 * base64-encode "username:app_password" as the Authorization: Basic header value.
 * This replaces the legacy Basic Auth plugin (json_basic_auth_handler_genoo_wpme_etools).
 */
add_action( 'rest_api_init', 'register_satellite_routes' );
function register_satellite_routes(): void {
	register_rest_route(
		'genoo/v1',
		'/satellite/new_user/',
		array(
			'methods'             => 'POST',
			'callback'            => 'create_new_user_from_api',
			'permission_callback' => function () {
				return current_user_can( 'edit_others_posts' );
			},
		)
	);
}

/**
 * Create or retrieve a user on this site and enroll them in the specified memberships.
 * Called from the primary site via wp_remote_post().
 */
function create_new_user_from_api( WP_REST_Request $request ): string {

	$params      = $request->get_params();
	$username    = sanitize_user( $params['username'] ?? '' );
	$password    = $params['password'] ?? '';
	$email       = sanitize_email( $params['email'] ?? '' );
	$website     = esc_url_raw( $params['website'] ?? '' );
	$memberships = sanitize_text_field( $params['memberships'] ?? '' ); // comma-separated IDs

	if ( ! $email ) {
		return 'Error: missing email';
	}

	// Re-use existing user if possible.
	$existing = get_user_by( 'email', $email );
	if ( $existing ) {
		$user_id = $existing->ID;
	} else {
		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			return 'Error: ' . $user_id->get_error_message();
		}
	}

	$first_name = sanitize_text_field( $params['first_name'] ?? '' );
	$last_name  = sanitize_text_field( $params['last_name'] ?? '' );

	if ( $first_name ) {
		update_user_meta( $user_id, 'first_name', $first_name );
	}
	if ( $last_name ) {
		update_user_meta( $user_id, 'last_name', $last_name );
	}

	// Enroll the user — pass user ID (int), not LLMS_Student object.
	$membership_ids = array_filter( array_map( 'intval', explode( ',', $memberships ) ) );
	foreach ( $membership_ids as $membership_id ) {
		llms_enroll_student( (int) $user_id, $membership_id, $website );
	}

	return (string) $user_id . ' enrolled in ' . implode( ',', $membership_ids );
}

/**
 * Authentication for the satellite REST endpoint (genoo/v1/satellite/new_user/).
 *
 * WordPress Application Passwords (built-in since WP 5.6) handle REST
 * authentication automatically — no custom determine_current_user filter needed.
 *
 * To connect a new satellite site:
 *   1. On the satellite site, go to Users > Profile > Application Passwords.
 *   2. Create an application password for an administrator account.
 *   3. Base64-encode "admin_username:application_password_without_spaces".
 *   4. Enter the satellite site URL and those credentials in the Satellite Site
 *      Settings page on the primary site. The plugin will store the encoded token.
 *
 * The legacy WP-API/Basic-Auth plugin filter (determine_current_user) has been
 * removed. Application Passwords provide the same functionality securely and are
 * maintained by WordPress core.
 */

<?php
/*
Plugin Name: WooCommerce-LifterLMS-Additions
Description: Essential plugin for member websites to integrate LifterLMS, WooCommerce, One Page Checkout and WPMktgEngine plugins.
Author: Genoo LLC
Version: 3.0.0
Author URI: https://www.genoo.com/
Text Domain: woocommerce-lifterlms-membership-extention
Requires at least: 6.4
Requires PHP: 8.1
WC requires at least: 7.0
WC tested up to: 10.6
*/

// Declare compatibility with WooCommerce HPOS (High-Performance Order Storage).
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Retrieve the satellite site token from the settings array.
 */
function wpme_get_satellite_token(): string {
	$settings = get_option( 'satellite_site_settings', array() );
	return $settings['satellite_site_token'] ?? '';
}

/**
 * Retrieve the satellite site URL from the settings array.
 */
function wpme_get_satellite_url(): string {
	$settings = get_option( 'satellite_site_settings', array() );
	return $settings['satellite_site_url'] ?? '';
}

/**
 * Enroll a user on the remote (satellite) site via the REST API.
 * Uses wp_remote_post() — respects WordPress SSL, proxy, and timeout settings.
 */
function enroll_student_on_connected_site_wpme_genoo_etools( string $email, string $username, $membership_id, $userdata ): void {
	$url   = wpme_get_satellite_url();
	$token = wpme_get_satellite_token();

	if ( ! $url || ! $token ) {
		return;
	}

	// Generate a random password for the new user on the satellite site.
	$possible_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$password       = substr( str_shuffle( str_repeat( $possible_chars, (int) ceil( 14 / strlen( $possible_chars ) ) ) ), 0, 14 );

	$body = array(
		'username'    => $username,
		'password'    => $password,
		'email'       => $email,
		'website'     => get_site_url(),
		'first_name'  => $userdata->first_name ?? '',
		'last_name'   => $userdata->last_name ?? '',
		'memberships' => (string) $membership_id,
	);

	// Calls satellite-settings.php => create_new_user_from_api on the remote site.
	$response = wp_remote_post(
		esc_url_raw( trailingslashit( $url ) . 'wp-json/genoo/v1/satellite/new_user/' ),
		array(
			'headers' => array( 'Authorization' => 'Basic ' . $token ),
			'body'    => $body,
			'timeout' => 15,
		)
	);

	if ( is_wp_error( $response ) ) {
		return;
	}

	$result    = json_decode( wp_remote_retrieve_body( $response ) );
	$remote_id = (int) preg_replace( '/[^\d]/', '', $result );

	if ( $remote_id > 0 ) {
		$user_meta = get_user_meta( $remote_id, 'store_users', true );
		if ( ! $user_meta ) {
			require_once __DIR__ . '/includes/emailtemplate.php';
		}
	}
}

/**
 * On payment completion, enroll the purchasing customer in the connected LifterLMS membership.
 */
add_action( 'woocommerce_payment_complete', 'wpme_llms_catch_checkout_to_add_memberships' );
function wpme_llms_catch_checkout_to_add_memberships( int $order_id ): void {

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	// Use the order's customer ID — payment_complete can fire outside a browser session
	// (e.g. from a webhook or cron), so get_current_user_id() would return 0.
	$user_id = $order->get_customer_id();
	if ( ! $user_id ) {
		return;
	}

	foreach ( $order->get_items() as $item ) {
		$product_id         = $item->get_product_id();
		$raw_meta           = get_post_meta( $product_id, 'connected_memberships', true );
		$memberships_bought = json_decode( str_replace( "'", '"', $raw_meta ) );

		if ( ! is_object( $memberships_bought ) || ! isset( $memberships_bought->domain ) ) {
			continue;
		}

		if ( $memberships_bought->domain === get_site_url() ) {
			llms_enroll_student( $user_id, (int) $memberships_bought->ID, 'woocommerce_payment_complete' );
		} else {
			$userdata = get_userdata( $user_id );
			if ( $userdata ) {
				enroll_student_on_connected_site_wpme_genoo_etools(
					$userdata->user_email,
					$userdata->user_login,
					$memberships_bought->ID,
					$userdata
				);
			}
		}
	}
}

// Meta box: connect a LifterLMS membership to a WooCommerce product.
add_action( 'add_meta_boxes', 'connected_memberships_metabox' );
function connected_memberships_metabox(): void {
	add_meta_box(
		'connected_memberships',
		'Membership to assign users to when they buy this product',
		'connected_memberships_display',
		'product',
		'side',   // appears in the right-hand sidebar, visible without scrolling
		'default'
	);
}

/**
 * Fetch memberships from the remote (satellite) site via the LifterLMS REST API v1.
 * Uses wp_remote_get() with SSL verification enabled.
 */
function getConnectedSiteMemberships( string $url ): array {
	if ( ! $url ) {
		return array();
	}

	$token = wpme_get_satellite_token();

	$response = wp_remote_get(
		add_query_arg(
			array( 'per_page' => 100, 'order' => 'desc' ),
			esc_url_raw( trailingslashit( $url ) . 'wp-json/llms/v1/memberships' )
		),
		array(
			'headers' => array( 'Authorization' => 'Basic ' . $token ),
			'timeout' => 15,
		)
	);

	if ( is_wp_error( $response ) ) {
		return array();
	}

	return json_decode( wp_remote_retrieve_body( $response ) ) ?: array();
}

/**
 * Render the membership dropdown inside the product meta box.
 */
function connected_memberships_display( WP_Post $post ): void {
	$raw            = get_post_meta( $post->ID, 'connected_memberships', true );
	$dropdown_value = json_decode( str_replace( "'", '"', $raw ) );

	wp_nonce_field( basename( __FILE__ ), 'connected_memberships_nonce' );

	$connected_url         = wpme_get_satellite_url();
	$connected_memberships = getConnectedSiteMemberships( $connected_url );
	$remote_options        = '';

	if ( ! empty( $connected_memberships ) ) {
		$remote_options .= '<optgroup label="' . esc_attr( $connected_url ) . '">';
		foreach ( $connected_memberships as $membership ) {
			$id       = $membership->id ?? 0;
			$title    = $membership->title->rendered ?? '';
			$selected = ( is_object( $dropdown_value ) && (int) $dropdown_value->ID === (int) $id ) ? 'selected' : '';
			$value    = wp_json_encode( array( 'domain' => $connected_url, 'ID' => (string) $id ) );
			$remote_options .= '<option value="' . esc_attr( $value ) . '" ' . $selected . '>' . esc_html( $title ) . '</option>';
		}
		$remote_options .= '</optgroup>';
	}

	$local_memberships = get_posts( array(
		'posts_per_page' => -1,
		'post_type'      => 'llms_membership',
		'no_found_rows'  => true,
	) );

	$local_options = '';
	foreach ( $local_memberships as $m ) {
		$selected      = ( is_object( $dropdown_value ) && (int) $dropdown_value->ID === $m->ID ) ? 'selected' : '';
		$value         = wp_json_encode( array( 'domain' => get_site_url(), 'ID' => (string) $m->ID ) );
		$local_options .= '<option value="' . esc_attr( $value ) . '" ' . $selected . '>' . esc_html( $m->post_title ) . '</option>';
	}
	?>
	<select name="connected_memberships" id="connected_memberships">
		<option value="">--- None ---</option>
		<?php echo $remote_options; // Already escaped above. ?>
		<?php if ( $local_options ) : ?>
			<optgroup label="<?php echo esc_attr( get_site_url() ); ?>">
				<?php echo $local_options; // Already escaped above. ?>
			</optgroup>
		<?php endif; ?>
	</select>
	<?php
}

// Save the connected membership dropdown value.
add_action( 'save_post', 'connected_memberships_save' );
function connected_memberships_save( int $post_id ): void {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['connected_memberships_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['connected_memberships_nonce'] ) ), basename( __FILE__ ) ) ) {
		return;
	}

	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : '';

	if ( 'page' === $post_type ) {
		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}
	} else {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
	}

	if ( isset( $_POST['connected_memberships'] ) ) {
		update_post_meta( $post_id, 'connected_memberships', sanitize_text_field( wp_unslash( $_POST['connected_memberships'] ) ) );
	}
}

include __DIR__ . '/change-global-user-enrollment-date.php';
include __DIR__ . '/redirect-unlogged-in-users.php';
include __DIR__ . '/lesson-links-widget.php';
include __DIR__ . '/lesson-forum-metabox.php';
include __DIR__ . '/satellite-settings.php';
include __DIR__ . '/course-reordering.php';
include __DIR__ . '/shortcodes.php';
include __DIR__ . '/subscription-integration.php';

require __DIR__ . '/plugin-update-checker-5.5/plugin-update-checker.php';
$myUpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://raw.githubusercontent.com/genoo-source/genoo-membership-plugin/master/details.json',
	__FILE__,
	'genoo-woocommerce-lifterlms-additions'
);

// Clear the WooCommerce cart when visiting the admin outside a WooFunnels checkout context.
add_action( 'init', 'wpme_woocommerce_clear_cart_on_admin' );
function wpme_woocommerce_clear_cart_on_admin(): void {
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return;
	}
	if ( ! is_admin() ) {
		return;
	}

	$request_uri       = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$is_woo_funnels    = strpos( $request_uri, '/checkouts/' ) !== false;
	$persistent_cart   = isset( $_GET['persistant-cart'] ) && 'true' === $_GET['persistant-cart'];

	if ( ! $is_woo_funnels && ! $persistent_cart && function_exists( 'WC' ) && WC()->cart ) {
		WC()->cart->empty_cart();
	}
}

add_filter( 'template_include', 'change_template_if_membership' );
function change_template_if_membership( string $template ): string {
	if ( get_post_type() === 'llms_membership' ) {
		$template = plugin_dir_path( __FILE__ ) . 'membership-page.php';
	}
	return $template;
}

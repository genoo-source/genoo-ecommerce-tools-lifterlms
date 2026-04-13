<?php
/*
Plugin Name: Genoo WPME WooCommerce LifterLMS Additions
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

/**
 * Fetch published memberships from the remote (satellite) site via the LifterLMS REST API v1.
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
 * Render the membership selector inside the WooCommerce General product tab.
 *
 * Uses woocommerce_product_options_general_product_data so the field appears
 * inside the Product Data panel with a proper label, in the expected location.
 *
 * The HTML field uses the prefixed name/id "wpme_connected_membership" rather
 * than the generic "connected_memberships" to prevent LifterLMS admin JavaScript
 * from matching it with its own AJAX-powered select2 initialiser (which ignores
 * statically-rendered <option> elements and replaces the dropdown with an AJAX
 * search that returns "No results found").
 *
 * class="no-select2" additionally blocks WooCommerce's own select2 enhancement.
 *
 * The post meta key (connected_memberships) is unchanged for backward compatibility
 * with existing product data and the enrollment code below.
 */
add_action( 'woocommerce_product_options_general_product_data', 'wpme_render_connected_membership_field' );
function wpme_render_connected_membership_field(): void {
	global $post;

	$raw            = get_post_meta( $post->ID, 'connected_memberships', true );
	$dropdown_value = json_decode( str_replace( "'", '"', $raw ) );

	// Remote (satellite) site memberships.
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

	// Local published memberships only.
	$local_memberships = get_posts( array(
		'posts_per_page' => -1,
		'post_type'      => 'llms_membership',
		'post_status'    => 'publish',
		'no_found_rows'  => true,
	) );

	$local_options = '';
	foreach ( $local_memberships as $m ) {
		$selected      = ( is_object( $dropdown_value ) && (int) $dropdown_value->ID === $m->ID ) ? 'selected' : '';
		$value         = wp_json_encode( array( 'domain' => get_site_url(), 'ID' => (string) $m->ID ) );
		$local_options .= '<option value="' . esc_attr( $value ) . '" ' . $selected . '>' . esc_html( $m->post_title ) . '</option>';
	}

	wp_nonce_field( 'wpme_save_connected_membership', 'wpme_connected_membership_nonce' );
	?>
	<p class="form-field wpme_connected_membership_field">
		<label for="wpme_connected_membership">
			<?php esc_html_e( 'Membership to assign on purchase', 'woocommerce-lifterlms-membership-extention' ); ?>
		</label>
		<select name="wpme_connected_membership"
		        id="wpme_connected_membership"
		        class="no-select2"
		        style="width:100%;">
			<option value="">&#8212; None &#8212;</option>
			<?php echo $remote_options; // Already escaped above. ?>
			<?php if ( $local_options ) : ?>
				<optgroup label="<?php echo esc_attr( get_site_url() ); ?>">
					<?php echo $local_options; // Already escaped above. ?>
				</optgroup>
			<?php endif; ?>
		</select>
		<span class="description">
			<?php esc_html_e( 'Enroll the customer in this LifterLMS membership when they purchase this product.', 'woocommerce-lifterlms-membership-extention' ); ?>
		</span>
	</p>
	<?php
}

/**
 * Save the membership field when WooCommerce saves the product.
 * Stores to the same meta key (connected_memberships) used by the enrollment logic.
 */
add_action( 'woocommerce_process_product_meta', 'wpme_save_connected_membership_field' );
function wpme_save_connected_membership_field( int $post_id ): void {

	if ( ! isset( $_POST['wpme_connected_membership_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce(
		sanitize_text_field( wp_unslash( $_POST['wpme_connected_membership_nonce'] ) ),
		'wpme_save_connected_membership'
	) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( ! empty( $_POST['wpme_connected_membership'] ) ) {
		update_post_meta(
			$post_id,
			'connected_memberships',
			sanitize_text_field( wp_unslash( $_POST['wpme_connected_membership'] ) )
		);
	} else {
		delete_post_meta( $post_id, 'connected_memberships' );
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

	$request_uri     = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$is_woo_funnels  = strpos( $request_uri, '/checkouts/' ) !== false;
	$persistent_cart = isset( $_GET['persistant-cart'] ) && 'true' === $_GET['persistant-cart'];

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

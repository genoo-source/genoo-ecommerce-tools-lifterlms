<?php
/**
 * WooCommerce Subscriptions ↔ LifterLMS integration.
 *
 * Keeps LifterLMS membership enrollment in sync with the WooCommerce
 * Subscriptions lifecycle. All hooks are registered only when the
 * WooCommerce Subscriptions plugin is active.
 *
 * Enrollment trigger used in llms_enroll_student() / unenroll():
 *   'woocommerce-subscriptions' — visible in LifterLMS enrollment records.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'plugins_loaded', 'wpme_register_subscription_hooks' );

function wpme_register_subscription_hooks(): void {
	if ( ! class_exists( 'WC_Subscriptions' ) ) {
		return;
	}

	// Grant access when a subscription becomes active (initial or resumed).
	add_action( 'woocommerce_subscription_status_active', 'wpme_subscription_enroll_user' );

	// Suspend access on payment failure / manual hold.
	add_action( 'woocommerce_subscription_status_on-hold', 'wpme_subscription_unenroll_user' );

	// Revoke access on cancellation or expiration.
	add_action( 'woocommerce_subscription_status_cancelled', 'wpme_subscription_unenroll_user' );
	add_action( 'woocommerce_subscription_status_expired',   'wpme_subscription_unenroll_user' );

	// Prevent double-enrollment on the initial subscription payment — the
	// subscription_status_active hook above handles it.
	add_action( 'woocommerce_payment_complete', 'wpme_skip_initial_subscription_payment', 1 );
}

/**
 * Returns the LifterLMS membership ID linked to a WooCommerce product, or 0.
 */
function wpme_get_local_membership_id_for_product( int $product_id ): int {
	$raw   = get_post_meta( $product_id, 'connected_memberships', true );
	$value = json_decode( str_replace( "'", '"', $raw ) );

	if ( ! is_object( $value ) || ! isset( $value->domain, $value->ID ) ) {
		return 0;
	}

	// Only handle local memberships here; remote-site enrollments happen
	// via the REST call in enroll_student_on_connected_site_wpme_genoo_etools().
	if ( $value->domain !== get_site_url() ) {
		return 0;
	}

	return (int) $value->ID;
}

/**
 * Enroll the subscription owner in their connected LifterLMS membership.
 *
 * @param WC_Subscription $subscription
 */
function wpme_subscription_enroll_user( WC_Subscription $subscription ): void {
	$user_id = $subscription->get_customer_id();
	if ( ! $user_id ) {
		return;
	}

	foreach ( $subscription->get_items() as $item ) {
		$membership_id = wpme_get_local_membership_id_for_product( $item->get_product_id() );
		if ( $membership_id <= 0 ) {
			continue;
		}

		// llms_is_user_enrolled() returns true when status is 'enrolled'.
		// Re-enrolling an already-enrolled user is safe but let's skip it to keep clean records.
		if ( function_exists( 'llms_is_user_enrolled' ) && llms_is_user_enrolled( $user_id, $membership_id ) ) {
			continue;
		}

		llms_enroll_student( $user_id, $membership_id, 'woocommerce-subscriptions' );
	}
}

/**
 * Unenroll the subscription owner from their connected LifterLMS membership.
 *
 * @param WC_Subscription $subscription
 */
function wpme_subscription_unenroll_user( WC_Subscription $subscription ): void {
	$user_id = $subscription->get_customer_id();
	if ( ! $user_id ) {
		return;
	}

	foreach ( $subscription->get_items() as $item ) {
		$membership_id = wpme_get_local_membership_id_for_product( $item->get_product_id() );
		if ( $membership_id <= 0 ) {
			continue;
		}

		$student = new LLMS_Student( $user_id );
		if ( $student->is_enrolled( $membership_id ) ) {
			$student->unenroll( $membership_id, 'woocommerce-subscriptions' );
		}
	}
}

/**
 * If the order being paid is an initial subscription order, skip the
 * woocommerce_payment_complete enrollment handler — woocommerce_subscription_status_active
 * will fire immediately after and handle enrollment instead.
 *
 * Hooked at priority 1, before the main handler at the default priority.
 */
function wpme_skip_initial_subscription_payment( int $order_id ): void {
	if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	if ( wcs_order_contains_subscription( $order, 'parent' ) ) {
		remove_action( 'woocommerce_payment_complete', 'wpme_llms_catch_checkout_to_add_memberships' );
	}
}

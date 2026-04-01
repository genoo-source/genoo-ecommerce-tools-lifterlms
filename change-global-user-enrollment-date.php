<?php

add_action( 'show_user_profile', 'my_show_extra_profile_fields' );
add_action( 'edit_user_profile', 'my_show_extra_profile_fields' );

function my_show_extra_profile_fields( $user ) {
	$year_options = '';
	$current_year = (int) wp_date( 'Y' );
	for ( $i = 0; $i < 10; $i++ ) {
		$opt = $current_year - $i;
		$year_options .= '<option value="' . esc_attr( $opt ) . '">' . esc_html( $opt ) . '</option>';
	}

	$month_options = '';
	$month_names   = array( 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' );
	for ( $m = 0; $m < 12; $m++ ) {
		$month_num   = $m + 1;
		$month_title = $month_names[ $m ];
		$month_options .= '<option value="' . esc_attr( $month_num ) . '">' . esc_html( $month_title ) . '</option>';
	}

	$day_options = '';
	for ( $d = 1; $d <= 31; $d++ ) {
		$day_options .= '<option value="' . esc_attr( $d ) . '">' . esc_html( $d ) . '</option>';
	}

	$all_memberships   = get_posts( array( 'posts_per_page' => -1, 'post_type' => 'llms_membership', 'no_found_rows' => true ) );
	$membership_options = '';
	foreach ( $all_memberships as $membership ) {
		$membership_options .= '<option value="' . esc_attr( $membership->ID ) . '">' . esc_html( $membership->post_title ) . '</option>';
	}

	$all_courses   = get_posts( array( 'posts_per_page' => -1, 'post_type' => 'course', 'no_found_rows' => true ) );
	$course_options = '';
	foreach ( $all_courses as $course ) {
		$course_options .= '<option value="' . esc_attr( $course->ID ) . '">' . esc_html( $course->post_title ) . '</option>';
	}
	?>
	<table class='form-table'>
		<tr>
			<th><label for='enrollment-date-change'>Change Enrollment Date To:</label></th>
			<td>
				<style scoped>
					.negate-padding td {
						padding: 0;
					}
				</style>
				<table class="negate-padding">
					<tr>
						<td>Membership to change:</td>
						<td style="padding: 0 12px;"> - or - </td>
						<td>Course to change:</td>
					</tr>
					<tr>
						<td>
							<select name="enrollment-date-membership-id">
								<option selected value="">Select a membership</option>
								<?= $membership_options ?>
							</select>
						</td>
						<td>&nbsp;</td>
						<td>
							<select name="enrollment-date-course-id">
								<option selected value="">Select a course</option>
								<?= $course_options ?>
							</select>
						</td>
					</tr>
				</table>

				<label>Date to change:</label><br />
				<select name="enrollment-date-change-year"><option selected disabled>YEAR</option><?= $year_options ?></select>
				<select name="enrollment-date-change-month"><option selected disabled>MONTH</option><?= $month_options ?></select>
				<select name="enrollment-date-change-day"><option selected disabled>DAY</option><?= $day_options ?></select>
				<br />
				<span class="description">
					Useful if trying to edit how courses drip for users.
				</span>
			</td>
		</tr>
	</table>
	<?php
}

add_action( 'personal_options_update', 'my_save_extra_profile_fields' );
add_action( 'edit_user_profile_update', 'my_save_extra_profile_fields' );

function my_save_extra_profile_fields( $user_id ) {

	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return false;
	}

	// Validate and sanitize each date component from $_POST.
	$raw_year  = isset( $_POST['enrollment-date-change-year'] )  ? (int) $_POST['enrollment-date-change-year']  : 0;
	$raw_month = isset( $_POST['enrollment-date-change-month'] ) ? (int) $_POST['enrollment-date-change-month'] : 0;
	$raw_day   = isset( $_POST['enrollment-date-change-day'] )   ? (int) $_POST['enrollment-date-change-day']   : 0;

	// Require a plausible date — bail silently if the user left the dropdowns unset.
	if ( $raw_year < 2000 || $raw_month < 1 || $raw_month > 12 || $raw_day < 1 || $raw_day > 31 ) {
		return;
	}

	// Format: 2017-01-23 00:00:00
	$updated_date = sprintf( '%04d-%02d-%02d 00:00:00', $raw_year, $raw_month, $raw_day );

	$course_id     = isset( $_POST['enrollment-date-course-id'] )     ? (int) $_POST['enrollment-date-course-id']     : 0;
	$membership_id = isset( $_POST['enrollment-date-membership-id'] ) ? (int) $_POST['enrollment-date-membership-id'] : 0;
	$post_id       = $course_id > 0 ? $course_id : $membership_id;

	if ( $post_id <= 0 ) {
		return;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'lifterlms_user_postmeta';

	$wpdb->query(
		$wpdb->prepare(
			"UPDATE `{$table_name}` SET `updated_date` = %s WHERE `user_id` = %d AND `post_id` = %d",
			$updated_date,
			(int) $user_id,
			$post_id
		)
	);
}

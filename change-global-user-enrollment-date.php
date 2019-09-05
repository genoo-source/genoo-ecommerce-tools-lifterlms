<?php

add_action( 'show_user_profile', 'my_show_extra_profile_fields' );
add_action( 'edit_user_profile', 'my_show_extra_profile_fields' );

function my_show_extra_profile_fields( $user ) {
	$year_options = "";
	$current_date = date('Y');
	for ($i=0; $i < 10; $i++) {
		$new_option_date = $current_date-$i;
		$year_options .= "<option>$new_option_date</option>";
	}

	$month_options = "";
	$monthNames = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
	for ($m=0; $m < 12; $m++) {
		$month_num = $m+1;
		$month_title = $monthNames[$m];
		$month_options .= "<option value='$month_num'>$month_title</option>";
	}

	$day_options = "";
	for ($d=1; $d <= 31; $d++) {
		$day_options .= "<option>$d</option>";
	}

	$all_memberships = get_posts(array( 'numberposts' => 999, 'post_type'   => 'llms_membership' ));
	$membership_options = "";
	foreach ($all_memberships as $membership) {
		$membership_options .= "<option value=\"$membership->ID\">$membership->post_title</option>";
	}

	$all_courses= get_posts(array( 'numberposts' => 999, 'post_type'   => 'course' ));
	$course_options = "";
	foreach ($all_courses as $course) {
		$course_options .= "<option value=\"$course->ID\">$course->post_title</option>";
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

	if ( !current_user_can( 'edit_user', $user_id ) )
		return false;

  // Format: 2017-01-23 10:08:41
  $updated_date = $_POST['enrollment-date-change-year']
		      . '-' . $_POST['enrollment-date-change-month']
		      . '-' . $_POST['enrollment-date-change-day'] . ' 00:00:00';
	global $wpdb;
	$table_name = $wpdb->prefix . 'lifterlms_user_postmeta';

	if ( $_POST['enrollment-date-course-id'] != '' ) {
		$post_id = $_POST['enrollment-date-course-id'];
	} else {
		$post_id = $_POST['enrollment-date-membership-id'];
	}

	if ( $post_id == "" ) { return; }

	//UPDATE `wp_lifterlms_user_postmeta` SET `updated_date`="2017-01-23 10:08:41" WHERE `user_id`=1 AND `post_id`=1054
	$wpdb->query(
		"UPDATE `$table_name` SET `updated_date`=\"$updated_date\" WHERE `user_id`=$user_id AND `post_id`=$post_id"
	);
}

<?php

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

function facebook_comments($atts){
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
}
add_shortcode('facebook_comments', 'facebook_comments');

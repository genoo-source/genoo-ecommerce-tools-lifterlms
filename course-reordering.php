<?php

function course_reorder_meta_box_markup($object) {
  wp_nonce_field(basename(__FILE__), "meta-box-nonce");

  $course_order = explode( ",", get_post_meta($object->ID, "courses-in-order", true) );
  $day = 60 * 60 * 24;
  for ($i=0; $i < count( $course_order ); $i++) {
    if ( $course_order[$i] !== "" ) {
      $course_access_update = array();
      $course_access_update['ID'] = $course_order[$i];
      $course_access_update['post_date'] = date( 'Y-m-d H:i:s', time()-($day*(365-$i)) );
    }

    wp_update_post( $course_access_update );
  }

  add_action ('admin_enqueue_scripts', function() {
    if(is_admin())
    wp_enqueue_media();
  });

  $membership_id = $object->ID;

  $args = array(
	  'post_type' => 'llms_access_plan',
	  'posts_per_page'   => -1,
	  'order' => 'ASC',
	  'order_by' => 'date'
  );
  $course_ids = array();
  $access_plan_ids = array();
  $access_plan_posts = get_posts( $args );
  for ($i=0; $i < count($access_plan_posts); $i++) {
    $access_plan = get_post_meta( $access_plan_posts[$i]->ID );
    $connected_course = $access_plan["_llms_product_id"][0];
    if ( strpos(json_encode($access_plan["_llms_availability_restrictions"]), strval($membership_id)) !== false && get_post($connected_course) !== null) {
      $course_ids[] = $connected_course;
      $access_plan_ids[] = $access_plan_posts[$i]->ID;//get_the_ID();
    }
  }
  ?>
      <div>
          <input type="hidden" disabled="true" name="courses-in-order" value="<?php echo htmlspecialchars(get_post_meta($object->ID, "courses-in-order", true)); ?>">
          <ul id="courses-in-order"><?php for ($i=0; $i < count($course_ids); $i++) {
            ?><li data-id="<?= $access_plan_ids[$i] ?>">
              <span class="handle dashicons-before dashicons-menu" style="font-size: 0.8em; cursor: row-resize;"></span>
              <?= get_post($course_ids[$i])->post_title ?>
            </li><?php
          }
          ?></ul>

          <script>
          jQuery(document).ready(function() {
              var $ = jQuery;

              function updateCourseOrder(){
                const input = document.querySelector("[name=\"courses-in-order\"]");
                input.value = [...document.getElementById("courses-in-order").children].map(
                  listItem => listItem.getAttribute( "data-id" )
                );
                // Make it not disabled so that it only submits new data when there is new data to submit
                input.removeAttribute("disabled");
              }

              $("#courses-in-order").sortable({
                stop: updateCourseOrder,
                axis: "y"
              });
          });
          </script>
      </div>
  <?php
}

function save_course_order_meta_box($post_id, $post, $update) {
  if(isset($_POST["courses-in-order"])){
    update_post_meta($post_id, "courses-in-order", $_POST["courses-in-order"]);
  }
}
add_action("save_post", "save_course_order_meta_box", 10, 3);

function add_course_reorder_meta_box() {
  add_meta_box("course-reorder-meta-box", "Order your courses", "course_reorder_meta_box_markup", "llms_membership", "side", "high", null);
}
add_action("add_meta_boxes", "add_course_reorder_meta_box");

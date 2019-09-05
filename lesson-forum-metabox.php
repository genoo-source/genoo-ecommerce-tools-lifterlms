<?php

function lesson_forum_meta_box_markup($object)
{
    wp_nonce_field(basename(__FILE__), "forum-meta-box-nonce");

    echo "<span><b>Select the forum topic (if any) you would like to add to this lesson</b></span>";

    // List the memberships
    $args = array(
      'numberposts' => -1,
      'post_type'   => 'llms_membership'
    );
    $memberships = get_posts( $args );

    $args = array(
      'numberposts' => -1,
      'post_type'   => 'topic'
    );
    $topics = get_posts( $args );

    $forums = array();
    for ($i=0; $i < count($topics); $i++) {
      $topic = $topics[$i];
      $topic_forum_id = $topic->post_parent;
      if ( !isset($forums[$topic_forum_id]) ) {
        $forums[$topic_forum_id] = array();
      }
      $forums[$topic_forum_id][] = $topic;
    }

    // Needs to be wrapped in a select tag
    $topic_options = "<option value=\"\">-- None --</option>";
    foreach ($forums as $forum_id => $topics) {
      if ( $forum_id != 0 ) {
        $forum_title = get_the_title($forum_id);
      } else { // topics outside of a forum
        $forum_title = "No Forum Parent";
      }
      $topic_options .= "<optgroup label=\"$forum_title\">";
      for ($i=0; $i < count($topics); $i++) {
        $topic = $topics[$i];
        $topic_options .= "<option value=\"$topic->ID\">$topic->post_title</option>";
      }
      $topic_options .= "</optgroup>";
    }

    $current_value = get_post_meta(get_the_ID(), "forum-for-comments", true);
    echo "<input name=\"forum-for-comments\" style=\"display: none;\" value=\"".htmlspecialchars($current_value)."\" />";

    for ($i=0; $i < count($memberships); $i++) {
      echo "<br /><br /><label><b>".$memberships[$i]->post_title . "</b></label>";

      echo "<select style=\"width: 100%;\" data-forum-membership-id=\"". $memberships[$i]->ID ."\">$topic_options</select>";
    }
    echo '
    <script type="text/javascript">
      var forumForComments = [];
      try {
        forumForComments = JSON.parse(document.querySelector("[name=\"forum-for-comments\"]").value);
      } catch (e) {
        console.error("Invalid forum JSON");
      }

      jQuery("[data-forum-membership-id]").ready(function() {
        for (var i = 0; i < forumForComments.length; i++) {
          $(`[data-forum-membership-id="${forumForComments[i].id}"]`).val(
            forumForComments[i].forum
          );
        }
      });

      $("[data-forum-membership-id]").on("change", function(){
        forumForComments = [...document.querySelectorAll(\'[data-forum-membership-id]\')].map(
          that => ({
            id: that.getAttribute("data-forum-membership-id"),
            forum: that.value
          })
        );
        $("[name=\'forum-for-comments\']").val( JSON.stringify(forumForComments) );
      });
    </script>
    ';
}

function save_lesson_forum_meta_box($post_id, $post, $update) {
    if(isset($_POST["forum-for-comments"])){
        $forums_in_use = $_POST["forum-for-comments"];
		update_post_meta($post_id, "forum-for-comments", $forums_in_use);
    }
}
add_action("save_post", "save_lesson_forum_meta_box", 10, 3);

function add_lesson_forum_meta_box() {
    add_meta_box("lesson-forum-meta-box", "Custom Lesson Forums", "lesson_forum_meta_box_markup", "lesson", "side", "high", null);
}

add_action("add_meta_boxes", "add_lesson_forum_meta_box");

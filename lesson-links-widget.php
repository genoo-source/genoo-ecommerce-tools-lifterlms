<?php

function lesson_link_meta_box_markup($object)
{
    wp_nonce_field(basename(__FILE__), "meta-box-nonce");

    add_action ('admin_enqueue_scripts', function() {
      if(is_admin())
      wp_enqueue_media();
    });
    ?>
        <div>

            <span><i>Use the Lesson Links widget for the lesson sidebar to show these. You can also use the [show-lesson-links] shortcode to show inside the content.</i></span>
            <input type="hidden" name="links-in-use" value="<?php echo htmlspecialchars(get_post_meta($object->ID, "links-in-use", true)); ?>">
            <ul id="links-in-use"><li>Loading....</li></ul>
            <button class="add-link-button  button">
              <span class="handle dashicons-before dashicons-plus" style="position: relative; top: 6px;"></span>
              Add Link
            </button>

            <script>
            $ = jQuery;
            function updateLinksInUse() {
              const linksInUse = [...$("#links-in-use a")].map(
                linkEl => {return {url: $(linkEl).attr("href"), filename: $(linkEl).attr("data-filename")}}
              );
              $("[name=\"links-in-use\"]").val( JSON.stringify(linksInUse) );
            }
            function editFileName( listNode ) {
              updateLinksInUse();

              const $linkNode = $(listNode).find('[data-filename]');
              const filename = $linkNode.attr("data-filename");

              console.log( $linkNode );

              $linkNode.hide();
              $linkNode.after("<input type=\"text\" style=\"width: 180px\" />");
              const $inputElem = $linkNode.next();
              console.log( $inputElem );

              $inputElem.val(filename);

              $inputElem.on("keyup", function( e ){
                $linkNode.html( this.value );
                $linkNode.attr( "data-filename", this.value );
                updateLinksInUse();
                if ( e.keyCode === 13 ) {
                  $inputElem.remove();
                  $linkNode.show();
                  e.preventDefault();
                  e.stopPropagation();
                }
              });
            }
            function addLink({url, filename}){
              const raw_filename = url.match(/\/[^\/]*$/);
              $("#links-in-use").append(
                `<li>
                  <span class="handle dashicons-before dashicons-menu" style="font-size: 0.8em; cursor: row-resize;"></span>
                  <a href="${url}" data-filename="${filename}" target="_blank">${filename} </a>
                  <span class="dashicons-before dashicons-trash" onclick="$(this).parent().remove();updateLinksInUse();" style="font-size: 0.8em; color: red; cursor: pointer;" title="delete link"></span>
                  <span class="dashicons-before dashicons-edit" onclick="editFileName(this.parentNode)" style="font-size: 0.8em; cursor: pointer;" title="edit link"></span>
                  <span style="display: block;width: 100%; font-size: 10px; font-family: monospace; padding-left: 20px; box-sizing:border-box">${raw_filename}</span>
                </li>`
              );
              updateLinksInUse();
            }

            jQuery(document).ready(function() {
                var $ = jQuery;

                $("#links-in-use").html("");
                try {
                    JSON.parse($("[name=\"links-in-use\"]").val()).forEach(addLink);
                } catch (e) {
                  console.error(e)
                }

                $("#links-in-use").sortable({
                  stop: updateLinksInUse,
                  axis: "y"
                });

                // TODO: Load data here
                // addLink({url: "test", filename: "Load data here.pdf"})

                if ($('.add-link-button').length > 0) {
                    if ( typeof wp !== 'undefined' && wp.media && wp.media.editor) {
                        $(document).on('click', '.add-link-button ', function(e) {
                            e.preventDefault();
                            var button = $(this);
                            wp.media.editor.send.attachment = function(props, attachment) {
                                addLink( attachment );
                            };
                            wp.media.editor.open(button);
                            return false;
                        });
                    }
                }
            });
            </script>
        </div>
    <?php
}

function save_lesson_link_meta_box($post_id, $post, $update) {
    if(isset($_POST["links-in-use"])){
        $links_in_use = $_POST["links-in-use"];
		update_post_meta($post_id, "links-in-use", $links_in_use);
    }
}
add_action("save_post", "save_lesson_link_meta_box", 10, 3);

function add_lesson_link_meta_box() {
    add_meta_box("lesson-link-meta-box", "Custom Lesson Links", "lesson_link_meta_box_markup", "lesson", "side", "high", null);
}

add_action("add_meta_boxes", "add_lesson_link_meta_box");


//
//
//

// Register and load the widget
function wpb_load_widget() {
    register_widget( 'lesson_link_widget' );
}
add_action( 'widgets_init', 'wpb_load_widget' );

// lesson Links Shortcode
add_shortcode( 'show-lesson-links', 'lessonLinksWidget' );
function lessonLinksWidget( $atts ) {
  // TODO: If masonry is an attribute then show links as shorcodes.
  $a = shortcode_atts( array(
		'style' => 'list',
	), $atts );
  $data = json_decode(get_post_meta(get_the_ID(), "links-in-use", true));
  if ( $a["style"] == "list" ) {
    $output = "<div class=\"widget_lesson_link_widget\"><ul>";
  } else {
    $output = "<div>";
  }
  for ($i = 0; $i < @count($data); $i++) {
    if ( $a["style"] == "list" ) {
      $icon_url = "";
      if ( substr($data[$i]->url, -3) == "pdf" ) {
        $icon_url = "https://kajabi-storefronts-production.global.ssl.fastly.net/kajabi-storefronts-production/themes/330055/assets/acrobat.png?15168264784571571";
      }
      if ( substr($data[$i]->url, -3) == "mp3" ) {
        $icon_url = "https://kajabi-storefronts-production.global.ssl.fastly.net/kajabi-storefronts-production/themes/330055/assets/audio.png?15168264784571571";
      }
      if ( substr($data[$i]->url, -3) == "jpg" || substr($data[$i]->filename, -3) == "png" ) {
        $icon_url = "https://kajabi-storefronts-production.global.ssl.fastly.net/kajabi-storefronts-production/themes/330055/assets/image.png?15168264784571571";
      }
      if ( substr($data[$i]->url, -4) == "docx" || substr($data[$i]->filename, -3) == "doc" ) {
        $icon_url = "https://kajabi-storefronts-production.global.ssl.fastly.net/kajabi-storefronts-production/themes/330055/assets/word.png?15168264784571571";
      }
      $output .= "<li><img src=\"$icon_url\" /><a href='".$data[$i]->url."' target='_blank'>" .$data[$i]->filename."</a></li>";
    } else {
      $output .= do_shortcode("[course-material-download src=\"". $data[$i]->url ."\"]"
                  . $data[$i]->filename .
                  "[/course-material-download]");
    }
  }
  $break = "<div style=\"clear: both;\"></div>";
  if ( $a["style"] == "list" ) {
    $output .= "</ul>$break</div>";
  } else {
    $output .= "$break</div>";
  }
  return $output;
}

// Creating the widget
class lesson_link_widget extends WP_Widget {

  function __construct() {
    parent::__construct(
      'lesson_link_widget',
      'Lesson Downloads',
      array( 'description' => 'Show a list of downloads in your lesson sidebar')
    );
  }

  // Creating widget front-end

  public function widget( $args, $instance ) {
    $title = apply_filters( 'widget_title', $instance['title'] );

    echo $args['before_widget'];
    if ( ! empty( $title ) )
    echo $args['before_title'] . $title . $args['after_title'];

    $data = json_decode(get_post_meta(get_the_ID(), "links-in-use", true));
    echo "<ul>";
    for ($i = 0; $i < @count($data); $i++) {
  	  $icon_url = "";
  	  if ( substr($data[$i]->url, -3) == "pdf" ) {
  		  $icon_url = "https://kajabi-storefronts-production.global.ssl.fastly.net/kajabi-storefronts-production/themes/330055/assets/acrobat.png?15168264784571571";
  	  }
  	  if ( substr($data[$i]->url, -3) == "mp3" ) {
  		  $icon_url = "https://kajabi-storefronts-production.global.ssl.fastly.net/kajabi-storefronts-production/themes/330055/assets/audio.png?15168264784571571";
  	  }
  	  if ( substr($data[$i]->url, -3) == "jpg" || substr($data[$i]->filename, -3) == "png"  ) {
  		  $icon_url = "https://kajabi-storefronts-production.global.ssl.fastly.net/kajabi-storefronts-production/themes/330055/assets/image.png?15168264784571571";
  	  }
  	  if ( substr($data[$i]->url, -4) == "docx" || substr($data[$i]->filename, -3) == "doc" ) {
  		  $icon_url = "https://kajabi-storefronts-production.global.ssl.fastly.net/kajabi-storefronts-production/themes/330055/assets/word.png?15168264784571571";
  	  }
      echo "<li><img src=\"$icon_url\" /><a href='".$data[$i]->url."' target='_blank'>" .$data[$i]->filename."</a></li>";
    }
    echo "</ul>";
    echo $args['after_widget'];
  }

  // Widget Backend
  public function form( $instance ) {
    if ( isset( $instance[ 'title' ] ) ) {
      $title = $instance[ 'title' ];
    }
    else {
      $title = 'Downloads';
    }
    // Widget admin form
    ?>
    <p>
      <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
      <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
    </p>
    <?php
  }

  // Updating widget replacing old instances with new
  public function update( $new_instance, $old_instance ) {
    $instance = array();
    $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
    return $instance;
  }
} // Class lesson_link_widget ends here

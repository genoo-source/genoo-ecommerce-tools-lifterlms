<?php

// Enqueue wp_enqueue_media() at the correct time, scoped to the lesson post type.
add_action( 'admin_enqueue_scripts', 'wpme_lesson_links_enqueue_media' );
function wpme_lesson_links_enqueue_media(): void {
	$screen = get_current_screen();
	if ( $screen && 'lesson' === $screen->post_type ) {
		wp_enqueue_media();
	}
}

function lesson_link_meta_box_markup( WP_Post $object ): void {
	wp_nonce_field( basename( __FILE__ ), 'meta-box-nonce' );
	?>
	<div>
		<span><i>Use the Lesson Links widget for the lesson sidebar to show these. You can also use the [show-lesson-links] shortcode to show inside the content.</i></span>
		<input type="hidden" name="links-in-use" value="<?php echo esc_attr( get_post_meta( $object->ID, 'links-in-use', true ) ); ?>">
		<ul id="links-in-use"><li>Loading...</li></ul>
		<button class="add-link-button button">
			<span class="handle dashicons-before dashicons-plus" style="position: relative; top: 6px;"></span>
			Add Link
		</button>
		<script>
		(function ($) {
			function updateLinksInUse() {
				var linksInUse = [...$('#links-in-use a')].map(function (linkEl) {
					return { url: $(linkEl).attr('href'), filename: $(linkEl).attr('data-filename') };
				});
				$('[name="links-in-use"]').val(JSON.stringify(linksInUse));
			}

			function editFileName(listNode) {
				updateLinksInUse();
				var $linkNode = $(listNode).find('[data-filename]');
				var filename  = $linkNode.attr('data-filename');

				$linkNode.hide();
				$linkNode.after('<input type="text" style="width: 180px" />');
				var $inputElem = $linkNode.next();
				$inputElem.val(filename);

				$inputElem.on('keyup', function (e) {
					$linkNode.html(this.value);
					$linkNode.attr('data-filename', this.value);
					updateLinksInUse();
					if (e.key === 'Enter') {
						$inputElem.remove();
						$linkNode.show();
						e.preventDefault();
						e.stopPropagation();
					}
				});
			}

			function addLink(attachment) {
				var url      = attachment.url      || '';
				var filename = attachment.filename || url.match(/\/([^/]*)$/)[1] || url;
				var rawPath  = url.match(/\/([^/]*)$/);
				$('#links-in-use').append(
					'<li>' +
					'<span class="handle dashicons-before dashicons-menu" style="font-size:0.8em;cursor:row-resize;"></span>' +
					'<a href="' + url + '" data-filename="' + filename + '" target="_blank">' + filename + ' </a>' +
					'<span class="dashicons-before dashicons-trash wpme-delete-link" style="font-size:0.8em;color:red;cursor:pointer;" title="Delete link"></span>' +
					'<span class="dashicons-before dashicons-edit wpme-edit-link" style="font-size:0.8em;cursor:pointer;" title="Edit link name"></span>' +
					'<span style="display:block;width:100%;font-size:10px;font-family:monospace;padding-left:20px;box-sizing:border-box">' + (rawPath ? rawPath[0] : '') + '</span>' +
					'</li>'
				);
				updateLinksInUse();
			}

			$(document).ready(function () {
				$('#links-in-use').html('');
				try {
					JSON.parse($('[name="links-in-use"]').val()).forEach(addLink);
				} catch (e) {
					console.error(e);
				}

				$('#links-in-use').sortable({ stop: updateLinksInUse, axis: 'y' });

				$(document).on('click', '.wpme-delete-link', function () {
					$(this).parent().remove();
					updateLinksInUse();
				});

				$(document).on('click', '.wpme-edit-link', function () {
					editFileName(this.parentNode);
				});

				if ($('.add-link-button').length > 0 && typeof wp !== 'undefined' && wp.media && wp.media.editor) {
					$(document).on('click', '.add-link-button', function (e) {
						e.preventDefault();
						wp.media.editor.send.attachment = function (props, attachment) {
							addLink(attachment);
						};
						wp.media.editor.open($(this));
						return false;
					});
				}
			});
		}(jQuery));
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

/**
 * Returns a dashicon span for a given file URL, replacing the old Kajabi CDN images.
 */
function wpme_lesson_link_icon( string $url, string $filename ): string {
	$ext = strtolower( pathinfo( $url, PATHINFO_EXTENSION ) );
	$dashicon = 'dashicons-media-default';

	if ( $ext === 'pdf' ) {
		$dashicon = 'dashicons-media-document';
	} elseif ( $ext === 'mp3' || $ext === 'wav' || $ext === 'ogg' ) {
		$dashicon = 'dashicons-media-audio';
	} elseif ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
		$dashicon = 'dashicons-media-img';
	} elseif ( in_array( $ext, array( 'doc', 'docx' ), true ) ) {
		$dashicon = 'dashicons-media-text';
	} elseif ( in_array( $ext, array( 'xls', 'xlsx' ), true ) ) {
		$dashicon = 'dashicons-media-spreadsheet';
	} elseif ( in_array( $ext, array( 'zip', 'tar', 'gz' ), true ) ) {
		$dashicon = 'dashicons-media-archive';
	} elseif ( in_array( $ext, array( 'mp4', 'mov', 'avi', 'webm' ), true ) ) {
		$dashicon = 'dashicons-media-video';
	}

	return '<span class="dashicons ' . esc_attr( $dashicon ) . '" aria-hidden="true" style="vertical-align:middle;margin-right:4px;"></span>';
}

// Lesson Links Shortcode
add_shortcode( 'show-lesson-links', 'lessonLinksWidget' );
function lessonLinksWidget( array $atts ): string {
	$a    = shortcode_atts( array( 'style' => 'list' ), $atts );
	$data = json_decode( get_post_meta( get_the_ID(), 'links-in-use', true ) );

	if ( $a['style'] === 'list' ) {
		$output = '<div class="widget_lesson_link_widget"><ul>';
	} else {
		$output = '<div>';
	}

	if ( is_array( $data ) ) {
		foreach ( $data as $item ) {
			$url      = esc_url( $item->url ?? '' );
			$filename = esc_html( $item->filename ?? $url );

			if ( $a['style'] === 'list' ) {
				$output .= '<li>' . wpme_lesson_link_icon( $url, $filename ) . '<a href="' . $url . '" target="_blank">' . $filename . '</a></li>';
			} else {
				$output .= do_shortcode( '[course-material-download src="' . $url . '"]' . $filename . '[/course-material-download]' );
			}
		}
	}

	$break = '<div style="clear:both;"></div>';
	if ( $a['style'] === 'list' ) {
		$output .= '</ul>' . $break . '</div>';
	} else {
		$output .= $break . '</div>';
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

    $data = json_decode( get_post_meta( get_the_ID(), 'links-in-use', true ) );
    echo '<ul>';
    if ( is_array( $data ) ) {
        foreach ( $data as $item ) {
            $url      = esc_url( $item->url ?? '' );
            $filename = esc_html( $item->filename ?? $url );
            echo '<li>' . wpme_lesson_link_icon( $url, $filename ) . '<a href="' . $url . '" target="_blank">' . $filename . '</a></li>';
        }
    }
    echo '</ul>';
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

<?php
get_header();
?>
<div class="container" style="padding-top: 40px;">
    <?php
    do_action( 'lifterlms_single_membership_before_summary' );
    echo apply_filters( 'the_content', apply_filters( 'lifterlms_full_description', do_shortcode( $post->post_content ) ) );
    do_action( 'lifterlms_single_membership_after_summary' );

    echo do_shortcode("[llms_courses_in_membership]");
    ?>
</div>
<?php get_footer(); ?>

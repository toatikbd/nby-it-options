<?php
/**
 * Single career page wrapper (theme header/footer + two-column job layout).
 * Override in your theme at nby-it-options/single-career.php.
 *
 * @package NBY_IT_Options
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="content" class="site-main nby-single-page">
	<?php
	while ( have_posts() ) :
		the_post();
		echo do_shortcode( '[nby_career_single]' );
	endwhile;
	?>
</main>
<?php
get_footer();

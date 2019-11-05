<?php
/**
 * The template for displaying all single posts.
 *
 * @package storefront
 */

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<?php while ( have_posts() ) : the_post();

			do_action( 'storefront_single_post_before' );

			if( isset( $_GET['signup'] ) ) {

				$signed_up = esc_html( $_GET['signup'] );

				if( 'success' == $signed_up ) {
					?>
					<div class="form-success-box">
						<?php
						printf( __( 'Thank you for signing up! You will receive an email shortly confirming your participation in this event and the organisers will be in touch with you closer to the time with further details. %sPlease check your spam folder if you do not see the email in your inbox.%s', 'do-action' ), '<u><em>', '</em></u>' );
						?>
					</div>
					<?php
				} else {
					?>
					<div class="form-error-box">
						<?php
						_e( 'There was an error with your submission - please try again.', 'do-action' );
						?>
					</div>
					<?php
				}

			}

			if( isset( $_GET['application'] ) ) {

				$applied = esc_html( $_GET['application'] );

				if( 'success' == $applied ) {
					?>
					<div class="form-success-box">
						<?php
						_e( 'Thank you for applying! You will receive an email shortly confirming your application for this event and the organisers will be in touch with you closer to the time with further details.', 'do-action' );
						?>
					</div>
					<?php
				} else {
					?>
					<div class="form-error-box">
						<?php
						_e( 'There was an error with your submission - please try again.', 'do-action' );
						?>
					</div>
					<?php
				}

			}

			get_template_part( 'content', 'single' );

		endwhile; // End of the loop. ?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php
get_sidebar( 'event' );

do_action_functions()->event_form();

get_footer();

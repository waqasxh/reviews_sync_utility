<?php
/**
 * Included from Client_Reviews_Shortcode::render(). Expects $reviews (array of DB rows)
 * and $layout in scope. All output is escaped -- review content is untrusted, public API
 * data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<ul class="client-reviews client-reviews--list" data-layout="<?php echo esc_attr( $layout ); ?>">
	<?php if ( empty( $reviews ) ) : ?>
		<li class="client-reviews__empty"><?php esc_html_e( 'No reviews to show yet.', 'client-reviews' ); ?></li>
	<?php else : ?>
		<?php foreach ( $reviews as $review ) : ?>
			<li class="client-reviews__row<?php echo $review['is_featured'] ? ' client-reviews__row--featured' : ''; ?>">
				<div class="client-reviews__rating" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: 1-5 star rating */ __( '%d out of 5 stars', 'client-reviews' ), (int) $review['rating'] ) ); ?>">
					<?php echo esc_html( str_repeat( '★', (int) $review['rating'] ) . str_repeat( '☆', 5 - (int) $review['rating'] ) ); ?>
				</div>
				<strong class="client-reviews__author"><?php echo esc_html( $review['author_name'] ); ?></strong>
				<p class="client-reviews__text"><?php echo wp_kses_post( $review['review_text'] ); ?></p>
			</li>
		<?php endforeach; ?>
	<?php endif; ?>
</ul>

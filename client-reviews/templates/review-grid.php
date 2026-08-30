<?php
/**
 * Included from Client_Reviews_Shortcode::render(). Expects $reviews (array of DB rows)
 * and $layout in scope. All output is escaped -- review content is untrusted, public API
 * data.
 *
 * Review text is truncated via Client_Reviews_Shortcode::excerpt() (word-boundary, not
 * mid-word) with a "Read full review on Google" link to the review's real source_url --
 * full-length reviews made cards too tall and inconsistent in height; this keeps the
 * grid scannable while still linking through to the authentic, unedited review on
 * Google.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="client-reviews client-reviews--grid" data-layout="<?php echo esc_attr( $layout ); ?>">
	<?php if ( empty( $reviews ) ) : ?>
		<p class="client-reviews__empty"><?php esc_html_e( 'No reviews to show yet.', 'client-reviews' ); ?></p>
	<?php else : ?>
		<?php foreach ( $reviews as $review ) : ?>
			<?php $excerpt = Client_Reviews_Shortcode::excerpt( $review['review_text'] ); ?>
			<div class="client-reviews__card<?php echo $review['is_featured'] ? ' client-reviews__card--featured' : ''; ?>">
				<div class="client-reviews__header">
					<?php if ( ! empty( $review['author_photo_url'] ) ) : ?>
						<img class="client-reviews__avatar" src="<?php echo esc_url( $review['author_photo_url'] ); ?>" alt="" width="40" height="40" />
					<?php endif; ?>
					<span class="client-reviews__author"><?php echo esc_html( $review['author_name'] ); ?></span>
				</div>
				<div class="client-reviews__rating" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: 1-5 star rating */ __( '%d out of 5 stars', 'client-reviews' ), (int) $review['rating'] ) ); ?>">
					<?php echo esc_html( str_repeat( '★', (int) $review['rating'] ) . str_repeat( '☆', 5 - (int) $review['rating'] ) ); ?>
				</div>
				<p class="client-reviews__text"><?php echo esc_html( $excerpt['text'] ); ?><?php echo $excerpt['truncated'] ? '&hellip;' : ''; ?></p>
				<?php if ( ! empty( $review['source_url'] ) ) : ?>
					<a class="client-reviews__readmore" href="<?php echo esc_url( $review['source_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Read full review on Google', 'client-reviews' ); ?></a>
				<?php endif; ?>
				<span class="client-reviews__time">
					<?php echo esc_html( $review['relative_time'] ? $review['relative_time'] : ( $review['published_at'] ? date_i18n( get_option( 'date_format' ), strtotime( $review['published_at'] ) ) : '' ) ); ?>
				</span>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

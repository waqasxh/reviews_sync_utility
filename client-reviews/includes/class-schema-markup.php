<?php
/**
 * Emits Review/AggregateRating JSON-LD so Google can pick up star ratings in search
 * snippets. Rendered alongside the visible markup, never instead of it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Client_Reviews_Schema_Markup {

	/**
	 * @param array  $reviews       DB rows (author_name, rating, review_text, published_at).
	 * @param string $business_name Defaults to the site name if not supplied.
	 */
	public static function render( $reviews, $business_name = '' ) {
		if ( empty( $reviews ) ) {
			return;
		}

		if ( '' === $business_name ) {
			$business_name = get_bloginfo( 'name' );
		}

		$rating_sum = 0;
		$review_ld  = array();

		foreach ( $reviews as $review ) {
			$rating_sum += (int) $review['rating'];

			$item = array(
				'@type'       => 'Review',
				'author'      => array(
					'@type' => 'Person',
					'name'  => wp_strip_all_tags( $review['author_name'] ),
				),
				'reviewRating' => array(
					'@type'       => 'Rating',
					'ratingValue' => (int) $review['rating'],
					'bestRating'  => 5,
				),
				'reviewBody' => wp_strip_all_tags( $review['review_text'] ),
			);

			if ( ! empty( $review['published_at'] ) ) {
				$item['datePublished'] = gmdate( 'Y-m-d', strtotime( $review['published_at'] ) );
			}

			$review_ld[] = $item;
		}

		$count   = count( $reviews );
		$average = $count > 0 ? round( $rating_sum / $count, 1 ) : 0;

		$json_ld = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'LocalBusiness',
			'name'            => $business_name,
			'aggregateRating' => array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $average,
				'reviewCount' => $count,
			),
			'review' => $review_ld,
		);

		echo '<script type="application/ld+json">' . wp_json_encode( $json_ld ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

<?php
/**
 * Renders the [client_reviews] shortcode. Client_Reviews_Block::render_callback()
 * delegates to render() too, so there is exactly one place review markup is produced.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Client_Reviews_Shortcode {

	public static function register() {
		add_shortcode( 'client_reviews', array( __CLASS__, 'render' ) );
		add_shortcode( 'client_reviews_rating', array( __CLASS__, 'render_rating_summary' ) );
	}

	/**
	 * Renders the real Google aggregate ("Rated 4.9 out of 5 based on 180 Google
	 * reviews") from the business-level options the importer stores -- NOT computed
	 * from the local sample of individual review rows, which is usually a much
	 * smaller number (5 on the free Google Places tier) and would misrepresent the
	 * business's actual total.
	 *
	 * @param array $atts link (yes/no, default yes -- links the review count to the
	 *                    business's Google reviews page).
	 */
	public static function render_rating_summary( $atts ) {
		$atts = shortcode_atts(
			array(
				'link' => 'yes',
			),
			$atts,
			'client_reviews_rating'
		);

		$rating = get_option( 'client_reviews_google_rating' );
		$count  = get_option( 'client_reviews_google_review_count' );

		if ( empty( $rating ) || empty( $count ) ) {
			return '';
		}

		$count_text = sprintf(
			/* translators: %s: number of Google reviews */
			_n( '%s Google review', '%s Google reviews', (int) $count, 'client-reviews' ),
			number_format_i18n( (int) $count )
		);

		if ( 'yes' === $atts['link'] ) {
			$place_id = get_option( 'client_reviews_business_place_id' );
			if ( $place_id ) {
				$count_text = sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( 'https://search.google.com/local/reviews?placeid=' . $place_id ),
					esc_html( $count_text )
				);
			} else {
				$count_text = esc_html( $count_text );
			}
		} else {
			$count_text = esc_html( $count_text );
		}

		return sprintf(
			/* translators: 1: numeric rating out of 5, 2: linked/unlinked review count text */
			__( 'Rated %1$s out of 5 based on %2$s', 'client-reviews' ),
			esc_html( number_format_i18n( (float) $rating, 1 ) ),
			$count_text
		);
	}

	/**
	 * @param array $atts limit, min_rating, layout (grid|list|carousel), business_name.
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'         => 5,
				'min_rating'    => 0,
				'layout'        => 'grid',
				'business_name' => '',
			),
			$atts,
			'client_reviews'
		);

		$limit      = max( 1, (int) $atts['limit'] );
		$min_rating = (float) $atts['min_rating'];
		$layout     = in_array( $atts['layout'], array( 'grid', 'list', 'carousel' ), true ) ? $atts['layout'] : 'grid';

		$reviews = self::query_reviews( $limit, $min_rating );

		ob_start();

		Client_Reviews_Schema_Markup::render( $reviews, $atts['business_name'] );

		// A carousel is JS-driven UI that's out of scope for this scaffold; it reuses the
		// grid markup with a data-layout hook so a future carousel script can progressively
		// enhance it without a template change.
		$template_layout = ( 'carousel' === $layout ) ? 'grid' : $layout;
		$template_file   = CLIENT_REVIEWS_PLUGIN_DIR . 'templates/review-' . $template_layout . '.php';

		if ( file_exists( $template_file ) ) {
			include $template_file;
		}

		return ob_get_clean();
	}

	/**
	 * Truncates review text to CLIENT_REVIEWS_EXCERPT_LENGTH characters on a word
	 * boundary (never mid-word). Lives here rather than as a bare function in the
	 * template so it's declared exactly once even if the shortcode renders more than
	 * once on the same page (templates get include()'d fresh per render).
	 *
	 * @return array{text: string, truncated: bool}
	 */
	public static function excerpt( $text, $length = 150 ) {
		$text = trim( (string) $text );
		if ( mb_strlen( $text ) <= $length ) {
			return array(
				'text'      => $text,
				'truncated' => false,
			);
		}

		$cut        = mb_substr( $text, 0, $length );
		$last_space = mb_strrpos( $cut, ' ' );
		if ( false !== $last_space && $last_space > 0 ) {
			$cut = mb_substr( $cut, 0, $last_space );
		}

		return array(
			'text'      => rtrim( $cut, ",.;: \t\n" ),
			'truncated' => true,
		);
	}

	private static function query_reviews( $limit, $min_rating ) {
		global $wpdb;
		$table = $wpdb->prefix . CLIENT_REVIEWS_TABLE;

		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE is_visible = 1 AND rating >= %f ORDER BY is_featured DESC, published_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$min_rating,
			$limit
		);

		return $wpdb->get_results( $sql, ARRAY_A );
	}
}

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

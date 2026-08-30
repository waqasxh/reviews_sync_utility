<?php
/**
 * Registers the client-reviews/reviews dynamic block. render_callback points at the same
 * Client_Reviews_Shortcode::render() the [client_reviews] shortcode uses, so there is one
 * rendering implementation shared by both surfaces.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Client_Reviews_Block {

	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return; // WordPress too old for the block editor; the shortcode still works.
		}

		wp_register_script(
			'client-reviews-block-editor',
			CLIENT_REVIEWS_PLUGIN_URL . 'assets/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			CLIENT_REVIEWS_VERSION,
			true
		);

		register_block_type(
			'client-reviews/reviews',
			array(
				'attributes'      => array(
					'limit'         => array(
						'type'    => 'number',
						'default' => 5,
					),
					'minRating'     => array(
						'type'    => 'number',
						'default' => 0,
					),
					'layout'        => array(
						'type'    => 'string',
						'default' => 'grid',
					),
					'businessName'  => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'editor_script'   => 'client-reviews-block-editor',
				'render_callback' => array( __CLASS__, 'render_callback' ),
			)
		);
	}

	public static function render_callback( $attributes ) {
		$atts = array(
			'limit'         => isset( $attributes['limit'] ) ? $attributes['limit'] : 5,
			'min_rating'    => isset( $attributes['minRating'] ) ? $attributes['minRating'] : 0,
			'layout'        => isset( $attributes['layout'] ) ? $attributes['layout'] : 'grid',
			'business_name' => isset( $attributes['businessName'] ) ? $attributes['businessName'] : '',
		);

		return Client_Reviews_Shortcode::render( $atts );
	}
}

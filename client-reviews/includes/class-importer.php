<?php
/**
 * Parses a ReviewSync export JSON file, validates it, and upserts rows into
 * wp_client_reviews keyed by review_id. Never partially imports: the whole file is
 * validated before any database write happens.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Client_Reviews_Importer {

	const IMPORT_LOG_OPTION = 'client_reviews_import_log';
	const IMPORT_LOG_MAX    = 20;

	/**
	 * @param string $file_path Path to a JSON file already on disk (e.g. from $_FILES tmp_name).
	 * @return array|WP_Error Import summary on success, WP_Error with a user-facing message on failure.
	 */
	public static function import_from_file( $file_path ) {
		$raw = file_get_contents( $file_path );
		if ( false === $raw ) {
			return new WP_Error( 'client_reviews_read_failed', __( 'Could not read the uploaded file.', 'client-reviews' ) );
		}

		$data = json_decode( $raw, true );
		if ( null === $data && JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'client_reviews_invalid_json', __( 'The uploaded file is not valid JSON.', 'client-reviews' ) );
		}

		$validation = self::validate( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		global $wpdb;
		$table   = $wpdb->prefix . CLIENT_REVIEWS_TABLE;
		$placeId = $data['business']['place_id'];
		$source  = $data['source'];
		$default_visibility = get_option( 'client_reviews_default_visibility', 'visible' );
		$default_is_visible  = ( 'visible' === $default_visibility ) ? 1 : 0;

		$inserted = 0;
		$updated  = 0;

		foreach ( $data['reviews'] as $review ) {
			$result = self::upsert_review( $table, $review, $placeId, $source, $default_is_visible );
			if ( 'inserted' === $result ) {
				$inserted++;
			} elseif ( 'updated' === $result ) {
				$updated++;
			}
		}

		$summary = array(
			'business_name' => $data['business']['name'],
			'place_id'      => $placeId,
			'source'        => $source,
			'inserted'      => $inserted,
			'updated'       => $updated,
			'total'         => count( $data['reviews'] ),
		);

		self::log_import( $summary );

		return $summary;
	}

	/**
	 * @return true|WP_Error
	 */
	private static function validate( $data ) {
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'client_reviews_invalid_schema', __( 'Export file is not a JSON object.', 'client-reviews' ) );
		}

		$schema_version = isset( $data['schema_version'] ) ? (string) $data['schema_version'] : '';
		$major          = strtok( $schema_version, '.' );
		if ( CLIENT_REVIEWS_SCHEMA_VERSION_SUPPORTED !== $major ) {
			return new WP_Error(
				'client_reviews_unsupported_schema',
				sprintf(
					/* translators: %s: schema version found in the file */
					__( 'Unsupported schema_version "%s". This plugin understands schema major version 1.', 'client-reviews' ),
					$schema_version ? $schema_version : '(missing)'
				)
			);
		}

		if ( empty( $data['business']['place_id'] ) || empty( $data['business']['name'] ) ) {
			return new WP_Error( 'client_reviews_missing_business', __( 'Export is missing business.place_id or business.name.', 'client-reviews' ) );
		}

		if ( empty( $data['source'] ) ) {
			return new WP_Error( 'client_reviews_missing_source', __( 'Export is missing the top-level "source" field.', 'client-reviews' ) );
		}

		if ( ! isset( $data['reviews'] ) || ! is_array( $data['reviews'] ) ) {
			return new WP_Error( 'client_reviews_missing_reviews', __( 'Export is missing a "reviews" array.', 'client-reviews' ) );
		}

		foreach ( $data['reviews'] as $index => $review ) {
			if ( empty( $review['review_id'] ) ) {
				return new WP_Error(
					'client_reviews_missing_review_id',
					sprintf(
						/* translators: %d: zero-based index of the offending review in the file */
						__( 'Review at index %d is missing review_id -- cannot upsert without a stable id.', 'client-reviews' ),
						$index
					)
				);
			}
			if ( ! isset( $review['rating'] ) || ! is_numeric( $review['rating'] ) ) {
				return new WP_Error(
					'client_reviews_missing_rating',
					sprintf(
						/* translators: 1: review_id of the offending review, %2$d retained for parity with other messages */
						__( 'Review "%1$s" is missing a numeric rating.', 'client-reviews' ),
						$review['review_id']
					)
				);
			}
		}

		return true;
	}

	/**
	 * @return string "inserted"|"updated"|"skipped"
	 */
	private static function upsert_review( $table, $review, $place_id, $source, $default_is_visible ) {
		global $wpdb;

		$review_id = sanitize_text_field( $review['review_id'] );
		$existing  = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE review_id = %s", $review_id ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		$published_at = null;
		if ( ! empty( $review['published_at'] ) ) {
			$timestamp = strtotime( $review['published_at'] );
			if ( false !== $timestamp ) {
				$published_at = gmdate( 'Y-m-d H:i:s', $timestamp );
			}
		}

		$fields = array(
			'review_id'         => $review_id,
			'business_place_id' => $place_id,
			'author_name'       => isset( $review['author_name'] ) ? $review['author_name'] : '',
			'author_photo_url'  => isset( $review['author_photo_url'] ) ? $review['author_photo_url'] : null,
			'rating'            => (int) $review['rating'],
			'review_text'       => isset( $review['text'] ) ? $review['text'] : '',
			'relative_time'     => isset( $review['relative_time'] ) ? $review['relative_time'] : null,
			'published_at'      => $published_at,
			'language'          => isset( $review['language'] ) ? $review['language'] : null,
			'source_url'        => isset( $review['source_url'] ) ? $review['source_url'] : null,
			'source'            => $source,
			'imported_at'       => current_time( 'mysql', true ),
		);

		if ( $existing ) {
			// Re-imports never clobber an admin's manual visible/featured choices.
			$wpdb->update( $table, $fields, array( 'id' => $existing ) );
			return 'updated';
		}

		$fields['is_visible']  = $default_is_visible;
		$fields['is_featured'] = 0;
		$wpdb->insert( $table, $fields );
		return 'inserted';
	}

	private static function log_import( $summary ) {
		$log   = get_option( self::IMPORT_LOG_OPTION, array() );
		$entry = array_merge( $summary, array( 'imported_at' => current_time( 'mysql' ) ) );

		array_unshift( $log, $entry );
		$log = array_slice( $log, 0, self::IMPORT_LOG_MAX );

		update_option( self::IMPORT_LOG_OPTION, $log );
	}
}

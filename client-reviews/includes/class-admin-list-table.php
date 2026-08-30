<?php
/**
 * Admin list table for wp_client_reviews. Visibility/featured toggles are rendered as
 * buttons wired to AJAX (see assets/admin.js + Client_Reviews_Admin_Page::ajax_*); bulk
 * delete uses WP_List_Table's own nonce-checked bulk-action flow.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Client_Reviews_List_Table extends WP_List_Table {

	const PER_PAGE = 20;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'review',
				'plural'   => 'reviews',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox" />',
			'author_name'  => __( 'Author', 'client-reviews' ),
			'rating'       => __( 'Rating', 'client-reviews' ),
			'review_text'  => __( 'Review', 'client-reviews' ),
			'published_at' => __( 'Published', 'client-reviews' ),
			'source'       => __( 'Source', 'client-reviews' ),
			'is_visible'   => __( 'Visible', 'client-reviews' ),
			'is_featured'  => __( 'Featured', 'client-reviews' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'rating'       => array( 'rating', false ),
			'published_at' => array( 'published_at', true ),
		);
	}

	public function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'client-reviews' ),
		);
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="review[]" value="%d" />', (int) $item['id'] );
	}

	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	protected function get_default_primary_column_name() {
		return 'author_name';
	}

	public function column_author_name( $item ) {
		$delete_nonce = wp_create_nonce( 'client_reviews_delete_' . $item['id'] );
		$actions      = array(
			'delete' => sprintf(
				'<a href="#" class="client-reviews-delete" data-id="%1$d" data-nonce="%2$s">%3$s</a>',
				(int) $item['id'],
				esc_attr( $delete_nonce ),
				esc_html__( 'Delete', 'client-reviews' )
			),
		);

		return esc_html( $item['author_name'] ) . $this->row_actions( $actions );
	}

	public function column_rating( $item ) {
		return esc_html( str_repeat( '★', (int) $item['rating'] ) );
	}

	public function column_review_text( $item ) {
		$text = wp_strip_all_tags( $item['review_text'] );
		return esc_html( mb_strlen( $text ) > 120 ? mb_substr( $text, 0, 120 ) . '…' : $text );
	}

	public function column_is_visible( $item ) {
		return $this->render_toggle_button( $item, 'is_visible', __( 'Visible', 'client-reviews' ), __( 'Hidden', 'client-reviews' ) );
	}

	public function column_is_featured( $item ) {
		return $this->render_toggle_button( $item, 'is_featured', __( 'Featured', 'client-reviews' ), __( 'Not featured', 'client-reviews' ) );
	}

	private function render_toggle_button( $item, $field, $on_label, $off_label ) {
		$is_on = ! empty( $item[ $field ] );

		return sprintf(
			'<button type="button" class="button client-reviews-toggle" data-id="%1$d" data-field="%2$s" data-nonce="%3$s">%4$s</button>',
			(int) $item['id'],
			esc_attr( $field ),
			esc_attr( wp_create_nonce( 'client_reviews_toggle_' . $item['id'] ) ),
			$is_on ? esc_html( $on_label ) : esc_html( $off_label )
		);
	}

	public function prepare_items() {
		global $wpdb;
		$table = $wpdb->prefix . CLIENT_REVIEWS_TABLE;

		$this->process_bulk_action();

		$per_page     = self::PER_PAGE;
		$current_page = $this->get_pagenum();

		$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$allowed_orderby = array( 'rating', 'published_at' );
		$orderby         = ( ! empty( $_GET['orderby'] ) && in_array( $_GET['orderby'], $allowed_orderby, true ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_key( $_GET['orderby'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: 'published_at';
		$order           = ( ! empty( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ) ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$per_page,
				( $current_page - 1 ) * $per_page
			),
			ARRAY_A
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
	}

	private function process_bulk_action() {
		if ( 'delete' !== $this->current_action() || empty( $_POST['review'] ) ) {
			return;
		}

		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . CLIENT_REVIEWS_TABLE;
		$ids   = array_map( 'intval', (array) $_POST['review'] );

		foreach ( $ids as $id ) {
			$wpdb->delete( $table, array( 'id' => $id ) );
		}
	}
}

<?php
/**
 * Admin menu, the reviews list screen, the import (upload + history) screen, the default
 * visibility setting, and the AJAX handlers the list table's toggle/delete buttons call.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Client_Reviews_Admin_Page {

	public static function register_menu() {
		add_menu_page(
			__( 'Client Reviews', 'client-reviews' ),
			__( 'Client Reviews', 'client-reviews' ),
			'manage_options',
			'client-reviews',
			array( __CLASS__, 'render_reviews_page' ),
			'dashicons-star-filled',
			26
		);

		add_submenu_page( 'client-reviews', __( 'All Reviews', 'client-reviews' ), __( 'All Reviews', 'client-reviews' ), 'manage_options', 'client-reviews', array( __CLASS__, 'render_reviews_page' ) );
		add_submenu_page( 'client-reviews', __( 'Import', 'client-reviews' ), __( 'Import', 'client-reviews' ), 'manage_options', 'client-reviews-import', array( __CLASS__, 'render_import_page' ) );
		add_submenu_page( 'client-reviews', __( 'Settings', 'client-reviews' ), __( 'Settings', 'client-reviews' ), 'manage_options', 'client-reviews-settings', array( __CLASS__, 'render_settings_page' ) );
	}

	public static function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'client-reviews' ) ) {
			return;
		}

		wp_enqueue_style( 'client-reviews-admin', CLIENT_REVIEWS_PLUGIN_URL . 'assets/admin.css', array(), CLIENT_REVIEWS_VERSION );
		wp_enqueue_script( 'client-reviews-admin', CLIENT_REVIEWS_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), CLIENT_REVIEWS_VERSION, true );
		wp_localize_script(
			'client-reviews-admin',
			'ClientReviewsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'confirmDelete' => __( 'Delete this review? This cannot be undone.', 'client-reviews' ),
				),
			)
		);
	}

	public static function render_reviews_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$table = new Client_Reviews_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Client Reviews', 'client-reviews' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'bulk-reviews' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	public static function render_import_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = self::handle_import_submission();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Reviews', 'client-reviews' ); ?></h1>
			<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_html() pieces in handle_import_submission(). ?>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'client_reviews_import', 'client_reviews_import_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="client_reviews_file"><?php esc_html_e( 'JSON export file', 'client-reviews' ); ?></label></th>
						<td><input type="file" name="client_reviews_file" id="client_reviews_file" accept="application/json" required /></td>
					</tr>
				</table>
				<?php submit_button( __( 'Import', 'client-reviews' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Import history', 'client-reviews' ); ?></h2>
			<?php self::render_import_history(); ?>
		</div>
		<?php
	}

	/**
	 * @return string Pre-escaped admin-notice HTML, or an empty string.
	 */
	private static function handle_import_submission() {
		$nonce_ok = ! empty( $_POST['client_reviews_import_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( $_POST['client_reviews_import_nonce'] ), 'client_reviews_import' );

		if ( ! $nonce_ok || empty( $_FILES['client_reviews_file']['tmp_name'] ) ) {
			return '';
		}

		$result = Client_Reviews_Importer::import_from_file( $_FILES['client_reviews_file']['tmp_name'] );

		if ( is_wp_error( $result ) ) {
			return '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
		}

		$message = sprintf(
			/* translators: 1: new reviews inserted, 2: existing reviews updated, 3: business name, 4: source (google_places|outscraper) */
			__( 'Imported %1$d new and updated %2$d existing reviews for %3$s (source: %4$s).', 'client-reviews' ),
			(int) $result['inserted'],
			(int) $result['updated'],
			$result['business_name'],
			$result['source']
		);

		return '<div class="notice notice-success"><p>' . esc_html( $message ) . '</p></div>';
	}

	private static function render_import_history() {
		$log = get_option( 'client_reviews_import_log', array() );

		if ( empty( $log ) ) {
			echo '<p>' . esc_html__( 'No imports yet.', 'client-reviews' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'client-reviews' ); ?></th>
					<th><?php esc_html_e( 'Business', 'client-reviews' ); ?></th>
					<th><?php esc_html_e( 'Source', 'client-reviews' ); ?></th>
					<th><?php esc_html_e( 'Inserted', 'client-reviews' ); ?></th>
					<th><?php esc_html_e( 'Updated', 'client-reviews' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $log as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( $entry['imported_at'] ); ?></td>
						<td><?php echo esc_html( $entry['business_name'] ); ?></td>
						<td><?php echo esc_html( $entry['source'] ); ?></td>
						<td><?php echo (int) $entry['inserted']; ?></td>
						<td><?php echo (int) $entry['updated']; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if (
			! empty( $_POST['client_reviews_settings_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( $_POST['client_reviews_settings_nonce'] ), 'client_reviews_settings' )
		) {
			$value = ( ! empty( $_POST['client_reviews_default_visibility'] ) && 'hidden' === $_POST['client_reviews_default_visibility'] )
				? 'hidden'
				: 'visible';
			update_option( 'client_reviews_default_visibility', $value );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'client-reviews' ) . '</p></div>';
		}

		$current = get_option( 'client_reviews_default_visibility', 'visible' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Client Reviews Settings', 'client-reviews' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'client_reviews_settings', 'client_reviews_settings_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'New reviews default to', 'client-reviews' ); ?></th>
						<td>
							<label>
								<input type="radio" name="client_reviews_default_visibility" value="visible" <?php checked( $current, 'visible' ); ?> />
								<?php esc_html_e( 'Visible', 'client-reviews' ); ?>
							</label><br />
							<label>
								<input type="radio" name="client_reviews_default_visibility" value="hidden" <?php checked( $current, 'hidden' ); ?> />
								<?php esc_html_e( 'Hidden (pending manual approval)', 'client-reviews' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Only applies to newly inserted reviews on import; existing reviews keep whatever visibility you already set for them.', 'client-reviews' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public static function ajax_toggle_visible() {
		self::ajax_toggle( 'is_visible' );
	}

	public static function ajax_toggle_featured() {
		self::ajax_toggle( 'is_featured' );
	}

	private static function ajax_toggle( $field ) {
		$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! $id || ! wp_verify_nonce( $nonce, 'client_reviews_toggle_' . $id ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'client-reviews' ) ), 403 );
		}

		global $wpdb;
		$table   = $wpdb->prefix . CLIENT_REVIEWS_TABLE;
		$current = (int) $wpdb->get_var( $wpdb->prepare( "SELECT {$field} FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$new     = $current ? 0 : 1;

		$wpdb->update( $table, array( $field => $new ), array( 'id' => $id ) );

		wp_send_json_success( array( 'value' => $new ) );
	}

	public static function ajax_delete() {
		$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! $id || ! wp_verify_nonce( $nonce, 'client_reviews_delete_' . $id ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'client-reviews' ) ), 403 );
		}

		global $wpdb;
		$table = $wpdb->prefix . CLIENT_REVIEWS_TABLE;
		$wpdb->delete( $table, array( 'id' => $id ) );

		wp_send_json_success();
	}
}

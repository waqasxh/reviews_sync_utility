<?php
/**
 * Plugin Name: Client Reviews
 * Description: Imports normalized Google/Outscraper review JSON exports (from the ReviewSync console app) and renders them via shortcode or block.
 * Version: 0.1.0
 * Author: NextGenDigital
 * Text Domain: client-reviews
 *
 * Ingestion is decoupled from the console app by a JSON file contract -- see the
 * repo-root CLAUDE.md. This plugin never talks to Google or Outscraper directly.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CLIENT_REVIEWS_VERSION', '0.1.0' );
define( 'CLIENT_REVIEWS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLIENT_REVIEWS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CLIENT_REVIEWS_TABLE', 'client_reviews' );
define( 'CLIENT_REVIEWS_SCHEMA_VERSION_SUPPORTED', '1' );

require_once CLIENT_REVIEWS_PLUGIN_DIR . 'includes/class-importer.php';
require_once CLIENT_REVIEWS_PLUGIN_DIR . 'includes/class-schema-markup.php';
require_once CLIENT_REVIEWS_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once CLIENT_REVIEWS_PLUGIN_DIR . 'includes/class-block.php';

if ( is_admin() ) {
	require_once CLIENT_REVIEWS_PLUGIN_DIR . 'includes/class-admin-list-table.php';
	require_once CLIENT_REVIEWS_PLUGIN_DIR . 'includes/class-admin-page.php';
}

/**
 * Creates the wp_client_reviews table. business_place_id is kept even in today's
 * single-tenant (one plugin install = one business) usage so a future multi-tenant
 * upgrade never requires a schema migration -- see CLAUDE.md "Multi-tenant future".
 */
function client_reviews_activate() {
	global $wpdb;

	$table_name      = $wpdb->prefix . CLIENT_REVIEWS_TABLE;
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table_name} (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		review_id VARCHAR(191) NOT NULL,
		business_place_id VARCHAR(191) NULL,
		author_name VARCHAR(255),
		author_photo_url TEXT,
		rating TINYINT UNSIGNED,
		review_text TEXT,
		relative_time VARCHAR(100),
		published_at DATETIME NULL,
		language VARCHAR(10),
		source_url TEXT,
		source VARCHAR(50),
		is_visible TINYINT(1) DEFAULT 1,
		is_featured TINYINT(1) DEFAULT 0,
		imported_at DATETIME,
		UNIQUE KEY review_id_unique (review_id)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	add_option( 'client_reviews_default_visibility', 'visible' );
	add_option( 'client_reviews_import_log', array() );
	add_option( 'client_reviews_db_version', CLIENT_REVIEWS_VERSION );
}
register_activation_hook( __FILE__, 'client_reviews_activate' );

add_action( 'init', array( 'Client_Reviews_Shortcode', 'register' ) );
add_action( 'init', array( 'Client_Reviews_Block', 'register' ) );

if ( is_admin() ) {
	add_action( 'admin_menu', array( 'Client_Reviews_Admin_Page', 'register_menu' ) );
	add_action( 'admin_enqueue_scripts', array( 'Client_Reviews_Admin_Page', 'enqueue_assets' ) );
	add_action( 'wp_ajax_client_reviews_toggle_visible', array( 'Client_Reviews_Admin_Page', 'ajax_toggle_visible' ) );
	add_action( 'wp_ajax_client_reviews_toggle_featured', array( 'Client_Reviews_Admin_Page', 'ajax_toggle_featured' ) );
	add_action( 'wp_ajax_client_reviews_delete', array( 'Client_Reviews_Admin_Page', 'ajax_delete' ) );
}

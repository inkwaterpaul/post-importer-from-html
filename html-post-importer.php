<?php
/**
 * Plugin Name: Post Importer from HTML Files
 * Plugin URI: https://inkandwater.co.uk/post-importer
 * Description: Import HTML files as WordPress posts. Extracts title from h1, content from page-content div, and date from small tag.
 * Version: 1.0.0
 * Author: Ink & Water
 * Author URI: https://inkandwater.co.uk
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: html-post-importer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HPI_VERSION', '1.0.0');
define('HPI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HPI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HPI_PLUGIN_FILE', __FILE__);

// Include required files
require_once HPI_PLUGIN_DIR . 'includes/class-logger.php';
require_once HPI_PLUGIN_DIR . 'includes/class-content-extractor.php';
require_once HPI_PLUGIN_DIR . 'includes/class-importer.php';
require_once HPI_PLUGIN_DIR . 'includes/class-ajax-handler.php';
require_once HPI_PLUGIN_DIR . 'includes/class-admin-ui.php';

/**
 * Main plugin class
 */
class HTML_Post_Importer {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Initialize AJAX handlers
        HPI_AJAX_Handler::init();

        // Preserve post dates when publishing imported posts
        add_filter('wp_insert_post_data', array($this, 'preserve_imported_post_date'), 10, 2);
    }

    /**
     * Preserve the original post date when publishing imported posts
     */
    public function preserve_imported_post_date($data, $postarr) {
        // Only apply to posts that were imported by our plugin
        if (isset($postarr['ID']) && get_post_meta($postarr['ID'], '_hpi_extracted_date', true)) {
            $original_date = get_post_meta($postarr['ID'], '_hpi_extracted_date', true);

            // If the post is being published and has an original date, preserve it
            if ($original_date && $data['post_status'] === 'publish') {
                $data['post_date'] = $original_date;
                $data['post_date_gmt'] = get_gmt_from_date($original_date);
            }
        }

        return $data;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('HTML Post Importer', 'html-post-importer'),
            __('HTML Importer', 'html-post-importer'),
            'manage_options',
            'html-post-importer',
            array('HPI_Admin_UI', 'render_page'),
            'dashicons-upload',
            30
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin page
        if ('toplevel_page_html-post-importer' !== $hook) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'hpi-admin-style',
            HPI_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            HPI_VERSION
        );

        // Enqueue scripts
        wp_enqueue_script(
            'hpi-admin-script',
            HPI_PLUGIN_URL . 'assets/js/admin-script.js',
            array('jquery'),
            HPI_VERSION,
            true
        );

        // Localize script
        wp_localize_script('hpi-admin-script', 'hpiAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hpi_import_nonce'),
            'strings' => array(
                'processing' => __('Processing...', 'html-post-importer'),
                'success' => __('Import completed!', 'html-post-importer'),
                'error' => __('An error occurred.', 'html-post-importer'),
            )
        ));
    }
}

/**
 * Initialize the plugin
 */
function hpi_init() {
    return HTML_Post_Importer::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'hpi_init');

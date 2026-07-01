<?php
/**
 * Plugin Name: Post Importer from HTML Files
 * Plugin URI: https://inkandwater.co.uk/post-importer
 * Description: Import HTML files as WordPress posts. Extracts title from h1, content from page-content div, date from small tag, and images (featured image plus any others in the content).
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
define('POST_IMPORTER_VERSION', '1.0.0');
define('POST_IMPORTER_NAME', 'HTML Post Importer');
define('POST_IMPORTER_MENU_SLUG', 'html-post-importer');
define('POST_IMPORTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('POST_IMPORTER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('POST_IMPORTER_PLUGIN_FILE', __FILE__);

// Include required files
require_once POST_IMPORTER_PLUGIN_DIR . 'includes/class-logger.php';
require_once POST_IMPORTER_PLUGIN_DIR . 'includes/class-content-extractor.php';
require_once POST_IMPORTER_PLUGIN_DIR . 'includes/class-importer.php';
require_once POST_IMPORTER_PLUGIN_DIR . 'includes/class-ajax-handler.php';
require_once POST_IMPORTER_PLUGIN_DIR . 'includes/class-admin-ui.php';

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

        // Make sure the log table (created on activation) exists and is current -
        // covers upgrades where the table name/schema changed on an already-active install
        add_action('admin_init', array('POST_IMPORTER_Logger', 'maybe_upgrade_table'));

        // Initialize AJAX handlers
        POST_IMPORTER_AJAX_Handler::init();
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('HTML Post Importer', POST_IMPORTER_NAME),
            __('HTML Post Importer', POST_IMPORTER_NAME),
            'manage_options',
            POST_IMPORTER_MENU_SLUG,
            array('POST_IMPORTER_Admin_UI', 'render_page'),
            'dashicons-upload',
            30
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin page
        if ('toplevel_page_' . POST_IMPORTER_MENU_SLUG !== $hook) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'post-importer-admin-style',
            POST_IMPORTER_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            POST_IMPORTER_VERSION
        );

        // Enqueue scripts
        wp_enqueue_script(
            'post-importer-admin-script',
            POST_IMPORTER_PLUGIN_URL . 'assets/js/admin-script.js',
            array('jquery'),
            POST_IMPORTER_VERSION,
            true
        );

        // Localize script
        wp_localize_script('post-importer-admin-script', 'postImporterAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('post_importer_import_nonce'),
            'strings' => array(
                'processing' => __('Processing...', POST_IMPORTER_NAME),
                'success' => __('Import completed!', POST_IMPORTER_NAME),
                'error' => __('An error occurred.', POST_IMPORTER_NAME),
            )
        ));
    }
}

/**
 * Initialize the plugin
 */
function post_importer_init() {
    return HTML_Post_Importer::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'post_importer_init');

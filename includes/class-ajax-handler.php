<?php
/**
 * AJAX Handler Class
 * Handles AJAX requests for file import
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class POST_IMPORTER_AJAX_Handler {

    /**
     * Initialize AJAX handlers
     */
    public static function init() {
        add_action('wp_ajax_post_importer_import_files', array(__CLASS__, 'handle_import'));
        add_action('wp_ajax_post_importer_process_file', array(__CLASS__, 'handle_process_file'));
        add_action('wp_ajax_post_importer_preview_file', array(__CLASS__, 'handle_preview'));
        add_action('wp_ajax_post_importer_upload_media', array(__CLASS__, 'handle_upload_media'));
    }

    /**
     * Handle file import AJAX request
     */
    public static function handle_import() {
        // Start output buffering to catch any PHP errors/warnings
        ob_start();

        try {
            // Verify nonce
            check_ajax_referer('post_importer_import_nonce', 'nonce');

            // Check user capabilities
            if (!current_user_can('manage_options')) {
                ob_end_clean();
                wp_send_json_error(array(
                    'message' => __('You do not have permission to perform this action.', POST_IMPORTER_NAME )
                ));
            }

            // Check if files were uploaded
            if (empty($_FILES['pi_files'])) {
                ob_end_clean();
                wp_send_json_error(array(
                    'message' => __('No files were uploaded.', POST_IMPORTER_NAME )
                ));
            }

        // Get import options
        // Images/documents are not uploaded at this stage - the importer records
        // which ones are referenced, and they're uploaded afterwards via
        // handle_upload_media() once we know exactly what's needed.
        $options = array(
            'post_status' => isset($_POST['post_status']) ? sanitize_text_field($_POST['post_status']) : 'draft',
            'category_id' => isset($_POST['category_id']) ? absint($_POST['category_id']) : 0
        );

        // Process uploaded files
        $files = $_FILES['pi_files'];
        $file_count = count($files['name']);
        $file_paths = array();

        // Initialize results array for tracking
        $validation_errors = array();

        // Validate and prepare files
        for ($i = 0; $i < $file_count; $i++) {
            $file = array(
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            );

            // Validate file - collect errors but don't stop
            $validation = POST_IMPORTER_Content_Extractor::validate_file($file);

            if (is_wp_error($validation)) {
                $validation_errors[] = array(
                    'file' => $file['name'],
                    'error' => $validation->get_error_message()
                );
                continue; // Skip this file and move to the next
            }

            $file_paths[] = array(
                'path' => $file['tmp_name'],
                'name' => $file['name']
            );
        }

        // Import files
        $results = array(
            'success' => array(),
            'failed' => $validation_errors, // Start with validation errors
            'total' => $file_count
        );

        foreach ($file_paths as $file_info) {
            try {
                // Suppress any PHP warnings/errors that might corrupt JSON
                error_reporting(E_ERROR);
                $result = POST_IMPORTER_Importer::import_file($file_info['path'], $options);
                error_reporting(E_ALL);

                if (is_wp_error($result)) {
                    $results['failed'][] = array(
                        'file' => $file_info['name'],
                        'error' => $result->get_error_message()
                    );
                } else {
                    $results['success'][] = $result;

                    // Log the import
                    POST_IMPORTER_Logger::log_import($result['post_id'], $file_info['name'], 'success');
                }
            } catch (Exception $e) {
                // Catch any exceptions and add to failed list
                $results['failed'][] = array(
                    'file' => $file_info['name'],
                    'error' => 'Exception: ' . $e->getMessage()
                );
            } catch (Error $e) {
                // Catch fatal errors (PHP 7+)
                $results['failed'][] = array(
                    'file' => $file_info['name'],
                    'error' => 'Fatal error: ' . $e->getMessage()
                );
            }
        }

        // Clean output buffer before sending JSON
        ob_end_clean();

        // Send response
        wp_send_json_success(array(
            'message' => sprintf(
                __('Import completed. %d succeeded, %d failed.', POST_IMPORTER_NAME ),
                count($results['success']),
                count($results['failed'])
            ),
            'results' => $results
        ));

        } catch (Exception $e) {
            ob_end_clean();
            wp_send_json_error(array(
                'message' => 'An error occurred: ' . $e->getMessage()
            ));
        } catch (Error $e) {
            ob_end_clean();
            wp_send_json_error(array(
                'message' => 'A fatal error occurred: ' . $e->getMessage()
            ));
        }
    }

    /**
     * Handle single file processing (for progress tracking)
     */
    public static function handle_process_file() {
        // Verify nonce
        check_ajax_referer('post_importer_import_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', POST_IMPORTER_NAME )
            ));
        }

        // Get file index and options
        $file_index = isset($_POST['file_index']) ? absint($_POST['file_index']) : 0;
        $options = isset($_POST['options']) ? $_POST['options'] : array();

        // This would be used for processing individual files with progress updates
        // For now, we'll use the batch import method above

        wp_send_json_success(array(
            'message' => __('File processed successfully', POST_IMPORTER_NAME )
        ));
    }

    /**
     * Handle file preview AJAX request
     */
    public static function handle_preview() {
        // Verify nonce
        check_ajax_referer('post_importer_import_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', POST_IMPORTER_NAME )
            ));
        }

        // Check if file was uploaded
        if (empty($_FILES['preview_file'])) {
            wp_send_json_error(array(
                'message' => __('No file was uploaded for preview.', POST_IMPORTER_NAME )
            ));
        }

        $file = $_FILES['preview_file'];

        // Validate file
        $validation = POST_IMPORTER_Content_Extractor::validate_file($file);

        if (is_wp_error($validation)) {
            wp_send_json_error(array(
                'message' => $validation->get_error_message()
            ));
        }

        // Extract content from file
        $extracted = POST_IMPORTER_Content_Extractor::extract_from_file($file['tmp_name']);

        if (is_wp_error($extracted)) {
            wp_send_json_error(array(
                'message' => $extracted->get_error_message()
            ));
        }

        // Truncate content for preview (first 500 characters)
        $content_preview = $extracted['content'];
        if (strlen($content_preview) > 500) {
            $content_preview = substr($content_preview, 0, 500) . '...';
        }

        // Send success response with extracted data
        wp_send_json_success(array(
            'title' => $extracted['title'],
            'content' => $content_preview,
            'content_full' => strlen($extracted['content']) . ' characters',
            'date' => $extracted['date'] ? date('F j, Y', strtotime($extracted['date'])) : __('Not found', POST_IMPORTER_NAME ),
            'first_image' => !empty($extracted['first_image']) ? $extracted['first_image'] : __('Not found', POST_IMPORTER_NAME ),
            'file_name' => $extracted['file_name']
        ));
    }

    /**
     * Recursively delete a temporary directory
     */
    private static function cleanup_temp_dir($dir) {
        if (!is_dir($dir)) {
            return;
        }
        $items = array_diff((array) @scandir($dir), array('.', '..'));
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? self::cleanup_temp_dir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * Phase 2: accept uploaded image/doc files, match them to posts that have
     * unresolved pending media, upload to the WP media library, and update content.
     */
    public static function handle_upload_media() {
        ob_start();
        $temp_dir = null;

        try {
            check_ajax_referer('post_importer_import_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                ob_end_clean();
                wp_send_json_error(array('message' => __('You do not have permission to perform this action.', POST_IMPORTER_NAME)));
            }

            if (empty($_FILES['media_files']['name'][0])) {
                ob_end_clean();
                wp_send_json_error(array('message' => __('No files were uploaded.', POST_IMPORTER_NAME)));
            }

            $upload_dir = wp_upload_dir();
            $temp_dir   = $upload_dir['basedir'] . '/pi-temp/' . uniqid();
            wp_mkdir_p($temp_dir);

            $files         = $_FILES['media_files'];
            $files_by_name = array();

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                $filename                  = sanitize_file_name(basename($files['name'][$i]));
                $target                    = $temp_dir . '/' . $filename;
                move_uploaded_file($files['tmp_name'][$i], $target);
                $files_by_name[$filename]  = $target;
            }

            @set_time_limit(300);

            $results = POST_IMPORTER_Importer::process_pending_media($files_by_name);

            self::cleanup_temp_dir($temp_dir);
            $temp_dir = null;

            ob_end_clean();

            wp_send_json_success(array(
                'message' => sprintf(
                    __('%d image(s) and %d document(s) processed across %d post(s).', POST_IMPORTER_NAME),
                    $results['images_updated'],
                    $results['docs_updated'],
                    $results['posts_updated']
                ),
                'results' => $results,
            ));

        } catch (Exception $e) {
            if ($temp_dir) self::cleanup_temp_dir($temp_dir);
            ob_end_clean();
            wp_send_json_error(array('message' => 'Exception: ' . $e->getMessage()));
        } catch (Error $e) {
            if ($temp_dir) self::cleanup_temp_dir($temp_dir);
            ob_end_clean();
            wp_send_json_error(array('message' => 'Fatal error: ' . $e->getMessage()));
        }
    }

}

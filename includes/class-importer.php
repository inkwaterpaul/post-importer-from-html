<?php
/**
 * Importer Class
 * Handles creating WordPress posts from extracted HTML content
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class HPI_Importer {

    /**
     * Import a single HTML file as a post
     *
     * @param string $file_path Path to HTML file
     * @param array $options Import options (post_status, post_author, post_category)
     * @return array|WP_Error Result array or WP_Error on failure
     */
    public static function import_file($file_path, $options = array()) {
        try {
            // Default options
            $defaults = array(
                'post_status' => 'draft',
                'post_author' => get_current_user_id(),
                'post_category' => array()
            );

            $options = wp_parse_args($options, $defaults);

            // Extract content from HTML file
            $extracted = HPI_Content_Extractor::extract_from_file($file_path);

            if (is_wp_error($extracted)) {
                return $extracted;
            }

        // Prepare post data
        $post_data = array(
            'post_title'    => wp_strip_all_tags($extracted['title']),
            'post_content'  => $extracted['content'],
            'post_status'   => $options['post_status'],
            'post_author'   => $options['post_author'],
            'post_type'     => 'post',
            'edit_date'     => true, // Tell WordPress we're setting the date manually
        );

        // Add post date if extracted
        if (!empty($extracted['date'])) {
            $post_data['post_date'] = $extracted['date'];
            $post_data['post_date_gmt'] = get_gmt_from_date($extracted['date']);
        }

        // Add category if provided
        if (!empty($options['post_category']) && $options['post_category'] != '0') {
            $post_data['post_category'] = array((int) $options['post_category']);
        }

        // Insert the post
        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Store original filename as post meta
        update_post_meta($post_id, '_hpi_original_filename', $extracted['file_name']);
        update_post_meta($post_id, '_hpi_import_date', current_time('mysql'));
        if (!empty($extracted['date'])) {
            update_post_meta($post_id, '_hpi_extracted_date', $extracted['date']);
        }

        // Handle featured image if available
        $featured_image_id = null;
        if (!empty($extracted['first_image']) && !empty($options['images_folder'])) {
            $featured_image_id = self::set_featured_image($post_id, $extracted['first_image'], $options['images_folder']);
        }

        return array(
            'success' => true,
            'post_id' => $post_id,
            'post_title' => $extracted['title'],
            'post_date' => !empty($extracted['date']) ? $extracted['date'] : 'Not found',
            'featured_image' => $featured_image_id ? 'Set' : 'Not found',
            'file_name' => $extracted['file_name'],
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'view_url' => get_permalink($post_id)
        );

        } catch (Exception $e) {
            return new WP_Error('import_exception', 'Exception: ' . $e->getMessage());
        } catch (Error $e) {
            return new WP_Error('import_error', 'Fatal error: ' . $e->getMessage());
        }
    }

    /**
     * Import multiple HTML files
     *
     * @param array $files Array of file paths
     * @param array $options Import options
     * @return array Results array with success/failure for each file
     */
    public static function import_multiple_files($files, $options = array()) {
        $results = array(
            'success' => array(),
            'failed' => array(),
            'total' => count($files),
            'success_count' => 0,
            'failed_count' => 0
        );

        foreach ($files as $file_path) {
            $result = self::import_file($file_path, $options);

            if (is_wp_error($result)) {
                $results['failed'][] = array(
                    'file' => basename($file_path),
                    'error' => $result->get_error_message()
                );
                $results['failed_count']++;
            } else {
                $results['success'][] = $result;
                $results['success_count']++;
            }
        }

        return $results;
    }

    /**
     * Check if a post with the same title already exists
     *
     * @param string $title Post title
     * @return bool True if exists, false otherwise
     */
    public static function post_exists($title) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = 'post' AND post_status != 'trash' LIMIT 1",
            $title
        );

        $result = $wpdb->get_var($query);

        return !empty($result);
    }

    /**
     * Get import statistics
     *
     * @return array Statistics array
     */
    public static function get_import_stats() {
        global $wpdb;

        $total_imported = $wpdb->get_var(
            "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = '_hpi_import_date'"
        );

        $recent_imports = $wpdb->get_results(
            "SELECT p.ID, p.post_title, pm.meta_value as import_date
            FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id
            WHERE pm.meta_key = '_hpi_import_date'
            ORDER BY pm.meta_value DESC
            LIMIT 10"
        );

        return array(
            'total' => (int) $total_imported,
            'recent' => $recent_imports
        );
    }

    /**
     * Set featured image for a post
     *
     * @param int $post_id Post ID
     * @param string $image_filename Image filename
     * @param string $images_folder Path to images folder
     * @return int|bool Attachment ID or false on failure
     */
    private static function set_featured_image($post_id, $image_filename, $images_folder) {
        // Ensure folder path ends with slash
        $images_folder = rtrim($images_folder, '/') . '/';

        // Build full path to image
        $image_path = $images_folder . $image_filename;

        // Check if file exists
        if (!file_exists($image_path)) {
            return false;
        }

        // Check if this image is already in the media library
        $existing = self::get_attachment_by_filename($image_filename);
        if ($existing) {
            set_post_thumbnail($post_id, $existing);
            return $existing;
        }

        // Upload image to WordPress media library
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Get the file type
        $filetype = wp_check_filetype($image_filename);

        // Prepare upload
        $upload_dir = wp_upload_dir();
        $new_filename = wp_unique_filename($upload_dir['path'], $image_filename);
        $new_file_path = $upload_dir['path'] . '/' . $new_filename;

        // Copy file to uploads directory
        if (!copy($image_path, $new_file_path)) {
            return false;
        }

        // Prepare attachment data
        $attachment = array(
            'guid' => $upload_dir['url'] . '/' . $new_filename,
            'post_mime_type' => $filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $image_filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        // Insert attachment
        $attach_id = wp_insert_attachment($attachment, $new_file_path, $post_id);

        if (!is_wp_error($attach_id)) {
            // Generate metadata
            $attach_data = wp_generate_attachment_metadata($attach_id, $new_file_path);
            wp_update_attachment_metadata($attach_id, $attach_data);

            // Set as featured image
            set_post_thumbnail($post_id, $attach_id);

            return $attach_id;
        }

        return false;
    }

    /**
     * Get attachment ID by filename
     *
     * @param string $filename Filename
     * @return int|bool Attachment ID or false
     */
    private static function get_attachment_by_filename($filename) {
        global $wpdb;

        $attachment = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid LIKE %s",
            '%' . $wpdb->esc_like($filename)
        ));

        return $attachment ? (int) $attachment : false;
    }
}

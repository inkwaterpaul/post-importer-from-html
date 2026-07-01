<?php
/**
 * Importer Class
 * Handles creating WordPress posts from extracted HTML content
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class POST_IMPORTER_Importer {

    /**
     * Import a single HTML file as a post
     *
     * @param string $file_path Path to HTML file
     * @param array $options Import options (post_status, category_id)
     * @return array|WP_Error Result array or WP_Error on failure
     */
    public static function import_file($file_path, $options = array()) {
        try {
            // Default options
            $defaults = array(
                'post_status' => 'draft',
                'category_id' => 0,
                'images_folder' => '',
                'documents_folder' => '',
                'source_file_path' => ''
            );

            $options = wp_parse_args($options, $defaults);

            // Extract content from HTML file, converting it to WordPress blocks
            $extracted = POST_IMPORTER_Content_Extractor::extract_from_file($file_path, true);

            if (is_wp_error($extracted)) {
                return $extracted;
            }

            $content = $extracted['content'];

        // Prepare post data
        $post_data = array(
            'post_title'    => wp_strip_all_tags($extracted['title']),
            'post_content'  => $content,
            'post_status'   => $options['post_status'],
            'post_author'   => get_current_user_id(),
            'post_type'     => 'post'
        );

        // Use the date extracted from the HTML, if one was found, instead of the current time
        if (!empty($extracted['date'])) {
            $post_data['post_date']     = $extracted['date'];
            $post_data['post_date_gmt'] = get_gmt_from_date($extracted['date']);
        }

        // Assign category if provided
        if (!empty($options['category_id'])) {
            $post_data['post_category'] = array((int) $options['category_id']);
        }

        // Insert the post
        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Store original filename as post meta
        update_post_meta($post_id, '_pi_original_filename', $extracted['file_name']);
        update_post_meta($post_id, '_pi_import_date', current_time('mysql'));

        // Handle featured image if available
        $featured_image_id = null;
        if (!empty($extracted['first_image'])) {
            if (!empty($options['images_folder'])) {
                $featured_image_id = self::set_featured_image($post_id, $extracted['first_image'], $options['images_folder']);
            } elseif (!empty($options['source_file_path'])) {
                // Resolve first image relative to the source HTML file
                $source_dir = dirname($options['source_file_path']);
                // extract_first_image returns a bare filename; look for it alongside the HTML file
                $resolved = realpath($source_dir . '/' . $extracted['first_image']);
                if ($resolved && is_file($resolved)) {
                    $attach_id = self::upload_image($resolved, $post_id);
                    if ($attach_id) {
                        set_post_thumbnail($post_id, $attach_id);
                        $featured_image_id = $attach_id;
                    }
                }
            }
        }

        // Handle all image URLs in content - either from images folder or resolved relative to source file
        if (!empty($options['images_folder']) || !empty($options['source_file_path'])) {
            self::update_image_urls($post_id, $options['images_folder'] ?? '', $options['source_file_path'] ?? '');
        }

        // Handle document URL replacements if documents folder is provided
        if (!empty($options['documents_folder'])) {
            self::update_document_urls($post_id, $options['documents_folder']);
        }

        // Store any still-unresolved image/doc references as pending for Phase 2 upload
        $pending = self::save_pending_media($post_id);

        return array(
            'success'        => true,
            'post_id'        => $post_id,
            'post_title'     => $extracted['title'],
            'featured_image' => $featured_image_id ? 'Set' : 'Not found',
            'file_name'      => $extracted['file_name'],
            'edit_url'       => get_edit_post_link($post_id, 'raw'),
            'view_url'       => get_permalink($post_id),
            'pending_images' => $pending['images'],
            'pending_docs'   => $pending['docs'],
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
     * Set featured image for a post
     *
     * @param int $post_id Post ID
     * @param string $image_filename Image filename
     * @param string $images_folder Path to images folder(s) - can be comma-separated
     * @return int|bool Attachment ID or false on failure
     */
    private static function set_featured_image($post_id, $image_filename, $images_folder) {
        // Split by comma if multiple folders provided
        $folders = array_map('trim', explode(',', $images_folder));

        $image_path = null;

        // Search for image in all specified folders
        foreach ($folders as $folder) {
            $folder = rtrim($folder, '/') . '/';
            $test_path = $folder . $image_filename;

            if (file_exists($test_path)) {
                $image_path = $test_path;
                break;
            }
        }

        // Check if file exists in any folder
        if (!$image_path) {
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

    /**
     * Update image URLs in page content
     * Finds images and uploads them to media library, then updates URLs.
     * Resolves relative URLs against $source_file_path when available.
     *
     * @param int $post_id Post ID
     * @param string $images_folder Path to images folder(s) - can be comma-separated
     * @param string $source_file_path Absolute path to the source HTML file (enables relative URL resolution)
     * @return void
     */
    private static function update_image_urls($post_id, $images_folder = '', $source_file_path = '') {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        $content = $post->post_content;

        // Split by comma if multiple folders provided
        $folders = !empty($images_folder) ? array_map('trim', explode(',', $images_folder)) : array();
        $source_dir = !empty($source_file_path) ? dirname($source_file_path) : '';

        // Find all image sources in content
        preg_match_all('/<img[^>]+src="([^"]+)"[^>]*>/i', $content, $matches);

        if (empty($matches[1])) {
            return;
        }

        $replacements = array();

        foreach ($matches[1] as $img_src) {
            // Skip absolute URLs
            if (preg_match('#^https?://#', $img_src)) {
                continue;
            }

            $img_src_decoded = urldecode($img_src);
            $image_path = null;

            // First: try resolving relative to the source HTML file's directory
            if (!empty($source_dir)) {
                $path_without_qs = preg_replace('/\?.*$/', '', $img_src_decoded);
                $resolved = realpath($source_dir . '/' . $path_without_qs);
                if ($resolved && file_exists($resolved) && is_file($resolved)) {
                    $image_path = $resolved;
                }
            }

            // Fallback: search in the specified images folder(s) by filename
            if (!$image_path && !empty($folders)) {
                $filename = preg_replace('/\?.*$/', '', basename($img_src_decoded));
                foreach ($folders as $folder) {
                    $folder = rtrim($folder, '/') . '/';
                    $test_path = $folder . $filename;
                    if (file_exists($test_path)) {
                        $image_path = $test_path;
                        break;
                    }
                }
            }

            if (!$image_path) {
                continue;
            }

            // Upload image to media library
            $attachment_id = self::upload_image($image_path, $post_id);

            if ($attachment_id) {
                $new_url = wp_get_attachment_url($attachment_id);
                $replacements[$img_src] = $new_url;
            }
        }

        // Replace URLs in content
        if (!empty($replacements)) {
            foreach ($replacements as $old_url => $new_url) {
                $content = str_replace('src="' . $old_url . '"', 'src="' . $new_url . '"', $content);
            }

            // Update page content
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $content
            ));
        }
    }

    /**
     * Upload image to WordPress media library
     *
     * @param string $file_path Full path to image
     * @param int $post_id Post ID
     * @return int|bool Attachment ID or false on failure
     */
    private static function upload_image($file_path, $post_id) {
        $filename = basename($file_path);

        // Check if this image is already in the media library
        $existing = self::get_attachment_by_filename($filename);
        if ($existing) {
            return $existing;
        }

        // Upload image to WordPress media library
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Get the file type
        $filetype = wp_check_filetype($filename);

        // Prepare upload
        $upload_dir = wp_upload_dir();
        $new_filename = wp_unique_filename($upload_dir['path'], $filename);
        $new_file_path = $upload_dir['path'] . '/' . $new_filename;

        // Copy file to uploads directory
        if (!copy($file_path, $new_file_path)) {
            return false;
        }

        // Prepare attachment data
        $attachment = array(
            'guid' => $upload_dir['url'] . '/' . $new_filename,
            'post_mime_type' => $filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        // Insert attachment
        $attach_id = wp_insert_attachment($attachment, $new_file_path, $post_id);

        if (!is_wp_error($attach_id)) {
            // Generate metadata
            $attach_data = wp_generate_attachment_metadata($attach_id, $new_file_path);
            wp_update_attachment_metadata($attach_id, $attach_data);

            return $attach_id;
        }

        return false;
    }

    /**
     * Update document URLs in page content
     * Finds document links and uploads them to media library, then updates URLs
     *
     * @param int $post_id Post ID
     * @param string $documents_folder Path to documents folder
     * @return void
     */
    private static function update_document_urls($post_id, $documents_folder) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        $content = $post->post_content;
        $documents_folder = rtrim($documents_folder, '/') . '/';

        // Document extensions to look for
        $doc_extensions = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'txt', 'csv');
        $pattern = '/href="([^"]+\.(' . implode('|', $doc_extensions) . '))"/i';

        // Find all document links
        preg_match_all($pattern, $content, $matches);

        if (empty($matches[1])) {
            return;
        }

        $replacements = array();

        foreach ($matches[1] as $doc_url) {
            // Extract filename from URL
            $filename = basename($doc_url);
            $doc_path = $documents_folder . $filename;

            // Check if file exists
            if (!file_exists($doc_path)) {
                continue;
            }

            // Upload document to media library
            $attachment_id = self::upload_document($doc_path, $post_id);

            if ($attachment_id) {
                $new_url = wp_get_attachment_url($attachment_id);
                $replacements[$doc_url] = $new_url;
            }
        }

        // Replace URLs in content
        if (!empty($replacements)) {
            foreach ($replacements as $old_url => $new_url) {
                $content = str_replace('href="' . $old_url . '"', 'href="' . $new_url . '"', $content);
            }

            // Update page content
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $content
            ));
        }
    }

    /**
     * Upload document to WordPress media library
     *
     * @param string $file_path Full path to document
     * @param int $post_id Post ID
     * @return int|bool Attachment ID or false on failure
     */
    private static function upload_document($file_path, $post_id) {
        $filename = basename($file_path);

        // Check if this document is already in the media library
        $existing = self::get_attachment_by_filename($filename);
        if ($existing) {
            return $existing;
        }

        // Upload document to WordPress media library
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Get the file type
        $filetype = wp_check_filetype($filename);

        // Prepare upload
        $upload_dir = wp_upload_dir();
        $new_filename = wp_unique_filename($upload_dir['path'], $filename);
        $new_file_path = $upload_dir['path'] . '/' . $new_filename;

        // Copy file to uploads directory
        if (!copy($file_path, $new_file_path)) {
            return false;
        }

        // Prepare attachment data
        $attachment = array(
            'guid' => $upload_dir['url'] . '/' . $new_filename,
            'post_mime_type' => $filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        // Insert attachment
        $attach_id = wp_insert_attachment($attachment, $new_file_path, $post_id);

        if (!is_wp_error($attach_id)) {
            // Generate metadata (not needed for documents but good practice)
            $attach_data = wp_generate_attachment_metadata($attach_id, $new_file_path);
            wp_update_attachment_metadata($attach_id, $attach_data);

            return $attach_id;
        }

        return false;
    }

    /**
     * Scan the current saved content of a page for any remaining non-absolute image
     * src and document href values, and store them as post meta so Phase 2 can resolve them.
     *
     * Returns ['images' => [...filenames...], 'docs' => [...filenames...]]
     */
    public static function save_pending_media($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array('images' => array(), 'docs' => array());
        }
        $content = $post->post_content;

        // Unresolved images: src values that are not absolute URLs
        $pending_images = array();
        preg_match_all('/<img[^>]+src="([^"]+)"[^>]*>/i', $content, $matches);
        foreach ($matches[1] as $src) {
            if (preg_match('#^https?://#', $src)) {
                continue;
            }
            $filename = basename(preg_replace('/\?.*$/', '', urldecode($src)));
            if ($filename) {
                $pending_images[] = array('filename' => $filename, 'original_src' => $src);
            }
        }

        // Unresolved docs: href values with document extensions that are not absolute URLs
        $doc_ext = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'txt', 'csv');
        $pending_docs = array();
        preg_match_all('/href="([^"]+\.(' . implode('|', $doc_ext) . '))"/i', $content, $matches);
        foreach ($matches[1] as $href) {
            if (preg_match('#^https?://#', $href)) {
                continue;
            }
            $filename = basename(preg_replace('/\?.*$/', '', urldecode($href)));
            if ($filename) {
                $pending_docs[] = array('filename' => $filename, 'original_href' => $href);
            }
        }

        if (!empty($pending_images)) {
            update_post_meta($post_id, '_pi_pending_images', $pending_images);
        } else {
            delete_post_meta($post_id, '_pi_pending_images');
        }

        if (!empty($pending_docs)) {
            update_post_meta($post_id, '_pi_pending_docs', $pending_docs);
        } else {
            delete_post_meta($post_id, '_pi_pending_docs');
        }

        return array(
            'images' => array_values(array_unique(array_column($pending_images, 'filename'))),
            'docs'   => array_values(array_unique(array_column($pending_docs, 'filename'))),
        );
    }

    /**
     * Phase 2: match uploaded files to pages with pending media, upload to media
     * library, and update page content with the new WP URLs.
     *
     * $files_by_name = [ 'photo.jpg' => '/server/path/to/photo.jpg', ... ]
     */
    public static function process_pending_media($files_by_name) {
        $results = array(
            'images_updated' => 0,
            'docs_updated'   => 0,
            'posts_updated'  => 0,
            'still_missing'  => array(),
        );

        $posts = get_posts(array(
            'post_type'      => 'post',
            'post_status'    => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                'relation' => 'OR',
                array('key' => '_pi_pending_images', 'compare' => 'EXISTS'),
                array('key' => '_pi_pending_docs',   'compare' => 'EXISTS'),
            ),
        ));

        // Cache uploaded attachments so the same file is only sent to WP media once
        $attach_cache = array();

        foreach ($posts as $post_id) {
            $post    = get_post($post_id);
            $content = $post->post_content;
            $changed = false;

            // --- Images ---
            $pending_images = get_post_meta($post_id, '_pi_pending_images', true);
            if (!empty($pending_images) && is_array($pending_images)) {
                $still_pending  = array();
                $first_image_id = null;

                foreach ($pending_images as $item) {
                    $filename = $item['filename'];
                    $orig_src = $item['original_src'];

                    if (isset($files_by_name[$filename])) {
                        if (!isset($attach_cache[$filename])) {
                            $attach_cache[$filename] = self::upload_image($files_by_name[$filename], $post_id);
                        }
                        $attach_id = $attach_cache[$filename];
                        if ($attach_id) {
                            $new_url  = wp_get_attachment_url($attach_id);
                            $content  = str_replace('src="' . $orig_src . '"', 'src="' . $new_url . '"', $content);
                            $changed  = true;
                            $results['images_updated']++;
                            if ($first_image_id === null) {
                                $first_image_id = $attach_id;
                            }
                        } else {
                            $still_pending[]            = $item;
                            $results['still_missing'][] = $filename;
                        }
                    } else {
                        $still_pending[]            = $item;
                        $results['still_missing'][] = $filename;
                    }
                }

                if (empty($still_pending)) {
                    delete_post_meta($post_id, '_pi_pending_images');
                } else {
                    update_post_meta($post_id, '_pi_pending_images', $still_pending);
                }

                if ($first_image_id && !has_post_thumbnail($post_id)) {
                    set_post_thumbnail($post_id, $first_image_id);
                }
            }

            // --- Documents ---
            $pending_docs = get_post_meta($post_id, '_pi_pending_docs', true);
            if (!empty($pending_docs) && is_array($pending_docs)) {
                $still_pending = array();

                foreach ($pending_docs as $item) {
                    $filename  = $item['filename'];
                    $orig_href = $item['original_href'];

                    if (isset($files_by_name[$filename])) {
                        if (!isset($attach_cache[$filename])) {
                            $attach_cache[$filename] = self::upload_document($files_by_name[$filename], $post_id);
                        }
                        $attach_id = $attach_cache[$filename];
                        if ($attach_id) {
                            $new_url  = wp_get_attachment_url($attach_id);
                            $content  = str_replace('href="' . $orig_href . '"', 'href="' . $new_url . '"', $content);
                            $changed  = true;
                            $results['docs_updated']++;
                        } else {
                            $still_pending[]            = $item;
                            $results['still_missing'][] = $filename;
                        }
                    } else {
                        $still_pending[]            = $item;
                        $results['still_missing'][] = $filename;
                    }
                }

                if (empty($still_pending)) {
                    delete_post_meta($post_id, '_pi_pending_docs');
                } else {
                    update_post_meta($post_id, '_pi_pending_docs', $still_pending);
                }
            }

            if ($changed) {
                wp_update_post(array('ID' => $post_id, 'post_content' => $content));
                $results['posts_updated']++;
            }
        }

        $results['still_missing'] = array_values(array_unique($results['still_missing']));

        return $results;
    }
}

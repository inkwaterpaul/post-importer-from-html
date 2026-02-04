<?php
/**
 * Admin UI Class
 * Handles the admin interface for file selection and import
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class HPI_Admin_UI {

    /**
     * Render the main admin page
     */
    public static function render_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'html-post-importer'));
        }

        ?>
        <div class="wrap hpi-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="hpi-container">
                <div class="hpi-card">
                    <h2><?php _e('Import HTML Files as Posts', 'html-post-importer'); ?></h2>
                    <p class="description">
                        <?php _e('Select one or more HTML files to import as WordPress posts. The importer will extract:', 'html-post-importer'); ?>
                    </p>
                    <ul class="hpi-features">
                        <li><?php _e('Title from <code>&lt;h1&gt;</code> tag', 'html-post-importer'); ?></li>
                        <li><?php _e('Content from <code>&lt;div class="page-content"&gt;</code>', 'html-post-importer'); ?></li>
                        <li><?php _e('Post date from <code>&lt;small&gt;</code> tag', 'html-post-importer'); ?></li>
                    </ul>

                    <form id="hpi-import-form" enctype="multipart/form-data">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="hpi-files"><?php _e('Select HTML Files', 'html-post-importer'); ?></label>
                                </th>
                                <td>
                                    <input type="file"
                                           id="hpi-files"
                                           name="hpi_files[]"
                                           accept=".html,.htm"
                                           multiple
                                           required>
                                    <p class="description">
                                        <?php _e('You can select multiple HTML files at once. Files are processed in batches of 10 to handle large imports.', 'html-post-importer'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="hpi-post-status"><?php _e('Post Status', 'html-post-importer'); ?></label>
                                </th>
                                <td>
                                    <select id="hpi-post-status" name="post_status">
                                        <option value="draft"><?php _e('Draft', 'html-post-importer'); ?></option>
                                        <option value="publish"><?php _e('Published', 'html-post-importer'); ?></option>
                                        <option value="pending"><?php _e('Pending Review', 'html-post-importer'); ?></option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="hpi-post-author"><?php _e('Post Author', 'html-post-importer'); ?></label>
                                </th>
                                <td>
                                    <?php
                                    wp_dropdown_users(array(
                                        'name' => 'post_author',
                                        'id' => 'hpi-post-author',
                                        'selected' => get_current_user_id(),
                                        'who' => 'authors'
                                    ));
                                    ?>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="hpi-post-category"><?php _e('Post Category', 'html-post-importer'); ?></label>
                                </th>
                                <td>
                                    <?php
                                    wp_dropdown_categories(array(
                                        'name' => 'post_category',
                                        'id' => 'hpi-post-category',
                                        'hide_empty' => false,
                                        'hierarchical' => true,
                                        'show_option_none' => __('Select Category', 'html-post-importer'),
                                        'option_none_value' => '0'
                                    ));
                                    ?>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="hpi-images-folder"><?php _e('Images Folder (Optional)', 'html-post-importer'); ?></label>
                                </th>
                                <td>
                                    <input type="text"
                                           id="hpi-images-folder"
                                           name="images_folder"
                                           class="regular-text"
                                           placeholder="/path/to/images/folder">
                                    <button type="button" id="hpi-browse-folder" class="button"><?php _e('Browse', 'html-post-importer'); ?></button>
                                    <p class="description">
                                        <?php _e('Select the folder containing images. The first image from each HTML file will be set as the featured image.', 'html-post-importer'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary button-large" id="hpi-import-btn">
                                <span class="dashicons dashicons-upload"></span>
                                <?php _e('Import Files', 'html-post-importer'); ?>
                            </button>
                        </p>
                    </form>

                    <div id="hpi-preview" class="hpi-card hpi-preview" style="display: none;">
                        <h3><?php _e('Preview - First File', 'html-post-importer'); ?></h3>
                        <div id="hpi-preview-content">
                            <div class="hpi-preview-loading">
                                <span class="spinner is-active"></span>
                                <p><?php _e('Loading preview...', 'html-post-importer'); ?></p>
                            </div>
                        </div>
                    </div>

                    <div id="hpi-progress" class="hpi-progress" style="display: none;">
                        <h3><?php _e('Import Progress', 'html-post-importer'); ?></h3>
                        <div class="hpi-progress-bar">
                            <div class="hpi-progress-bar-fill" id="hpi-progress-bar"></div>
                        </div>
                        <p class="hpi-progress-text" id="hpi-progress-text">0%</p>
                    </div>

                    <div id="hpi-results" class="hpi-results" style="display: none;">
                        <h3><?php _e('Import Results', 'html-post-importer'); ?></h3>
                        <div id="hpi-results-content"></div>
                    </div>
                </div>

                <div class="hpi-sidebar">
                    <div class="hpi-card">
                        <h3><?php _e('Instructions', 'html-post-importer'); ?></h3>
                        <ol>
                            <li><?php _e('Click "Select HTML Files" to choose files from your computer', 'html-post-importer'); ?></li>
                            <li><?php _e('Configure post settings (status, author, category)', 'html-post-importer'); ?></li>
                            <li><?php _e('Click "Import Files" to start the import process', 'html-post-importer'); ?></li>
                            <li><?php _e('Wait for the import to complete', 'html-post-importer'); ?></li>
                        </ol>
                    </div>

                    <div class="hpi-card">
                        <h3><?php _e('Tips', 'html-post-importer'); ?></h3>
                        <ul>
                            <li><?php _e('HTML files must contain an &lt;h1&gt; tag for the title', 'html-post-importer'); ?></li>
                            <li><?php _e('Content should be within a &lt;div class="page-content"&gt;', 'html-post-importer'); ?></li>
                            <li><?php _e('Date should be in a &lt;small&gt; tag', 'html-post-importer'); ?></li>
                            <li><?php _e('Import as drafts first to review before publishing', 'html-post-importer'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Folder Browser Modal -->
        <div id="hpi-folder-browser-modal" class="hpi-modal" style="display: none;">
            <div class="hpi-modal-content">
                <div class="hpi-modal-header">
                    <h2><?php _e('Select Images Folder', 'html-post-importer'); ?></h2>
                    <button type="button" class="hpi-modal-close">&times;</button>
                </div>
                <div class="hpi-modal-body">
                    <div class="hpi-folder-path">
                        <strong><?php _e('Current Path:', 'html-post-importer'); ?></strong>
                        <span id="hpi-current-path">/</span>
                    </div>
                    <div class="hpi-folder-list" id="hpi-folder-list">
                        <div class="hpi-folder-loading">
                            <span class="spinner is-active"></span>
                            <p><?php _e('Loading folders...', 'html-post-importer'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="hpi-modal-footer">
                    <button type="button" class="button button-secondary" id="hpi-folder-cancel"><?php _e('Cancel', 'html-post-importer'); ?></button>
                    <button type="button" class="button button-primary" id="hpi-folder-select"><?php _e('Select This Folder', 'html-post-importer'); ?></button>
                </div>
            </div>
        </div>
     <?php
    }
}

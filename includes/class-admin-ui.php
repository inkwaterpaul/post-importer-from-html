<?php
/**
 * Admin UI Class
 * Handles the admin interface for file selection and import
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class POST_IMPORTER_Admin_UI {

    /**
     * Render the main admin page
     */
    public static function render_page() {

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', POST_IMPORTER_NAME));
        }

        ?>
        <div class="wrap pi-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="pi-container">
                <div class="pi-card">

                    <h2><?php _e('Import HTML Files as Posts', POST_IMPORTER_NAME); ?></h2>
                    <p class="description">
                        <?php _e('Select one or more HTML files to import as WordPress posts. The importer will extract:', POST_IMPORTER_NAME); ?>
                    </p>
                    <ul class="pi-features">
                        <li><?php _e('Title from <code>&lt;h1&gt;</code> tag', POST_IMPORTER_NAME); ?></li>
                        <li><?php _e('Content from <code>&lt;div class="page-content"&gt;</code>', POST_IMPORTER_NAME); ?></li>
                        <li><?php _e('Date from <code>&lt;small&gt;</code> tag', POST_IMPORTER_NAME); ?></li>
                        <li><?php _e('Featured image (the first image in the content) and any other images &mdash; uploaded in a second step after import', POST_IMPORTER_NAME); ?></li>
                    </ul>

                    <form id="pi-import-form" enctype="multipart/form-data">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="pi-files"><?php _e('Select HTML Files', POST_IMPORTER_NAME); ?></label>
                                </th>
                                <td>
                                    <input type="file"
                                           id="pi-files"
                                           name="pi_files[]"
                                           accept=".html,.htm"
                                           multiple
                                           required>
                                    <p class="description">
                                        <?php _e('You can select multiple HTML files at once. Files are processed in batches of 10.', POST_IMPORTER_NAME); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="pi-post-status"><?php _e('Post Status', POST_IMPORTER_NAME); ?></label>
                                </th>
                                <td>
                                    <select id="pi-post-status" name="post_status">
                                        <option value="draft"><?php _e('Draft', POST_IMPORTER_NAME); ?></option>
                                        <option value="publish"><?php _e('Published', POST_IMPORTER_NAME); ?></option>
                                        <option value="pending"><?php _e('Pending Review', POST_IMPORTER_NAME); ?></option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="pi-category"><?php _e('Category', POST_IMPORTER_NAME); ?></label>
                                </th>
                                <td>
                                    <?php
                                    wp_dropdown_categories(array(
                                        'name'             => 'category_id',
                                        'id'               => 'pi-category',
                                        'show_option_none' => __('None (Uncategorized)', POST_IMPORTER_NAME),
                                        'option_none_value' => '0',
                                        'hide_empty'       => false,
                                        'selected'         => 0
                                    ));
                                    ?>
                                    <p class="description">
                                        <?php _e('Optionally assign all imported posts to a category.', POST_IMPORTER_NAME); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary button-large" id="pi-import-btn">
                                <span class="dashicons dashicons-upload"></span>
                                <?php _e('Import Files', POST_IMPORTER_NAME); ?>
                            </button>
                        </p>
                    </form>

                    <div id="pi-preview" class="pi-card pi-preview" style="display: none;">
                        <h3><?php _e('Preview - First File', POST_IMPORTER_NAME); ?></h3>
                        <div id="pi-preview-content">
                            <div class="pi-preview-loading">
                                <span class="spinner is-active"></span>
                                <p><?php _e('Loading preview...', POST_IMPORTER_NAME); ?></p>
                            </div>
                        </div>
                    </div>

                    <div id="pi-progress" class="pi-progress" style="display: none;">
                        <h3><?php _e('Import Progress', POST_IMPORTER_NAME); ?></h3>
                        <div class="pi-progress-bar">
                            <div class="pi-progress-bar-fill" id="pi-progress-bar"></div>
                        </div>
                        <p class="pi-progress-text" id="pi-progress-text">0%</p>
                    </div>

                    <div id="pi-results" class="pi-results" style="display: none;">
                        <h3><?php _e('Import Results', POST_IMPORTER_NAME); ?></h3>
                        <div id="pi-results-content"></div>
                    </div>

                </div>

                <div class="pi-sidebar">
                    <div class="pi-card">
                        <h3><?php _e('How It Works', POST_IMPORTER_NAME); ?></h3>
                        <ol>
                            <li><?php _e('Select one or more HTML files', POST_IMPORTER_NAME); ?></li>
                            <li><?php _e('Choose post status and optional category', POST_IMPORTER_NAME); ?></li>
                            <li><?php _e('Click "Import Files" to create the posts (title, content, date)', POST_IMPORTER_NAME); ?></li>
                            <li><?php _e('A list of the images/documents referenced in the content will appear &mdash; select the folder(s) containing them and only the needed files will be uploaded', POST_IMPORTER_NAME); ?></li>
                        </ol>
                    </div>

                    <div class="pi-card">
                        <h3><?php _e('HTML Requirements', POST_IMPORTER_NAME); ?></h3>
                        <ul>
                            <li><?php _e('Must contain an <code>&lt;h1&gt;</code> for the title', POST_IMPORTER_NAME); ?></li>
                            <li><?php _e('Content from <code>&lt;div class="page-content"&gt;</code>', POST_IMPORTER_NAME); ?></li>
                            <li><?php _e('Import as drafts first to review', POST_IMPORTER_NAME); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

     <?php
    }
}

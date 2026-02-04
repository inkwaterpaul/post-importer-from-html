/**
 * HTML Post Importer - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        const $form = $('#hpi-import-form');
        const $fileInput = $('#hpi-files');
        const $submitBtn = $('#hpi-import-btn');
        const $progress = $('#hpi-progress');
        const $progressBar = $('#hpi-progress-bar');
        const $progressText = $('#hpi-progress-text');
        const $results = $('#hpi-results');
        const $resultsContent = $('#hpi-results-content');

        // Handle form submission
        $form.on('submit', function(e) {
            e.preventDefault();

            // Validate file input
            if (!$fileInput[0].files.length) {
                alert(hpiAjax.strings.error + ' Please select at least one file.');
                return;
            }

            const files = Array.from($fileInput[0].files);
            const totalFiles = files.length;
            const batchSize = 10; // Process 10 files at a time
            const batches = [];

            // Split files into batches
            for (let i = 0; i < totalFiles; i += batchSize) {
                batches.push(files.slice(i, i + batchSize));
            }

            // Get import options
            const options = {
                post_status: $('#hpi-post-status').val(),
                post_author: $('#hpi-post-author').val(),
                post_category: $('#hpi-post-category').val(),
                images_folder: $('#hpi-images-folder').val()
            };

            // Disable form
            $submitBtn.prop('disabled', true).html(
                '<span class="dashicons dashicons-upload"></span> ' +
                hpiAjax.strings.processing
            );

            // Show progress
            $progress.show();
            $results.hide();
            updateProgress(0, 0, totalFiles);

            // Aggregate results
            const aggregatedResults = {
                success: [],
                failed: [],
                total: totalFiles
            };

            // Process batches sequentially
            processBatches(batches, 0, options, aggregatedResults, totalFiles);
        });

        // Process batches of files
        function processBatches(batches, currentBatchIndex, options, aggregatedResults, totalFiles) {
            if (currentBatchIndex >= batches.length) {
                // All batches processed
                updateProgress(100, totalFiles, totalFiles);

                // Display final results
                displayResults(aggregatedResults);

                // Reset form
                $form[0].reset();

                // Show success message
                const message = sprintf(
                    'Import completed. %d succeeded, %d failed.',
                    aggregatedResults.success.length,
                    aggregatedResults.failed.length
                );
                showNotice('success', message);

                // Re-enable form
                $submitBtn.prop('disabled', false).html(
                    '<span class="dashicons dashicons-upload"></span> Import Files'
                );

                // Hide progress after a delay
                setTimeout(function() {
                    $progress.fadeOut();
                }, 2000);

                return;
            }

            const batch = batches[currentBatchIndex];
            const batchNumber = currentBatchIndex + 1;
            const totalBatches = batches.length;

            // Update progress text
            const processedFiles = currentBatchIndex * 10;
            updateProgress((processedFiles / totalFiles) * 100, processedFiles, totalFiles, batchNumber, totalBatches);

            // Prepare form data for this batch
            const formData = new FormData();
            formData.append('action', 'hpi_import_files');
            formData.append('nonce', hpiAjax.nonce);
            formData.append('post_status', options.post_status);
            formData.append('post_author', options.post_author);
            formData.append('post_category', options.post_category);
            formData.append('images_folder', options.images_folder);

            // Add files from this batch
            batch.forEach(function(file) {
                formData.append('hpi_files[]', file);
            });

            // Send AJAX request for this batch
            $.ajax({
                url: hpiAjax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success && response.data.results) {
                        // Aggregate results from this batch
                        aggregatedResults.success.push(...response.data.results.success);
                        aggregatedResults.failed.push(...response.data.results.failed);
                    } else {
                        // If batch failed, mark all files in batch as failed
                        batch.forEach(function(file) {
                            aggregatedResults.failed.push({
                                file: file.name,
                                error: response.data.message || 'Batch processing failed'
                            });
                        });
                    }

                    // Process next batch
                    processBatches(batches, currentBatchIndex + 1, options, aggregatedResults, totalFiles);
                },
                error: function(xhr, status, error) {
                    // Try to parse error response
                    let errorMessage = error;

                    if (xhr.responseText) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.data && response.data.message) {
                                errorMessage = response.data.message;
                            }
                        } catch (e) {
                            errorMessage = 'Server error occurred';
                            console.error('Response text:', xhr.responseText.substring(0, 500));
                        }
                    }

                    // Mark all files in this batch as failed
                    batch.forEach(function(file) {
                        aggregatedResults.failed.push({
                            file: file.name,
                            error: errorMessage
                        });
                    });

                    // Continue to next batch despite error
                    processBatches(batches, currentBatchIndex + 1, options, aggregatedResults, totalFiles);
                }
            });
        }

        // Simple sprintf function
        function sprintf(format, ...args) {
            let i = 0;
            return format.replace(/%[sd]/g, () => args[i++]);
        }

        // Update progress bar
        function updateProgress(percent, processed, total, currentBatch, totalBatches) {
            percent = Math.round(percent);
            $progressBar.css('width', percent + '%');

            let progressText = percent + '%';
            if (typeof processed !== 'undefined' && typeof total !== 'undefined') {
                progressText += ' (' + processed + '/' + total + ' files';
                if (typeof currentBatch !== 'undefined' && typeof totalBatches !== 'undefined') {
                    progressText += ', batch ' + currentBatch + '/' + totalBatches;
                }
                progressText += ')';
            }

            $progressText.text(progressText);
        }

        // Display results
        function displayResults(results) {
            $results.show();

            let html = '';

            // Summary
            html += '<div class="hpi-summary">';
            html += '<div class="hpi-summary-item">';
            html += '<span class="hpi-summary-value">' + results.total + '</span>';
            html += '<span class="hpi-summary-label">Total Files</span>';
            html += '</div>';
            html += '<div class="hpi-summary-item">';
            html += '<span class="hpi-summary-value" style="color: #00a32a;">' + results.success.length + '</span>';
            html += '<span class="hpi-summary-label">Successful</span>';
            html += '</div>';
            html += '<div class="hpi-summary-item">';
            html += '<span class="hpi-summary-value" style="color: #d63638;">' + results.failed.length + '</span>';
            html += '<span class="hpi-summary-label">Failed</span>';
            html += '</div>';
            html += '</div>';

            // Successful imports
            if (results.success.length > 0) {
                html += '<h4 class="success-header">✓ Successfully Imported</h4>';
                html += '<ul class="hpi-results-list">';

                results.success.forEach(function(item) {
                    html += '<li class="success">';
                    html += '<div class="result-info">';
                    html += '<div class="result-title">' + escapeHtml(item.post_title) + '</div>';
                    html += '<div class="result-file">' + escapeHtml(item.file_name);
                    if (item.post_date) {
                        html += ' <span class="result-date">• Date: ' + escapeHtml(item.post_date) + '</span>';
                    }
                    if (item.featured_image) {
                        html += ' <span class="result-date">• Image: ' + escapeHtml(item.featured_image) + '</span>';
                    }
                    html += '</div>';
                    html += '</div>';
                    html += '<div class="result-actions">';
                    html += '<a href="' + item.edit_url + '" class="button button-small">Edit</a>';
                    html += '<a href="' + item.view_url + '" class="button button-small" target="_blank">View</a>';
                    html += '</div>';
                    html += '</li>';
                });

                html += '</ul>';
            }

            // Failed imports
            if (results.failed.length > 0) {
                html += '<h4 class="failed-header">⚠ Failed Imports - Please Review These Files</h4>';
                html += '<div style="margin-bottom: 10px;">';
                html += '<button type="button" class="button button-small hpi-copy-failed-files" data-files="' +
                        escapeHtml(JSON.stringify(results.failed.map(item => item.file))) + '">';
                html += '<span class="dashicons dashicons-clipboard" style="font-size: 16px; line-height: 1.2;"></span> ';
                html += 'Copy Failed Files List';
                html += '</button>';
                html += '</div>';
                html += '<ul class="hpi-results-list">';

                results.failed.forEach(function(item) {
                    html += '<li class="error">';
                    html += '<div class="result-info">';
                    html += '<div class="result-title">' + escapeHtml(item.file) + '</div>';
                    html += '<div class="result-error"><strong>Error:</strong> ' + escapeHtml(item.error) + '</div>';
                    html += '</div>';
                    html += '</li>';
                });

                html += '</ul>';
            }

            $resultsContent.html(html);

            // Add click handler for copy button
            $('.hpi-copy-failed-files').on('click', function() {
                const filesJson = $(this).data('files');
                const files = JSON.parse(filesJson);
                const filesList = files.join('\n');

                // Copy to clipboard
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(filesList).then(function() {
                        showNotice('success', 'Failed files list copied to clipboard!');
                    }).catch(function() {
                        fallbackCopy(filesList);
                    });
                } else {
                    fallbackCopy(filesList);
                }
            });
        }

        // Fallback copy function for older browsers
        function fallbackCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                showNotice('success', 'Failed files list copied to clipboard!');
            } catch (err) {
                showNotice('error', 'Failed to copy to clipboard');
            }
            document.body.removeChild(textarea);
        }

        // Show notice
        function showNotice(type, message) {
            const $notice = $('<div class="hpi-notice ' + type + '">' + escapeHtml(message) + '</div>');
            $form.before($notice);

            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }

        // Escape HTML
        function escapeHtml(text) {
            if (!text) {
                return '';
            }
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // File input change handler - trigger preview
        $fileInput.on('change', function() {
            const fileCount = this.files.length;
            if (fileCount > 0) {
                console.log('Selected ' + fileCount + ' file(s)');

                // Show preview for first file
                previewFirstFile(this.files[0]);
            } else {
                // Hide preview if no files selected
                $('#hpi-preview').hide();
            }
        });

        // Preview first file function
        function previewFirstFile(file) {
            const $preview = $('#hpi-preview');
            const $previewContent = $('#hpi-preview-content');

            // Show preview section with loading state
            $preview.show();
            $previewContent.html(
                '<div class="hpi-preview-loading">' +
                '<span class="spinner is-active"></span>' +
                '<p>Loading preview...</p>' +
                '</div>'
            );

            // Hide results if visible
            $results.hide();

            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'hpi_preview_file');
            formData.append('nonce', hpiAjax.nonce);
            formData.append('preview_file', file);

            // Send AJAX request
            $.ajax({
                url: hpiAjax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        displayPreview(response.data);
                    } else {
                        const errorMsg = (response.data && response.data.message) ? response.data.message : 'Could not load preview';
                        $previewContent.html(
                            '<div class="hpi-notice error">' +
                            '<strong>Preview Error:</strong> ' + escapeHtml(errorMsg) +
                            '</div>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    $previewContent.html(
                        '<div class="hpi-notice error">' +
                        '<strong>Error:</strong> Could not load preview. ' + escapeHtml(error) +
                        '</div>'
                    );
                }
            });
        }

        // Display preview data
        function displayPreview(data) {
            let html = '<div class="hpi-preview-data">';

            html += '<div class="hpi-preview-item">';
            html += '<strong>File:</strong> ' + escapeHtml(data.file_name);
            html += '</div>';

            html += '<div class="hpi-preview-item">';
            html += '<strong>Title:</strong> <span class="hpi-preview-title">' + escapeHtml(data.title) + '</span>';
            html += '</div>';

            html += '<div class="hpi-preview-item">';
            html += '<strong>Date:</strong> <span class="hpi-preview-date">' + escapeHtml(data.date) + '</span>';
            html += '</div>';

            html += '<div class="hpi-preview-item">';
            html += '<strong>First Image:</strong> <span class="hpi-preview-date">' + escapeHtml(data.first_image) + '</span>';
            html += '</div>';

            html += '<div class="hpi-preview-item">';
            html += '<strong>Content Preview:</strong> <span class="hpi-preview-length">(' + data.content_full + ')</span>';
            html += '<div class="hpi-preview-content-text">' + data.content + '</div>';
            html += '</div>';

            html += '<div class="hpi-notice success">';
            html += '<strong>✓ Preview successful!</strong> The data looks good. You can now proceed with the import.';
            html += '</div>';

            html += '</div>';

            $('#hpi-preview-content').html(html);
        }

        // Folder browser functionality
        let currentFolderPath = '';

        // Browse folder button handler
        $('#hpi-browse-folder').on('click', function() {
            openFolderBrowser();
        });

        // Open folder browser modal
        function openFolderBrowser(path) {
            $('#hpi-folder-browser-modal').fadeIn();
            loadFolders(path || '');
        }

        // Close modal handlers
        $('#hpi-folder-browser-modal .hpi-modal-close, #hpi-folder-cancel').on('click', function() {
            $('#hpi-folder-browser-modal').fadeOut();
        });

        // Click outside modal to close
        $('#hpi-folder-browser-modal').on('click', function(e) {
            if ($(e.target).is('#hpi-folder-browser-modal')) {
                $(this).fadeOut();
            }
        });

        // Select folder button
        $('#hpi-folder-select').on('click', function() {
            if (currentFolderPath) {
                $('#hpi-images-folder').val(currentFolderPath);
                $('#hpi-folder-browser-modal').fadeOut();
            }
        });

        // Load folders via AJAX
        function loadFolders(path) {
            const $folderList = $('#hpi-folder-list');

            $folderList.html(
                '<div class="hpi-folder-loading">' +
                '<span class="spinner is-active"></span>' +
                '<p>Loading folders...</p>' +
                '</div>'
            );

            $.ajax({
                url: hpiAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'hpi_browse_folders',
                    nonce: hpiAjax.nonce,
                    path: path
                },
                success: function(response) {
                    if (response.success) {
                        displayFolders(response.data);
                    } else {
                        const errorMsg = (response.data && response.data.message) ? response.data.message : 'Could not load folders';
                        $folderList.html(
                            '<div class="hpi-folder-empty">' +
                            '<strong>Error:</strong> ' + escapeHtml(errorMsg) +
                            '</div>'
                        );
                    }
                },
                error: function() {
                    $folderList.html(
                        '<div class="hpi-folder-empty">' +
                        '<strong>Error:</strong> Could not load folders.' +
                        '</div>'
                    );
                }
            });
        }

        // Display folders in the list
        function displayFolders(data) {
            currentFolderPath = data.current_path;
            $('#hpi-current-path').text(data.current_path);

            const $folderList = $('#hpi-folder-list');
            let html = '';

            // Add parent directory option if available
            if (data.parent_path) {
                html += '<div class="hpi-folder-item parent-folder" data-path="' + escapeHtml(data.parent_path) + '">';
                html += '<span class="hpi-folder-icon">↰</span>';
                html += '<span class="hpi-folder-name">..</span>';
                html += '</div>';
            }

            // Add folders
            if (data.folders.length === 0) {
                html += '<div class="hpi-folder-empty">No accessible subdirectories found.</div>';
            } else {
                data.folders.forEach(function(folder) {
                    html += '<div class="hpi-folder-item" data-path="' + escapeHtml(folder.path) + '">';
                    html += '<span class="hpi-folder-icon">📁</span>';
                    html += '<span class="hpi-folder-name">' + escapeHtml(folder.name) + '</span>';
                    if (folder.has_subdirs) {
                        html += '<span class="hpi-folder-arrow">→</span>';
                    }
                    html += '</div>';
                });
            }

            $folderList.html(html);

            // Add click handlers to folder items
            $('.hpi-folder-item').on('click', function() {
                const path = $(this).data('path');
                loadFolders(path);
            });
        }
    });

})(jQuery);

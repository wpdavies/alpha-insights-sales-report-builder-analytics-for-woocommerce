/**
 * Data Management Table JavaScript
 *
 * Handles AJAX interactions for the data management table
 *
 * @package Alpha Insights
 * @version 5.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Get localized data
        var dataManager = window.wpdDataManager || {};
        var ajaxUrl = dataManager.ajax_url || '';
        var nonce = dataManager.nonce || '';
        var cachedData = null; // Cache the AJAX response

        // Fetch all data
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpd_get_data_management_counts',
                nonce: nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    cachedData = response.data; // Cache the data
                    populateTableData(response.data);
                } else {
                    showError(dataManager.strings.failed_to_load || 'Failed to load data. Please refresh the page.');
                }
            },
            error: function() {
                showError(dataManager.strings.error_loading || 'Error loading data. Please refresh the page.');
            }
        });

        function populateTableData(data) {
            // Update counts for each entity
            $.each(data, function(entityType, entityData) {
                var $countCell = $('.wpd-count-' + entityType);
                var $deleteButton = $('.wpd-delete-entity[data-entity-type="' + entityType + '"]');

                // Update count
                $countCell.html('<span class="wpd-statistic-value">' + formatNumber(entityData.count) + '</span>');

                // Enable/disable delete button
                if (entityData.count > 0) {
                    $deleteButton.prop('disabled', false).removeAttr('disabled');
                } else {
                    $deleteButton.prop('disabled', true).attr('disabled', 'disabled');
                }

                // Enable/disable clear all tables button for database tables
                if (entityType === 'database_tables') {
                    var $clearAllButton = $('.wpd-clear-all-tables');
                    if (entityData.count > 0) {
                        $clearAllButton.prop('disabled', false);
                    }
                }
            });
        }

        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        function showError(message) {
            $('.wpd-statistic').each(function() {
                $(this).html('<span style="color: #d63638;">' + message + '</span>');
            });
        }

        // Refresh data counts after delete operations
        function refreshDataCounts() {
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpd_get_data_management_counts',
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        cachedData = response.data; // Update cache
                        populateTableData(response.data);
                    }
                }
            });
        }

        // Toggle sub-rows
        $(document).on('click', '.wpd-entity-row-expandable', function(e) {
            // Don't toggle if clicking the delete button or any button
            if ($(e.target).closest('.button, button').length) {
                return;
            }

            var entityType = $(this).data('entity-type');
            var $subRowContainer = $('.wpd-sub-row-container.wpd-sub-row-' + entityType);
            var $row = $(this);
            var $subRows = $subRowContainer.nextAll('.wpd-sub-row-' + entityType);

            if ($subRows.is(':visible')) {
                $subRows.slideUp();
                $row.removeClass('expanded');
            } else {
                // Load sub-row data if not already loaded
                if ($subRows.length === 0) {
                    loadSubRowData(entityType, $subRowContainer);
                    // Wait a moment for rows to be inserted, then show them
                    setTimeout(function() {
                        $subRowContainer.nextAll('.wpd-sub-row-' + entityType).slideDown();
                        $row.addClass('expanded');
                    }, 50);
                } else {
                    $subRows.slideDown();
                    $row.addClass('expanded');
                }
            }
        });

        function loadSubRowData(entityType, $container) {
            // Use cached data if available, otherwise fetch
            if (cachedData && cachedData[entityType]) {
                renderSubRows(entityType, cachedData[entityType].details, $container);
            } else {
                // Fallback: fetch if cache not available
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpd_get_data_management_counts',
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            cachedData = response.data; // Cache the data
                            if (response.data[entityType]) {
                                renderSubRows(entityType, response.data[entityType].details, $container);
                            }
                        }
                    }
                });
            }
        }

        function renderSubRows(entityType, details, $container) {
            // Remove any existing sub-rows for this entity type
            $container.nextAll('.wpd-sub-row-' + entityType).remove();

            // Hide the loading container
            $container.hide();

            if (!details || details.length === 0) {
                var $emptyRow = $('<tr class="wpd-sub-row wpd-sub-row-' + entityType + '"><td colspan="4" style="padding: 10px; text-align: center; color: #666;">' + (dataManager.strings.no_items_found || 'No items found.') + '</td></tr>');
                $container.after($emptyRow);
                return;
            }

            $.each(details, function(index, item) {
                var $row = $('<tr class="wpd-sub-row wpd-sub-row-' + entityType + '"></tr>');

                if (entityType === 'database_tables') {
                    $row.html(
                        '<td style="padding-left: 40px;">' +
                            '<span class="wpd-meta">' + escapeHtml(item.friendly_name) + '</span><br>' +
                            '<code style="font-size: 11px; color: #666;">' + escapeHtml(item.table_name) + '</code>' +
                        '</td>' +
                        '<td>' +
                            '<span class="wpd-meta">' + (dataManager.strings.records || 'Records:') + ' <strong>' + formatNumber(item.record_count) + '</strong></span><br>' +
                            '<span class="wpd-meta">' + (dataManager.strings.size || 'Size:') + ' <strong>' + parseFloat(item.size_mb).toFixed(2) + ' MB</strong></span>' +
                        '</td>' +
                        '<td><span class="wpd-statistic">' + formatNumber(item.record_count) + '</span></td>' +
                        '<td>' +
                            '<button type="button" class="button button-small wpd-truncate-table" ' +
                            'data-table-name="' + escapeHtml(item.table_name) + '" ' +
                            'data-table-friendly="' + escapeHtml(item.friendly_name) + '" ' +
                            'style="margin-right: 5px;">' +
                            (dataManager.strings.clear || 'Clear') +
                            '</button>' +
                            '<button type="button" class="button button-small button-link-delete wpd-delete-table" ' +
                            'data-table-name="' + escapeHtml(item.table_name) + '" ' +
                            'data-table-friendly="' + escapeHtml(item.friendly_name) + '">' +
                            (dataManager.strings.delete || 'Delete') +
                            '</button>' +
                        '</td>'
                    );
                } else if (entityType === 'orders' || entityType === 'products') {
                    $row.html(
                        '<td style="padding-left: 40px;">' +
                            '<strong>' + escapeHtml(item.friendly_name) + '</strong><br>' +
                            '<code style="font-size: 11px; color: #666;">' + escapeHtml(item.meta_key) + '</code>' +
                        '</td>' +
                        '<td><span class="wpd-meta">' + escapeHtml($('.wpd-entity-row[data-entity-type="' + entityType + '"]').find('strong').text()) + ' ' + (dataManager.strings.meta_key || 'meta key') + '</span></td>' +
                        '<td><span class="wpd-statistic">' + formatNumber(item.count) + '</span></td>' +
                        '<td>' +
                            '<button type="button" class="button button-small button-link-delete wpd-delete-meta-key" ' +
                            'data-entity-type="' + entityType + '" ' +
                            'data-meta-key="' + escapeHtml(item.meta_key) + '" ' +
                            'data-meta-friendly="' + escapeHtml(item.friendly_name) + '">' +
                            (dataManager.strings.delete || 'Delete') +
                            '</button>' +
                        '</td>'
                    );
                } else if (entityType === 'transients' || entityType === 'options') {
                    $row.html(
                        '<td style="padding-left: 40px;">' +
                            '<strong>' + escapeHtml(item.friendly_name) + '</strong><br>' +
                            '<code style="font-size: 11px; color: #666;">' + escapeHtml(item.key) + '</code>' +
                        '</td>' +
                        '<td><span class="wpd-meta">' + escapeHtml($('.wpd-entity-row[data-entity-type="' + entityType + '"]').find('strong').text()) + ' ' + (dataManager.strings.entry || 'entry') + '</span></td>' +
                        '<td><span class="wpd-statistic">1</span></td>' +
                        '<td>' +
                            '<button type="button" class="button button-small button-link-delete wpd-delete-single-item" ' +
                            'data-entity-type="' + entityType + '" ' +
                            'data-item-key="' + escapeHtml(item.key) + '" ' +
                            'data-item-friendly="' + escapeHtml(item.friendly_name) + '">' +
                            (dataManager.strings.delete || 'Delete') +
                            '</button>' +
                        '</td>'
                    );
                } else if (entityType === 'scheduled_tasks') {
                    var scheduledDate = item.scheduled_date ? '<br>' + (dataManager.strings.scheduled || 'Scheduled:') + ' <strong>' + escapeHtml(item.scheduled_date) + '</strong>' : '';
                    $row.html(
                        '<td style="padding-left: 40px;">' +
                            '<strong>' + escapeHtml(item.friendly_name) + '</strong><br>' +
                            '<code style="font-size: 11px; color: #666;">' + escapeHtml(item.hook) + '</code>' +
                        '</td>' +
                        '<td>' +
                            '<span class="wpd-meta">' + (dataManager.strings.status || 'Status:') + ' <strong>' + escapeHtml(item.status) + '</strong>' + scheduledDate + '</span>' +
                        '</td>' +
                        '<td><span class="wpd-statistic">1</span></td>' +
                        '<td>' +
                            '<button type="button" class="button button-small button-link-delete wpd-delete-scheduled-task" ' +
                            'data-action-id="' + escapeHtml(item.action_id) + '" ' +
                            'data-hook="' + escapeHtml(item.hook) + '" ' +
                            'data-hook-friendly="' + escapeHtml(item.friendly_name) + '">' +
                            (dataManager.strings.delete || 'Delete') +
                            '</button>' +
                        '</td>'
                    );
                } else {
                    // Post type sub-rows (expenses, facebook_campaigns, google_campaigns)
                    $row.html(
                        '<td style="padding-left: 40px;"><strong>' + escapeHtml(item.label) + '</strong></td>' +
                        '<td><span class="wpd-meta">' + escapeHtml($('.wpd-entity-row[data-entity-type="' + entityType + '"]').find('strong').text()) + ' ' + escapeHtml(item.label.toLowerCase()) + '</span></td>' +
                        '<td><span class="wpd-statistic">' + formatNumber(item.count) + '</span></td>' +
                        '<td>' +
                            '<button type="button" class="button button-small button-link-delete wpd-delete-post-type-meta" ' +
                            'data-entity-type="' + entityType + '" ' +
                            'data-meta-type="' + escapeHtml(item.label.toLowerCase()) + '">' +
                            (dataManager.strings.delete || 'Delete') +
                            '</button>' +
                        '</td>'
                    );
                }

                // Insert the row after the container (which is the loading placeholder)
                $container.after($row);
            });
        }

        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
        }

        // Handle delete entity button clicks - use mousedown to catch event before row click
        $(document).on('mousedown', '.wpd-delete-entity', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
        
        $(document).on('click', '.wpd-delete-entity', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $button = $(this);
            
            // Don't proceed if button is disabled
            if ($button.prop('disabled') || $button.attr('disabled') === 'disabled') {
                return false;
            }
            
            var entityType = $button.data('entity-type');
            var entityName = $button.data('entity-name');
            
            // Make sure we have valid data
            if (!entityType || !entityName) {
                console.error('Missing entity data:', { entityType: entityType, entityName: entityName });
                return false;
            }

            // Show confirmation
            if (!confirm((dataManager.strings.confirm_delete_all || 'Are you sure you want to delete all') + ' ' + entityName + '? ' + (dataManager.strings.action_cannot_undone || 'This action cannot be undone.'))) {
                return false;
            }

            // Show processing notification
            if (typeof wpdPopNotification === 'function') {
                wpdPopNotification('loading', dataManager.strings.processing || 'Processing...', dataManager.strings.working || 'We are working on it!');
            }

            var originalText = $button.text();
            $button.prop('disabled', true).text(dataManager.strings.deleting || 'Deleting...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpd_delete_entity',
                    entity_type: entityType,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update count to 0
                        var $countCell = $('.wpd-count-' + entityType);
                        $countCell.html('<span class="wpd-statistic-value">0</span>');
                        
                        // Clear cached data for this entity
                        if (cachedData && cachedData[entityType]) {
                            cachedData[entityType].count = 0;
                            cachedData[entityType].details = [];
                        }
                        
                        // Remove any expanded sub-rows for this entity
                        $('.wpd-sub-row-' + entityType).remove();
                        $('.wpd-sub-row-container.wpd-sub-row-' + entityType).hide();
                        $('.wpd-entity-row-expandable[data-entity-type="' + entityType + '"]').removeClass('expanded');
                        
                        // Restore button text and disable delete button (after UI updates)
                        $button.prop('disabled', true).text(originalText);
                        
                        // Show success notification
                        if (typeof wpdPopNotification === 'function') {
                            wpdPopNotification('success', dataManager.strings.success || 'Success!', (dataManager.strings.deleted_successfully || 'Deleted successfully.'));
                        }
                    } else {
                        // Show error notification
                        var errorMessage = response.data && response.data.message ? response.data.message : (dataManager.strings.failed_to_delete || 'Failed to delete.');
                        if (typeof wpdPopNotification === 'function') {
                            wpdPopNotification('fail', dataManager.strings.error || 'Error', errorMessage);
                        }
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    // Show error notification
                    if (typeof wpdPopNotification === 'function') {
                        wpdPopNotification('fail', dataManager.strings.error || 'Error', dataManager.strings.error_occurred || 'Error occurred. Please try again.');
                    }
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });

        // Handle delete table button clicks
        $(document).on('click', '.wpd-delete-table', function(e) {
            e.stopPropagation();
            var $button = $(this);
            var tableName = $button.data('table-name');
            var tableFriendly = $button.data('table-friendly');
            var $row = $button.closest('tr');

            // Show confirmation
            if (!confirm((dataManager.strings.confirm_delete_table || 'Are you sure you want to delete the table') + ' "' + tableFriendly + '"? ' + (dataManager.strings.action_cannot_undone || 'This action cannot be undone.'))) {
                return;
            }

            // Show processing notification
            if (typeof wpdPopNotification === 'function') {
                wpdPopNotification('loading', dataManager.strings.processing || 'Processing...', dataManager.strings.working || 'We are working on it!');
            }

            $button.prop('disabled', true).text(dataManager.strings.deleting || 'Deleting...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpd_delete_table',
                    table_name: tableName,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Remove the row
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                        
                        // Refresh data counts
                        refreshDataCounts();
                        
                        // Show success notification
                        if (typeof wpdPopNotification === 'function') {
                            wpdPopNotification('success', dataManager.strings.success || 'Success!', (dataManager.strings.deleted_successfully || 'Deleted successfully.'));
                        }
                    } else {
                        // Show error notification
                        var errorMessage = response.data && response.data.message ? response.data.message : (dataManager.strings.failed_to_delete_table || 'Failed to delete table.');
                        if (typeof wpdPopNotification === 'function') {
                            wpdPopNotification('fail', dataManager.strings.error || 'Error', errorMessage);
                        }
                        $button.prop('disabled', false).text(dataManager.strings.delete || 'Delete');
                    }
                },
                error: function() {
                    // Show error notification
                    if (typeof wpdPopNotification === 'function') {
                        wpdPopNotification('fail', dataManager.strings.error || 'Error', dataManager.strings.error_occurred || 'Error occurred. Please try again.');
                    }
                    $button.prop('disabled', false).text(dataManager.strings.delete || 'Delete');
                }
            });
        });

        // Handle truncate table button clicks
        $(document).on('click', '.wpd-truncate-table', function(e) {
            e.stopPropagation();
            var $button = $(this);
            var tableName = $button.data('table-name');
            var tableFriendly = $button.data('table-friendly');
            var $row = $button.closest('tr');

            // Show confirmation
            if (!confirm((dataManager.strings.confirm_clear_table || 'Are you sure you want to clear all data from the table') + ' "' + tableFriendly + '"? ' + (dataManager.strings.action_cannot_undone || 'This action cannot be undone.'))) {
                return;
            }

            // Show processing notification
            if (typeof wpdPopNotification === 'function') {
                wpdPopNotification('loading', dataManager.strings.processing || 'Processing...', dataManager.strings.working || 'We are working on it!');
            }

            $button.prop('disabled', true).text(dataManager.strings.clearing || 'Clearing...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpd_truncate_table',
                    table_name: tableName,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update the count in the row to 0
                        $row.find('.wpd-statistic').html('<span class="wpd-statistic-value">0</span>');
                        
                        // Refresh data counts
                        refreshDataCounts();
                        
                        // Show success notification
                        if (typeof wpdPopNotification === 'function') {
                            wpdPopNotification('success', dataManager.strings.success || 'Success!', (dataManager.strings.deleted_successfully || 'Cleared successfully.'));
                        }
                    } else {
                        // Show error notification
                        var errorMessage = response.data && response.data.message ? response.data.message : (dataManager.strings.failed_to_clear_table || 'Failed to clear table.');
                        if (typeof wpdPopNotification === 'function') {
                            wpdPopNotification('fail', dataManager.strings.error || 'Error', errorMessage);
                        }
                        $button.prop('disabled', false).text(dataManager.strings.clear || 'Clear');
                    }
                },
                error: function() {
                    // Show error notification
                    if (typeof wpdPopNotification === 'function') {
                        wpdPopNotification('fail', dataManager.strings.error || 'Error', dataManager.strings.error_occurred || 'Error occurred. Please try again.');
                    }
                    $button.prop('disabled', false).text(dataManager.strings.clear || 'Clear');
                }
            });
        });

        // Handle delete meta key button clicks
        $(document).on('click', '.wpd-delete-meta-key', function(e) {
            e.stopPropagation();
            var $button = $(this);
            var entityType = $button.data('entity-type');
            var metaKey = $button.data('meta-key');
            var metaFriendly = $button.data('meta-friendly');
            var $row = $button.closest('tr');

            // Show confirmation
            if (!confirm((dataManager.strings.confirm_delete_meta_key || 'Are you sure you want to delete the meta key') + ' "' + metaFriendly + '"? ' + (dataManager.strings.action_cannot_undone || 'This action cannot be undone.'))) {
                return;
            }

            // Show processing notification
            if (typeof wpdPopNotification === 'function') {
                wpdPopNotification('loading', dataManager.strings.processing || 'Processing...', dataManager.strings.working || 'We are working on it!');
            }

            $button.prop('disabled', true).text(dataManager.strings.deleting || 'Deleting...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpd_delete_meta_key',
                    entity_type: entityType,
                    meta_key: metaKey,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Remove the row
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                        
                        // Refresh data counts
                        refreshDataCounts();
                        
                        // Show success notification
                        if (typeof wpdPopNotification === 'function') {
                            wpdPopNotification('success', dataManager.strings.success || 'Success!', (dataManager.strings.deleted_successfully || 'Deleted successfully.'));
                        }
                    } else {
                        // Show error notification
                        var errorMessage = response.data && response.data.message ? response.data.message : (dataManager.strings.failed_to_delete_meta_key || 'Failed to delete meta key.');
                        if (typeof wpdPopNotification === 'function') {
                            wpdPopNotification('fail', dataManager.strings.error || 'Error', errorMessage);
                        }
                        $button.prop('disabled', false).text(dataManager.strings.delete || 'Delete');
                    }
                },
                error: function() {
                    // Show error notification
                    if (typeof wpdPopNotification === 'function') {
                        wpdPopNotification('fail', dataManager.strings.error || 'Error', dataManager.strings.error_occurred || 'Error occurred. Please try again.');
                    }
                    $button.prop('disabled', false).text(dataManager.strings.delete || 'Delete');
                }
            });
        });

        // Handle delete single item button clicks
        $(document).on('click', '.wpd-delete-single-item', function(e) {
            e.stopPropagation();
            var $button = $(this);
            var entityType = $button.data('entity-type');
            var itemKey = $button.data('item-key');
            var itemFriendly = $button.data('item-friendly');
            var $row = $button.closest('tr');

            // Show confirmation
            if (!confirm((dataManager.strings.confirm_delete_item || 'Are you sure you want to delete') + ' "' + itemFriendly + '"? ' + (dataManager.strings.action_cannot_undone || 'This action cannot be undone.'))) {
                return;
            }

            // Show processing notification
            if (typeof wpdPopNotification === 'function') {
                wpdPopNotification('loading', dataManager.strings.processing || 'Processing...', dataManager.strings.working || 'We are working on it!');
            }

            $button.prop('disabled', true).text(dataManager.strings.deleting || 'Deleting...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpd_delete_single_item',
                    entity_type: entityType,
                    item_key: itemKey,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Remove the row
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                        
                        // Refresh data counts
                        refreshDataCounts();
                        
                        // Show success notification
                        if (typeof wpdPopNotification === 'function') {
                            wpdPopNotification('success', dataManager.strings.success || 'Success!', (dataManager.strings.deleted_successfully || 'Deleted successfully.'));
                        }
                    } else {
                        // Show error notification
                        var errorMessage = response.data && response.data.message ? response.data.message : (dataManager.strings.failed_to_delete_item || 'Failed to delete item.');
                        if (typeof wpdPopNotification === 'function') {
                            wpdPopNotification('fail', dataManager.strings.error || 'Error', errorMessage);
                        }
                        $button.prop('disabled', false).text(dataManager.strings.delete || 'Delete');
                    }
                },
                error: function() {
                    // Show error notification
                    if (typeof wpdPopNotification === 'function') {
                        wpdPopNotification('fail', dataManager.strings.error || 'Error', dataManager.strings.error_occurred || 'Error occurred. Please try again.');
                    }
                    $button.prop('disabled', false).text(dataManager.strings.delete || 'Delete');
                }
            });
        });

        // Handle delete scheduled task button clicks
        $(document).on('click', '.wpd-delete-scheduled-task', function(e) {
            e.stopPropagation();
            var $button = $(this);
            var actionId = $button.data('action-id');
            var hookFriendly = $button.data('hook-friendly');
            var $row = $button.closest('tr');

            // Show confirmation
            if (!confirm((dataManager.strings.confirm_delete_scheduled_task || 'Are you sure you want to delete the scheduled task') + ' "' + hookFriendly + '"? ' + (dataManager.strings.action_cannot_undone || 'This action cannot be undone.'))) {
                return;
            }

            // Show processing notification
            if (typeof wpdPopNotification === 'function') {
                wpdPopNotification('loading', dataManager.strings.processing || 'Processing...', dataManager.strings.working || 'We are working on it!');
            }

            $button.prop('disabled', true).text(dataManager.strings.deleting || 'Deleting...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpd_delete_scheduled_task',
                    action_id: actionId,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Remove the row
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                        
                        // Refresh data counts
                        refreshDataCounts();
                        
                        // Show success notification
                        if (typeof wpdPopNotification === 'function') {
                            wpdPopNotification('success', dataManager.strings.success || 'Success!', (dataManager.strings.deleted_successfully || 'Deleted successfully.'));
                        }
                    } else {
                        // Show error notification
                        var errorMessage = response.data && response.data.message ? response.data.message : (dataManager.strings.failed_to_delete_scheduled_task || 'Failed to delete scheduled task.');
                        if (typeof wpdPopNotification === 'function') {
                            wpdPopNotification('fail', dataManager.strings.error || 'Error', errorMessage);
                        }
                        $button.prop('disabled', false).text(dataManager.strings.delete || 'Delete');
                    }
                },
                error: function() {
                    // Show error notification
                    if (typeof wpdPopNotification === 'function') {
                        wpdPopNotification('fail', dataManager.strings.error || 'Error', dataManager.strings.error_occurred || 'Error occurred. Please try again.');
                    }
                    $button.prop('disabled', false).text(dataManager.strings.delete || 'Delete');
                }
            });
        });

        // Handle delete post type meta button clicks
        $(document).on('click', '.wpd-delete-post-type-meta', function(e) {
            e.stopPropagation();
            var $button = $(this);
            var entityType = $button.data('entity-type');
            var metaType = $button.data('meta-type');
            var $row = $button.closest('tr');

            // Show confirmation
            if (!confirm((dataManager.strings.confirm_delete_data || 'Are you sure you want to delete this data?') + ' ' + (dataManager.strings.action_cannot_undone || 'This action cannot be undone.'))) {
                return;
            }

            // Show processing notification
            if (typeof wpdPopNotification === 'function') {
                wpdPopNotification('loading', dataManager.strings.processing || 'Processing...', dataManager.strings.working || 'We are working on it!');
            }

            $button.prop('disabled', true).text(dataManager.strings.deleting || 'Deleting...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpd_delete_post_type_meta',
                    entity_type: entityType,
                    meta_type: metaType,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Remove the row
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                        
                        // Refresh data counts
                        refreshDataCounts();
                        
                        // Show success notification
                        if (typeof wpdPopNotification === 'function') {
                            wpdPopNotification('success', dataManager.strings.success || 'Success!', (dataManager.strings.deleted_successfully || 'Deleted successfully.'));
                        }
                    } else {
                        // Show error notification
                        var errorMessage = response.data && response.data.message ? response.data.message : (dataManager.strings.failed_to_delete_data || 'Failed to delete data.');
                        if (typeof wpdPopNotification === 'function') {
                            wpdPopNotification('fail', dataManager.strings.error || 'Error', errorMessage);
                        }
                        $button.prop('disabled', false).text(dataManager.strings.delete || 'Delete');
                    }
                },
                error: function() {
                    // Show error notification
                    if (typeof wpdPopNotification === 'function') {
                        wpdPopNotification('fail', dataManager.strings.error || 'Error', dataManager.strings.error_occurred || 'Error occurred. Please try again.');
                    }
                    $button.prop('disabled', false).text(dataManager.strings.delete || 'Delete');
                }
            });
        });

        // Handle clear all tables button clicks
        $(document).on('click', '.wpd-clear-all-tables', function(e) {
            e.stopPropagation();
            var $button = $(this);

            if (!confirm((dataManager.strings.confirm_clear_all_tables || 'Are you sure you want to clear all database tables?') + ' ' + (dataManager.strings.clear_all_tables_warning || 'This will remove all data from all tables but keep the table structure. This action cannot be undone.'))) {
                return;
            }

            $button.prop('disabled', true).text(dataManager.strings.clearing || 'Clearing...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpd_truncate_all_tables',
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload the page to refresh data
                        location.reload();
                    } else {
                        alert(response.data && response.data.message ? response.data.message : (dataManager.strings.failed_to_clear_tables || 'Failed to clear tables.'));
                        $button.prop('disabled', false).text(dataManager.strings.clear_all_tables || 'Clear All Tables');
                    }
                },
                error: function() {
                    alert(dataManager.strings.error_occurred || 'Error occurred. Please try again.');
                    $button.prop('disabled', false).text(dataManager.strings.clear_all_tables || 'Clear All Tables');
                }
            });
        });
    });

})(jQuery);


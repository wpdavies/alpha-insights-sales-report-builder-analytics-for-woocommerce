/**
 * Migration Management JavaScript
 * 
 * Handles AJAX requests for running migrations
 * 
 * @package Alpha Insights
 * @version 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Helper function to escape HTML
	 * 
	 * @param {string} text Text to escape
	 * @return {string} Escaped HTML
	 */
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

	/**
	 * Show admin notice
	 * 
	 * @param {string} message Notice message
	 * @param {string} type Notice type (success, error, warning, info)
	 */
	function showNotice(message, type) {
		type = type || 'success';
		var noticeClass = 'notice-' + type;
		var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + escapeHtml(message) + '</p></div>');
		$('.wpd-wrapper:first').after($notice);
		$notice.delay(5000).fadeOut(function() { 
			$(this).remove(); 
		});
	}

	/**
	 * Update migration row status
	 * 
	 * @param {jQuery} $row Migration table row
	 * @param {string} completionTime Formatted completion time
	 */
	function updateMigrationStatus($row, completionTime) {
		var statusHtml = '<span class="wpd-meta" style="color: #00a32a;">' +
			'<span class="dashicons dashicons-yes-alt" style="font-size: 16px; vertical-align: middle;"></span> ' +
			window.wpdMigrationVars.strings.completed +
			'</span>';
		
		if (completionTime) {
			statusHtml += '<br><span class="wpd-meta" style="font-size: 11px; color: #666;">' +
				window.wpdMigrationVars.strings.ran + ' ' + escapeHtml(completionTime) +
				'</span>';
		}
		
		$row.find('td:nth-child(3)').html(statusHtml);
	}

	/**
	 * Handle migration button click
	 */
	$(document).ready(function() {
		$('.wpd-run-migration').on('click', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var migrationKey = $button.data('migration-key');
			var migrationName = $button.data('migration-name');
			var $row = $button.closest('tr');
			
			// Disable button and show loading
			$button.prop('disabled', true).text(window.wpdMigrationVars.strings.running);
			
			// Make AJAX request
			$.ajax({
				url: window.wpdMigrationVars.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpd_run_migration',
					migration_key: migrationKey,
					nonce: window.wpdMigrationVars.nonce
				},
				success: function(response) {
					if (response.success) {
						// Update status
						var completionTime = response.data && response.data.completion_time ? response.data.completion_time : '';
						updateMigrationStatus($row, completionTime);
						
						// Re-enable button
						$button.text(window.wpdMigrationVars.strings.runMigration).prop('disabled', false);
						
						// Show success notice
						if (response.data && response.data.message) {
							showNotice(response.data.message, 'success');
						}
					} else {
						// Show error
						$button.text(window.wpdMigrationVars.strings.runMigration).prop('disabled', false);
						var errorMsg = response.data && response.data.message ? response.data.message : window.wpdMigrationVars.strings.migrationFailed;
						showNotice(errorMsg, 'error');
					}
				},
				error: function() {
					$button.text(window.wpdMigrationVars.strings.runMigration).prop('disabled', false);
					showNotice(window.wpdMigrationVars.strings.errorRunningMigration, 'error');
				}
			});
		});
	});

})(jQuery);

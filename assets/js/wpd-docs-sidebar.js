/**
 * Documentation Sidebar JavaScript
 * 
 * Handles accordion functionality for the documentation sidebar navigation
 * 
 * @package Alpha Insights
 * @version 1.0.0
 */

(function() {
	'use strict';

	/**
	 * Initialize accordion functionality
	 */
	function initAccordions() {
		// Initialize accordions
		var toggles = document.querySelectorAll('.wpd-docs-folder-toggle');
		
		toggles.forEach(function(toggle) {
			toggle.addEventListener('click', function() {
				var folderId = this.getAttribute('data-folder-id');
				var children = document.querySelector('.wpd-docs-folder-children[data-parent-id="' + folderId + '"]');
				var icon = this.querySelector('.wpd-docs-folder-toggle-icon');
				
				if (children) {
					if (children.classList.contains('expanded')) {
						children.classList.remove('expanded');
						if (icon) {
							icon.classList.remove('expanded');
						}
					} else {
						children.classList.add('expanded');
						if (icon) {
							icon.classList.add('expanded');
						}
					}
				}
			});
		});

		// Expand active parent folders on load
		var activeParents = document.querySelectorAll('.wpd-docs-folder-toggle.wpd-active-parent');
		activeParents.forEach(function(parent) {
			var folderId = parent.getAttribute('data-folder-id');
			var children = document.querySelector('.wpd-docs-folder-children[data-parent-id="' + folderId + '"]');
			var icon = parent.querySelector('.wpd-docs-folder-toggle-icon');
			
			if (children) {
				children.classList.add('expanded');
				if (icon) {
					icon.classList.add('expanded');
				}
			}
		});
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAccordions);
	} else {
		// DOM is already ready
		initAccordions();
	}

})();

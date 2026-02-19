/**
 * Alpha Insights - Integrations filter
 * Instant category tabs and search filtering
 */
(function($) {
	'use strict';

	function initIntegrationsFilter() {
		var $container = $('#wpd-integrations-grid');
		var $noResults = $('#wpd-integrations-no-results');
		var $tabs = $('.wpd-integrations-tab');
		var $searchInput = $('#wpd-integrations-search');

		if (!$container.length) return;

		var currentCategory = '';
		var currentSearch = '';

		function filterCards() {
			var visibleCount = 0;
			$container.find('.wpd-integration-card').each(function() {
				var $card = $(this);
				var cardCategory = $card.attr('data-category') || '';
				var cardSearch = ($card.attr('data-search') || '').toLowerCase();
				var categoryMatch = currentCategory === '' || cardCategory === currentCategory;
				var searchMatch = currentSearch === '' || cardSearch.indexOf(currentSearch) !== -1;
				var show = categoryMatch && searchMatch;
				$card.toggle(show);
				if (show) visibleCount++;
			});
			$noResults.toggle(visibleCount === 0);
		}

		function setActiveTab($tab) {
			$tabs.removeClass('wpd-integrations-tab-active').attr('aria-selected', 'false');
			$tab.addClass('wpd-integrations-tab-active').attr('aria-selected', 'true');
			currentCategory = $tab.data('category') || '';
			filterCards();
		}

		$tabs.on('click', function() {
			setActiveTab($(this));
		});

		$searchInput.on('input keyup', function() {
			currentSearch = ($(this).val() || '').toLowerCase().trim();
			filterCards();
		});
	}

	$(document).ready(initIntegrationsFilter);

})(jQuery);

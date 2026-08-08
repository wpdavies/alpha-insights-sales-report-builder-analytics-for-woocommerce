/**
 * Alpha Insights - Mobile navigation drawer.
 *
 * @package Alpha Insights
 */
(function($) {
	'use strict';

	var MOBILE_BREAKPOINT = 768;
	var OPEN_CLASS = 'is-mobile-nav-open';

	function isMobileNav() {
		return window.matchMedia('(max-width: ' + MOBILE_BREAKPOINT + 'px)').matches;
	}

	function getNavWrapper() {
		return document.querySelector('.wpd-nav-wrapper');
	}

	function getDrawer() {
		return document.getElementById('wpd-ai-mobile-drawer');
	}

	function getMenuToggle() {
		return document.querySelector('.wpd-mobile-nav-toggle');
	}

	function getCloseButton() {
		return document.querySelector('.wpd-mobile-nav-close');
	}

	function getBackdrop() {
		return document.querySelector('.wpd-mobile-nav-backdrop');
	}

	function setMenuOpen(isOpen) {
		var wrapper = getNavWrapper();
		var drawer = getDrawer();
		var toggle = getMenuToggle();
		var backdrop = getBackdrop();

		if (!wrapper || !drawer || !toggle) {
			return;
		}

		wrapper.classList.toggle(OPEN_CLASS, isOpen);
		toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		toggle.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
		drawer.setAttribute('aria-hidden', isOpen ? 'false' : 'true');

		if (backdrop) {
			backdrop.hidden = !isOpen;
			backdrop.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
		}

		document.body.classList.toggle('wpd-mobile-nav-open', isOpen);
	}

	function closeMobileNav() {
		setMenuOpen(false);
	}

	function openMobileNav() {
		if (!isMobileNav()) {
			return;
		}

		setMenuOpen(true);
	}

	function toggleMobileNav() {
		var wrapper = getNavWrapper();

		if (!wrapper) {
			return;
		}

		if (wrapper.classList.contains(OPEN_CLASS)) {
			closeMobileNav();
		} else {
			openMobileNav();
		}
	}

	function setAccordionExpanded(item, isExpanded) {
		if (!item) {
			return;
		}

		item.classList.toggle('is-expanded', isExpanded);

		var trigger = item.querySelector('.wpd-mobile-nav-accordion-trigger');
		var sublist = item.querySelector('.wpd-mobile-nav-sublist');

		if (trigger) {
			trigger.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
		}

		if (sublist) {
			sublist.setAttribute('aria-hidden', isExpanded ? 'false' : 'true');
		}
	}

	function collapseDrawerAccordions(exceptItem) {
		document.querySelectorAll('.wpd-mobile-nav-item.has-children.is-expanded').forEach(function(item) {
			if (item !== exceptItem) {
				setAccordionExpanded(item, false);
			}
		});
	}

	function initDrawerAccordions() {
		document.querySelectorAll('.wpd-mobile-nav-accordion-trigger').forEach(function(trigger) {
			trigger.addEventListener('click', function(event) {
				event.preventDefault();
				event.stopPropagation();

				var item = trigger.closest('.wpd-mobile-nav-item');
				if (!item) {
					return;
				}

				var willExpand = !item.classList.contains('is-expanded');

				if (willExpand) {
					collapseDrawerAccordions(item);
				}

				setAccordionExpanded(item, willExpand);
			});
		});
	}

	function initMobileNav() {
		var toggle = getMenuToggle();
		var closeButton = getCloseButton();
		var backdrop = getBackdrop();
		var wrapper = getNavWrapper();

		if (!toggle || !wrapper) {
			return;
		}

		toggle.addEventListener('click', function(event) {
			event.preventDefault();
			event.stopPropagation();
			toggleMobileNav();
		});

		if (closeButton) {
			closeButton.addEventListener('click', function(event) {
				event.preventDefault();
				closeMobileNav();
			});
		}

		if (backdrop) {
			backdrop.addEventListener('click', closeMobileNav);
		}

		document.addEventListener('keydown', function(event) {
			if (event.key === 'Escape') {
				closeMobileNav();
			}
		});

		window.addEventListener('resize', function() {
			if (!isMobileNav()) {
				closeMobileNav();
			}
		});

		document.querySelectorAll('.wpd-mobile-nav-link, .wpd-mobile-nav-sublink').forEach(function(link) {
			link.addEventListener('click', function() {
				if (isMobileNav()) {
					closeMobileNav();
				}
			});
		});

		initDrawerAccordions();
	}

	$(document).ready(initMobileNav);
})(jQuery);

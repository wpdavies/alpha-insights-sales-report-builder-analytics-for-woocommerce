/**
 * Alpha Insights - Submenu Scroll Functionality
 * Handles horizontal scrolling for submenu with scroll buttons and custom scrollbar
 * 
 * @package Alpha Insights
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(document).ready(function() {
        initHorizontalScrollMenu();
    });

    /**
     * Horizontal Scrolling Menu with Drag, Scroll Controls, and Gradient Fades
     * Responsive - only activates when menu needs scrolling
     */
    function initHorizontalScrollMenu() {
        const container = document.querySelector('.wpd-sub-menu-container');
        if (!container) return;

        const menu = container.querySelector('.wpd-sub-menu');
        const fadeLeft = container.querySelector('.wpd-sub-menu-fade-left');
        const fadeRight = container.querySelector('.wpd-sub-menu-fade-right');
        const btnLeft = container.querySelector('.wpd-sub-menu-scroll-left');
        const btnRight = container.querySelector('.wpd-sub-menu-scroll-right');
        const scrollbarThumb = container.querySelector('.wpd-sub-menu-scrollbar-thumb');

        if (!menu || !fadeLeft || !fadeRight || !btnLeft || !btnRight || !scrollbarThumb) return;

        let isDown = false;
        let startX;
        let scrollLeft;

        // Check if menu needs scrolling
        function updateScrollState() {
            const isScrollable = menu.scrollWidth > menu.clientWidth;
            
            if (isScrollable) {
                container.classList.add('has-scroll');
                updateFadeVisibility();
            } else {
                container.classList.remove('has-scroll');
                container.classList.remove('at-start', 'at-end');
                fadeLeft.classList.remove('show');
                fadeRight.classList.remove('show');
            }
        }

        // Update custom scrollbar position
        function updateCustomScrollbar() {
            const scrollPosition = menu.scrollLeft;
            const maxScroll = menu.scrollWidth - menu.clientWidth;
            const scrollPercentage = maxScroll > 0 ? scrollPosition / maxScroll : 0;
            
            // Calculate thumb width and position
            const thumbWidth = (menu.clientWidth / menu.scrollWidth) * 100;
            const thumbPosition = scrollPercentage * (100 - thumbWidth);
            
            scrollbarThumb.style.width = thumbWidth + '%';
            scrollbarThumb.style.left = thumbPosition + '%';
        }

        // Update fade visibility based on scroll position
        function updateFadeVisibility() {
            const scrollPosition = menu.scrollLeft;
            const maxScroll = menu.scrollWidth - menu.clientWidth;
            const threshold = 5; // Small threshold for edge detection

            // Update start/end classes for button states
            if (scrollPosition <= threshold) {
                container.classList.add('at-start');
                fadeLeft.classList.remove('show');
            } else {
                container.classList.remove('at-start');
                fadeLeft.classList.add('show');
            }

            if (scrollPosition >= maxScroll - threshold) {
                container.classList.add('at-end');
                fadeRight.classList.remove('show');
            } else {
                container.classList.remove('at-end');
                fadeRight.classList.add('show');
            }
            
            // Update custom scrollbar
            updateCustomScrollbar();
        }

        // Scroll by a specific amount
        function scrollBy(amount) {
            menu.scrollBy({
                left: amount,
                behavior: 'smooth'
            });
        }

        // Button click handlers
        btnLeft.addEventListener('click', function(e) {
            e.preventDefault();
            scrollBy(-200); // Scroll left by 200px
        });

        btnRight.addEventListener('click', function(e) {
            e.preventDefault();
            scrollBy(200); // Scroll right by 200px
        });

        // Dragging functionality - works on entire menu including links
        let dragStartX = 0;
        let dragDistance = 0;
        let lastScrollLeft = 0;
        const dragThreshold = 5; // Minimum pixels to consider it a drag

        // Prevent default drag behavior on all links
        menu.addEventListener('dragstart', function(e) {
            e.preventDefault();
            return false;
        });

        // Mouse events
        menu.addEventListener('mousedown', function(e) {
            isDown = true;
            dragStartX = e.pageX;
            dragDistance = 0;
            startX = e.pageX;
            scrollLeft = menu.scrollLeft;
            lastScrollLeft = menu.scrollLeft;
            menu.style.scrollBehavior = 'auto'; // Disable smooth scrolling while dragging
            
            // Prevent default to stop any drag ghost images
            if (e.target.tagName === 'A') {
                e.preventDefault();
            }
        });

        document.addEventListener('mouseleave', function() {
            isDown = false;
            menu.classList.remove('dragging');
            menu.style.scrollBehavior = 'smooth';
        });

        document.addEventListener('mouseup', function() {
            isDown = false;
            menu.classList.remove('dragging');
            menu.style.scrollBehavior = 'smooth';
        });

        document.addEventListener('mousemove', function(e) {
            if (!isDown) return;
            
            e.preventDefault();
            const deltaX = e.pageX - startX;
            dragDistance = Math.abs(e.pageX - dragStartX);
            
            // Only start dragging if moved more than threshold
            if (dragDistance > dragThreshold) {
                menu.classList.add('dragging');
                menu.scrollLeft = scrollLeft - deltaX;
            }
        });

        // Touch events for mobile
        let touchStartX = 0;
        let touchStartScrollLeft = 0;
        let touchDistance = 0;
        
        menu.addEventListener('touchstart', function(e) {
            touchStartX = e.touches[0].pageX;
            touchDistance = 0;
            touchStartScrollLeft = menu.scrollLeft;
        }, { passive: true });

        menu.addEventListener('touchmove', function(e) {
            const touchX = e.touches[0].pageX;
            const deltaX = touchX - touchStartX;
            touchDistance = Math.abs(deltaX);
            
            if (touchDistance > dragThreshold) {
                // Direct scroll without multiplier for more natural touch feel
                menu.scrollLeft = touchStartScrollLeft - deltaX;
            }
        }, { passive: true });

        // Prevent link navigation when dragging
        menu.addEventListener('click', function(e) {
            if (dragDistance > dragThreshold || touchDistance > dragThreshold) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        }, true);

        // Listen to scroll events to update fades
        menu.addEventListener('scroll', updateFadeVisibility);

        // Make custom scrollbar draggable
        let isScrollbarDragging = false;
        let scrollbarStartX = 0;
        let scrollbarStartScroll = 0;

        scrollbarThumb.addEventListener('mousedown', function(e) {
            e.preventDefault();
            isScrollbarDragging = true;
            scrollbarStartX = e.clientX;
            scrollbarStartScroll = menu.scrollLeft;
            document.body.style.userSelect = 'none';
        });

        document.addEventListener('mousemove', function(e) {
            if (!isScrollbarDragging) return;
            
            const deltaX = e.clientX - scrollbarStartX;
            const scrollbarWidth = scrollbarThumb.parentElement.offsetWidth;
            const scrollRatio = menu.scrollWidth / scrollbarWidth;
            
            menu.scrollLeft = scrollbarStartScroll + (deltaX * scrollRatio);
        });

        document.addEventListener('mouseup', function() {
            if (isScrollbarDragging) {
                isScrollbarDragging = false;
                document.body.style.userSelect = '';
            }
        });

        // Initial state check
        updateScrollState();

        // Re-check on window resize
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(updateScrollState, 150);
        });
    }

})(jQuery);












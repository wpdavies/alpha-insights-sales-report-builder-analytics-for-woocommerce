/**
 * Alpha Insights - WordPress Admin Global Scripts
 * Loads on all WordPress admin pages for menu enhancements
 * 
 * @package Alpha Insights
 * @version 4.8.9
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        // Safety check: Ensure we're in WordPress admin
        if (!document.body.classList.contains('wp-admin')) {
            return;
        }

        initThirdLevelMenus();
    }

    /**
     * Add third-level submenus to WordPress admin menu
     * Dynamically creates flyout menus for any parent menu with children
     * Works on all admin pages
     */
    let thirdLevelInitialized = false;
    
    function initThirdLevelMenus() {
        try {
            // Get menu ID from localized data, fallback to default
            let menuId = 'toplevel_page_wpd-sales-reports'; // Default fallback
            
            if (typeof wpdAlphaInsightsMenu !== 'undefined' && wpdAlphaInsightsMenu.topLevelMenuId) {
                menuId = wpdAlphaInsightsMenu.topLevelMenuId;
            }

            // Check if we have the Alpha Insights menu
            const adminMenu = document.getElementById(menuId);
            if (!adminMenu) {
                return;
            }

            const submenu = adminMenu.querySelector('.wp-submenu');
            if (!submenu) {
                return;
            }

            // Prevent duplicate initialization
            if (thirdLevelInitialized) return;

            // Safety: Check if already has third-level menus
            if (submenu.querySelector('.wp-third-level-menu')) {
                thirdLevelInitialized = true;
                return;
            }

            // Ensure the submenu is visible when hovering
            adminMenu.addEventListener('mouseenter', function() {
                if (submenu) {
                    submenu.style.display = 'block';
                }
            });

            // Defer third-level menu fetch to avoid blocking critical dashboard data requests
            // Use requestIdleCallback for better performance, fallback to setTimeout
            const deferMenuFetch = () => fetchAllThirdLevelMenus(submenu);
            
            if ('requestIdleCallback' in window) {
                // Wait until browser is idle (after critical requests complete)
                window.requestIdleCallback(deferMenuFetch, { timeout: 2000 });
            } else {
                // Fallback: delay by 1 second to let dashboard data load first
                setTimeout(deferMenuFetch, 1000);
            }
            
            // Mark as initialized
            thirdLevelInitialized = true;
        } catch (error) {
            console.error('Error initializing third-level menus:', error);
        }
    }

    /**
     * Fetch all third-level menus in a single AJAX request
     */
    function fetchAllThirdLevelMenus(submenu) {
        try {
            // Safety checks
            if (typeof ajaxurl === 'undefined') {
                console.error('ajaxurl not defined');
                return;
            }
            if (typeof wpdAlphaInsightsWordPressAdmin === 'undefined' || !wpdAlphaInsightsWordPressAdmin.nonce) {
                console.error('wpdAlphaInsightsWordPressAdmin or nonce not defined');
                return;
            }

            // Make single AJAX request for all third-level menus
            const data = new FormData();
            data.append('action', 'wpd_get_all_third_level_menus');
            data.append('nonce', wpdAlphaInsightsWordPressAdmin.nonce);

            fetch(ajaxurl, {
                method: 'POST',
                body: data
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(response => {
                if (response && response.success && response.data && response.data.menus) {
                    processThirdLevelMenus(submenu, response.data.menus);
                }
            })
            .catch(error => {
                console.error('Error fetching third-level menus:', error);
            });
        } catch (error) {
            console.error('Error in fetchAllThirdLevelMenus:', error);
        }
    }

    /**
     * Process all third-level menus and attach them to appropriate parent items
     */
    function processThirdLevelMenus(submenu, menusData) {
        try {
            if (!submenu || !menusData || typeof menusData !== 'object') {
                return;
            }

            // Get all menu items in the submenu
            const menuItems = submenu.querySelectorAll('li');
            if (menuItems.length === 0) {
                return;
            }

            let matchCount = 0;

            // Iterate through each menu item to find matches
            menuItems.forEach(function(menuItem) {
                if (!menuItem) return;
                
                const link = menuItem.querySelector('a');
                if (!link) return;

                const linkHref = link.getAttribute('href');
                const linkText = link.textContent.trim();
                if (!linkHref) return;

                // Check each menu in our data to see if this menu item matches
                for (const [parentSlug, menuData] of Object.entries(menusData)) {
                    if (!menuData || !menuData.children || menuData.children.length === 0) continue;

                    // Check if the current menu item's URL matches this parent's URL
                    const parentUrl = menuData.parent_url;
                    if (linkHref.includes('page=' + parentSlug)) {
                        // This menu item should have a third-level menu
                        menuItem.classList.add('wp-has-third-level');
                        buildThirdLevelMenu(menuItem, menuData.children, parentUrl);
                        matchCount++;
                        break; // Found a match, no need to continue
                    }
                }
            });

        } catch (error) {
            console.error('Error processing third-level menus:', error);
        }
    }

    function buildThirdLevelMenu(menuItem, items, parentUrl) {
        try {
            // Safety checks
            if (!menuItem || !Array.isArray(items) || items.length === 0) return;

            // Check if menu already exists
            if (menuItem.querySelector('.wp-third-level-menu')) return;

            // Get the parent submenu
            const parentSubmenu = menuItem.closest('.wp-submenu');
            
            // Create the third-level menu
            const thirdLevelMenu = document.createElement('ul');
            thirdLevelMenu.className = 'wp-third-level-menu';
            
            items.forEach(function(item) {
                // Validate item data
                if (!item || !item.url || !item.title) return;
                
                // Decode HTML entities in URL
                const decodedUrl = decodeHtmlEntities(item.url);
                
                // Hide this item from the second-level menu if it exists there
                if (parentSubmenu) {
                    const existingItems = parentSubmenu.querySelectorAll('li a');
                    existingItems.forEach(function(existingLink) {
                        if (!existingLink) return;
                        
                        const existingHref = existingLink.getAttribute('href');
                        if (!existingHref) return;
                        
                        // Decode existing href for comparison
                        const decodedExistingHref = decodeHtmlEntities(existingHref);
                        
                        // Compare decoded URLs
                        const urlsMatch = decodedExistingHref === decodedUrl || 
                                         decodedExistingHref.includes(decodedUrl) || 
                                         decodedUrl.includes(decodedExistingHref);
                        
                        const parentLi = existingLink.closest('li');
                        if (urlsMatch && parentLi && parentLi !== menuItem) {
                            // Hide this duplicate item from second level
                            parentLi.style.display = 'none';
                            parentLi.classList.add('wpd-hidden-in-second-level');
                        }
                    });
                }
                
                // Create menu item
                const li = document.createElement('li');
                const a = document.createElement('a');
                
                // Use decoded URL
                a.href = decodedUrl;
                a.textContent = item.title;
                
                // Prevent XSS
                a.setAttribute('rel', 'noopener noreferrer');
                
                // Mark current item with exact matching
                try {
                    const currentUrl = decodeHtmlEntities(window.location.href);
                    
                    // Extract subpage parameter from both URLs for exact comparison
                    const getSubpage = function(url) {
                        const match = url.match(/[?&]subpage=([^&]+)/);
                        return match ? match[1] : null;
                    };
                    
                    const currentSubpage = getSubpage(currentUrl);
                    const itemSubpage = getSubpage(decodedUrl);
                    
                    // Check for exact match
                    let isCurrentItem = false;
                    
                    if (currentSubpage && itemSubpage) {
                        // Both have subpage - exact match required
                        isCurrentItem = currentSubpage === itemSubpage;
                    } else if (!currentSubpage && !itemSubpage) {
                        // Neither has subpage - compare full URLs
                        const currentPage = currentUrl.split('?')[0] + '?' + currentUrl.split('?')[1]?.split('&')[0];
                        const itemPage = decodedUrl.split('?')[0] + '?' + decodedUrl.split('?')[1]?.split('&')[0];
                        isCurrentItem = currentPage === itemPage;
                    }
                    
                    if (isCurrentItem) {
                        li.classList.add('current');
                    }
                } catch (e) {
                    // Silently handle URL comparison errors
                }
                
                li.appendChild(a);
                thirdLevelMenu.appendChild(li);
            });
            
            // Only append if we actually created menu items
            if (thirdLevelMenu.children.length > 0) {
                menuItem.appendChild(thirdLevelMenu);
            }
        } catch (error) {
            console.error('Error building third-level menu:', error);
        }
    }

    /**
     * Decode HTML entities in URLs
     * Handles &amp; -> &, &lt; -> <, etc.
     */
    function decodeHtmlEntities(text) {
        if (!text) return text;
        
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        return textarea.value;
    }

    // Initialize with error handling
    try {
        initThirdLevelMenus();
    } catch (error) {
        console.error('Failed to initialize third-level menus:', error);
    }
})();



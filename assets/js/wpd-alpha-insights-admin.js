//https://graphicdesign.stackexchange.com/questions/83866/generating-a-series-of-colors-between-two-colors - RGB
function wpdInterpolateColor(color1, color2, factor) {
    if (arguments.length < 3) { 
        factor = 0.5; 
    }
    var result = color1.slice();
    for (var i = 0; i < 3; i++) {
        result[i] = Math.round(result[i] + factor * (color2[i] - color1[i]));
    }
    var string = "rgb(" + result[0] + ", " + result[1] + ", " + result[2] + ")";
    return string;
};
function wpdColourArray(color1, color2, steps) {
    if ( steps === 1 ) {
        return [color1];
    }
    var stepFactor = 1 / (steps - 1),
        interpolatedColorArray = [];
    color1 = color1.match(/\d+/g).map(Number);
    color2 = color2.match(/\d+/g).map(Number);
    for(var i = 0; i < steps; i++) {
        interpolatedColorArray.push(wpdInterpolateColor(color1, color2, stepFactor * i));
    }
    return interpolatedColorArray;
}
function wpdAddOpacityToArray(item, index, arr) {
    arr[index] = arr[index].replace( ')', ', 0.5)');
}
var wpdPopNotification;
var wpdExtractResponseMessage;
var wpdHandleAjaxResponse;

/**
 * Extract message from AJAX response
 * Handles multiple response formats:
 * - WordPress format: {success: false, data: {message: "..."}}
 * - Custom format: {success: false, message: "..."}
 * - Direct string errors
 */
wpdExtractResponseMessage = function(response, defaultMessage) {
    if (!response) {
        return defaultMessage || 'An unexpected error occurred.';
    }
    
    // Handle WordPress wp_send_json_error format: {success: false, data: {message: "..."}}
    if (response.data && response.data.message) {
        return response.data.message;
    }
    
    // Handle custom format: {success: false, message: "..."}
    if (response.message) {
        return response.message;
    }
    
    // Handle string response
    if (typeof response === 'string') {
        return response;
    }
    
    // Handle array/object error responses
    if (Array.isArray(response)) {
        return response.join(', ');
    }
    
    return defaultMessage || 'An unexpected error occurred.';
};

/**
 * Handle AJAX response and show appropriate notification
 * Handles all response types: success, error, security failures, HTTP errors, parse errors
 */
wpdHandleAjaxResponse = function(response, successMessage, errorMessage) {
    var parsedResponse;
    
    // Handle parse errors
    try {
        if (typeof response === 'string') {
            parsedResponse = JSON.parse(response);
        } else {
            parsedResponse = response;
        }
    } catch (e) {
        if (typeof wpdPopNotification === 'function') {
            wpdPopNotification(
                'fail',
                'Parse Error',
                'The server response could not be understood. Please try again.'
            );
        }
        return;
    }
    
    // Check if response is successful
    if (parsedResponse && parsedResponse.success === true) {
        var message = wpdExtractResponseMessage(parsedResponse, successMessage || 'Your request was completed successfully.');
        if (typeof wpdPopNotification === 'function') {
            wpdPopNotification('success', 'Success!', message);
        }
        return parsedResponse;
    } else {
        // Handle error response
        var errorMsg = wpdExtractResponseMessage(parsedResponse, errorMessage || 'Your action could not be completed.');
        
        // Check for security errors specifically
        if (errorMsg.toLowerCase().indexOf('security') !== -1 || 
            errorMsg.toLowerCase().indexOf('nonce') !== -1 ||
            errorMsg.toLowerCase().indexOf('permission') !== -1) {
            if (typeof wpdPopNotification === 'function') {
                wpdPopNotification(
                    'fail',
                    'Security Check Failed',
                    errorMsg + ' Please refresh the page and try again.'
                );
            }
        } else {
            if (typeof wpdPopNotification === 'function') {
                wpdPopNotification('fail', 'Something Went Wrong', errorMsg);
            }
        }
        return parsedResponse;
    }
};

jQuery(document).ready(function($) {
    jsDateObjectToISO = function( yourDate ) {
        const offset = yourDate.getTimezoneOffset();
        yourDate = new Date(yourDate.getTime() - (offset*60*1000));
        return yourDate.toISOString().split('T')[0];
    }
    setCookie = function( name, value, days ) {
        var expires = "";
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days*24*60*60*1000));
            expires = "; expires=" + date.toUTCString();
            document.cookie = name + "=" + (value || "")  + expires + "; path=/";
        } else {
            document.cookie = name + "=" + (value || "")  + "; path=/";
        }
    }
    wpdPopNotification = function(type, title, subtitle = null) {
        if ( type == 'loading' ) {
            var response = wpdAlphaInsights.processing;
        } else if ( type == 'success' ) {
            var response = wpdAlphaInsights.success;
            var timeout = true;
        } else if ( type == 'fail' ) {
            var response = wpdAlphaInsights.failure;
            var timeout = true;
        }
        $('.wpd-notification-pop-title').text(title);
        $('.wpd-notification-pop-subtitle').text(subtitle);
        $('.wpd-notification-pop-icon').html(response);
        $('.wpd-notification-pop').addClass('active');
       if ( timeout ) {
            window.setTimeout(function(){
              $('.wpd-notification-pop').removeClass("active");
            }, 6000);
        }
    }
    jQuery('.wpd-jquery-datepicker').each(function() {
        var $this = jQuery(this);

        // Default: 10 years in the past, up to now
        var yearRange = "-10:+0";

        // If element has class "future_date", allow 10 years into the future
        if ($this.hasClass('future_date')) {
            yearRange = "-10:+10";
        }

        $this.datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true,
            yearRange: yearRange,
            beforeShow: function(input, inst) {
                jQuery(inst.dpDiv).addClass('wpd');
            },
            onSelect: function(selectedDate, instance) {
                // Clear quick select 
                setCookie('wpd-date-quick-select', null, 1);
                jQuery('.wpd-quick-select-date').removeClass('selected');

                // Set date from/to cookie
                var dateSelector = jQuery(this).attr('name');
                if (dateSelector === 'wpd-report-from-date') {
                    setCookie('wpd-date-from', selectedDate, 1);
                    var toDate = jQuery('.wpd-jquery-datepicker[name="wpd-report-to-date"]').val();
                    setCookie('wpd-date-to', toDate, 1);
                } else if (dateSelector === 'wpd-report-to-date') {
                    setCookie('wpd-date-to', selectedDate, 1);
                    var fromDate = jQuery('.wpd-jquery-datepicker[name="wpd-report-from-date"]').val();
                    setCookie('wpd-date-from', fromDate, 1);
                }
            }
        });
    });
    $('.change-time-display').on('change', function(e) {
        var optionSelected = $("option:selected", this).val();
        if ( optionSelected ) {
            setCookie( 'wpd-date-display', optionSelected, 1 );
        }
    });
    $('.wpd-quick-select-date').click(function() {

        // Selection
        var period = $(this).data('wpd-quick-select');

        // Remove selection classes
        $('.wpd-quick-select-date').removeClass('selected');
        $(this).addClass('selected');

        // Work it out
        var date = new Date();

        if ( period === 'this-month' ) {

            var fromDate = new Date(date.getFullYear(), date.getMonth(), 1);
            var toDate = new Date(date.getFullYear(), date.getMonth() +1, 0);

        } else if ( period === 'last-month' ) {

            var fromDate = new Date(date.getFullYear(), date.getMonth() -1, 1);
            var toDate = new Date(date.getFullYear(), date.getMonth(), 0);

        } else if ( period === 'this-year' ) {

            var fromDate = new Date(date.getFullYear(), 0, 1);
            var toDate = new Date(date.getFullYear(), 11, 31);
            
        } else if ( period === 'last-year' ) {

            var fromDate = new Date(date.getFullYear() -1, 0, 1);
            var toDate = new Date(date.getFullYear() -1, 11, 31);
            
        } else if ( period === 'today' ) {

            var fromDate = toDate = date;
            // var toDate = date;
            
        } else if ( period === 'yesterday' ) {

            var fromDate = toDate = new Date(date.setDate(date.getDate() - 1));
            // var toDate = new Date(date.setDate(date.getDate() - 1));
            
        } else if ( period === 'all-time' ) {

            // Check date exists in global var
            if ( typeof wpdAlphaInsights.site_creation_date !== 'undefined' ) {

                var siteCreationDateString =  wpdAlphaInsights.site_creation_date.replace(/-/g, "/");
                fromDate = new Date( siteCreationDateString );

            } else {

                // Otherwise use 10 years ago
                fromDate = new Date(date.getFullYear() -10, 1, 1);

            }

            var toDate = date;

        }

        // Update dates
        $( 'input[name="wpd-report-from-date"]' ).datepicker( "setDate", fromDate );
        $( 'input[name="wpd-report-to-date"]' ).datepicker( "setDate", toDate );

        // Update cookies
        setCookie( 'wpd-date-from', jsDateObjectToISO( fromDate ), 1 );
        setCookie( 'wpd-date-to', jsDateObjectToISO( toDate ), 1 );

        // Label
        setCookie( 'wpd-date-quick-select', period, 1 );

        // Submit form
        $( '#submit' ).trigger( 'click' );

    });
	// Documentation Modal Handler
    $(".additional-items").click(function (e) {
        e.preventDefault();
        wpdOpenDocsModal();
    });
    $(".wpd-combo-select").easySelect({
        // Options
        showEachItem: true,
        search: true,
        buttons: true,
        dropdownMaxHeight: '300px',
    });
    $(".wpd-single-select").easySelect({
        // Options
        showEachItem: true,
        search: true,
        buttons: false,
        dropdownMaxHeight: '300px',
    });
    $(".wpd-exit-notification-pop").click(function (e) {
        $('.wpd-notification-pop').removeClass("active");
    });
    // Multi COGS Input
    $(document).on('click', '.wpd-add-row', function() {

        // Load vars
        var parentTable     = $(this).closest('table.wpd-variations-multi-cost-price');
        var childIterators  = parentTable.find('.wpd-multi-cogs-row');
        var htmlRow         = childIterators.last().clone();
        var buttonContainer = $(this).closest('tr');
        var currentIterator = htmlRow.data('multi-cogs-iteration') + 1;
        var currentLoop     = htmlRow.data('current-loop');
        var timeStamp       = Math.floor(Date.now() / 1000)
        var date            = new Date;
        var dateFormat      = { day: 'numeric', month: 'long', hour: 'numeric', minute: 'numeric', second: 'numeric' };

        // Modify new container
        htmlRow.attr('data-multi-cogs-iteration', currentIterator); // Update TR data attribute
        htmlRow.find('.multi-cogs-iterator-id').text(currentIterator); // Update Text ID
        
        // Stock input
        htmlRow.find('.multi-cogs-stock-input').val(''); // Update Text ID //multi-cogs-cost-input
        htmlRow.find('.multi-cogs-stock-input').attr('name', 'wpd_ai_product_multi_cogs['+currentLoop+']['+currentIterator+'][qty]'); // Update Text ID //multi-cogs-stock-input
        
        // Cost input
        htmlRow.find('.multi-cogs-cost-input').val(''); // Update Text ID //multi-cogs-cost-input
        htmlRow.find('.multi-cogs-cost-input').attr('name', 'wpd_ai_product_multi_cogs['+currentLoop+']['+currentIterator+'][cogs]'); // Update Text ID //multi-cogs-cost-input

        // Currency selector
        htmlRow.find('.multi-cogs-currency').attr('name', 'wpd_ai_product_multi_cogs['+currentLoop+']['+currentIterator+'][currency]'); // Update Text ID //multi-cogs-cost-input

        // Date / ID
        htmlRow.find('.multi-cogs-hidden-timestamp').val(timeStamp); // Update Text ID //multi-cogs-stock-input
        htmlRow.find('.multi-cogs-hidden-timestamp').attr('name', 'wpd_ai_product_multi_cogs['+currentLoop+']['+currentIterator+'][timestamp-id]'); // Update Text ID //multi-cogs-stock-input
        htmlRow.find('.multi-cogs-date').val(date.toLocaleDateString('en-US', dateFormat)); // Update Text ID //multi-cogs-cost-input

        // Insert new element
        buttonContainer.before(htmlRow);

    });

        // Multi COGS Input
    $(document).on('click', '.wpd-delete-row', function() {

        var htmlRow = $(this).closest('.wpd-multi-cogs-row'); // Remove row
        var numberRowsLeft = $(this).closest('.wpd-variations-multi-cost-price').find('.wpd-multi-cogs-row').length;

        // Dont want to delete the last row.
        if ( numberRowsLeft == 1 ) {
            return false;
        }

        htmlRow.find('.multi-cogs-stock-input').trigger('change');
        htmlRow.remove(); // Remove row

        // WC Save Triggers
        $(this).closest(".woocommerce_variation").addClass("variation-needs-update").remove();
        $("#variable_product_options").trigger("woocommerce_variations_input_changed");
        $("button.cancel-variation-changes, button.save-variation-changes").prop("disabled", !1);

    });

    // Delete Log Files
    $('.wpd-delete-log').click(function(e) {

        // Prevent Default
        e.preventDefault();

        // Collect Data
        let logContainer = $(this).closest('.wpd-debug-container ');
        let logFile = $(this).data('file');
        let ajaxUrl = wpdAlphaInsights.ajax_url;

        // Processing action
        wpdPopNotification( 'loading', 'Processing...', 'We are working on it!' );

        // Payload
        var data = {
            'action': 'wpd_delete_log',
            'url'   : window.location.href,
            'log_file' 	: logFile,
            'nonce' : (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.nonce) ? wpdAlphaInsights.nonce : ''
        };

        // Make Request
        $.post(ajaxUrl, data, function( response ) {

            // Handle both string and object responses
            if (typeof response === 'string') {
                try {
                    response = JSON.parse( response );
                } catch(e) {
                    wpdPopNotification( 'fail', 'Hm, Something Is Not Quite Right', 'Invalid response from server.' );
                    return;
                }
            }

            if ( response.success ) {

                // Delete log text
                logContainer.find('pre').text('');

                // Check for message in response or response.data
                var message = response.message || (response.data && response.data.message) || 'Your request has been succesfully completed.';

                wpdPopNotification( 'success', 'Success!', message );
                
            } else {

                // Handle error response format from wp_send_json_error()
                // Format: {success: false, data: {message: "..."}}
                var errorMessage = (response.data && response.data.message) ? response.data.message : (response.message || 'Your action could not be complete.');
                
                wpdPopNotification( 'fail', 'Hm, Something Is Not Quite Right', errorMessage );

            }
        }).fail(function( jqXHR, textStatus, errorThrown ) {

            // Try to parse error response if available
            var errorMessage = 'Your action could not be complete.';
            if (jqXHR.responseText) {
                try {
                    var errorResponse = JSON.parse(jqXHR.responseText);
                    if (errorResponse.data && errorResponse.data.message) {
                        errorMessage = errorResponse.data.message;
                    } else if (errorResponse.message) {
                        errorMessage = errorResponse.message;
                    }
                } catch(e) {
                    // If parsing fails, use default message
                }
            }

            wpdPopNotification( 'fail', 'Hm, Something Is Not Quite Right', errorMessage );

        });
    });
});

// Sortable Table -> .wpd-sortable-table
jQuery(document).ready(function($) {
    $(function() {
        const ths = $(".wpd-sortable-table thead th.wpd-sortable");
        let sortOrder = 1;

        ths.on("click", function() {
        const rows = sortRows(this);
        rebuildTbody(rows);
        updateClassName(this);
        sortOrder *= -1; //反転
        })

        function sortRows(th) {
        const rows = $.makeArray($('.wpd-sortable-table tbody > tr'));
        const col = th.cellIndex;
        const type = th.dataset.type;
        rows.sort(function(a, b) {
            return compare(a, b, col, type) * sortOrder;      
        });
        return rows;
        }

        function compare(a, b, col, type) {
        let _a = $(a.children[col]).data('val');
        let _b = $(b.children[col]).data('val');
        if (type === "number") {
            _a *= 1;
            _b *= 1;
        } else if (type === "string") {
            //全て小文字に揃えている。toLowerCase()
            _a = _a.toLowerCase();
            _b = _b.toLowerCase();
        }

        if (_a < _b) {
            return -1;
        }
        if (_a > _b) {
            return 1;
        }
        return 0;
        }

        function rebuildTbody(rows) {
        const tbody = $(".wpd-sortable-table tbody");
        while (tbody.firstChild) {
            tbody.remove(tbody.firstChild);
        }

        let j;
        for (j=0; j<rows.length; j++) {
            tbody.append(rows[j]);
        }
        }

        function updateClassName(th) {
        let k;
        for (k=0; k<ths.length; k++) {
            ths[k].classList.remove('asc');
            ths[k].classList.remove('desc');
            ths[k].classList.remove('active');
            // ths[k].className = "wpd-sortable";
        }
        th.className = sortOrder === 1 ? "asc wpd-sortable active" : "desc wpd-sortable active";   
        }
        
    });

    // ====================================
    // Documentation Modal System
    // ====================================
    
    let wpdDocsData = null;
    let wpdDocsLoaded = false;
    let wpdCurrentDoc = null;

    /**
     * Open the documentation modal
     */
    window.wpdOpenDocsModal = function() {
        const $overlay = $('#wpd-docs-modal-overlay');
        $overlay.addClass('active');
        $('body').css('overflow', 'hidden');

        // Load docs if not already loaded
        if (!wpdDocsLoaded) {
            wpdLoadDocumentation();
        }
    };

    /**
     * Close the documentation modal
     */
    function wpdCloseDocsModal() {
        const $overlay = $('#wpd-docs-modal-overlay');
        $overlay.removeClass('active');
        
        // Wait for animation to complete before restoring body scroll
        setTimeout(function() {
            $('body').css('overflow', '');
        }, 300);
    }

    /**
     * Load all documentation via AJAX
     */
    function wpdLoadDocumentation() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpd_load_documentation',
                nonce: (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.nonce) ? wpdAlphaInsights.nonce : ''
            },
            success: function(response) {
                const result = typeof response === 'string' ? JSON.parse(response) : response;
                
                if (result.success) {
                    wpdDocsData = result.data;
                    wpdDocsLoaded = true;
                    wpdRenderDocsSidebar();
                    wpdShowWelcomeScreen();
                } else {
                    wpdShowDocsError(result.message || 'Failed to load documentation.');
                }
            },
            error: function() {
                wpdShowDocsError('Failed to load documentation. Please try again.');
            }
        });
    }

    /**
     * Render the sidebar navigation
     */
    function wpdRenderDocsSidebar() {
        const $nav = $('#wpd-docs-nav');
        $nav.empty();

        if (!wpdDocsData || Object.keys(wpdDocsData).length === 0) {
            $nav.html('<p style="padding: 20px; color: #9ca3af;">No documentation found.</p>');
            return;
        }

        const html = wpdBuildNavItems(wpdDocsData);
        $nav.html(html);

        // Bind folder toggle events
        $('.wpd-docs-folder-header').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation(); // Prevent event bubbling to parent folders
            $(this).parent().toggleClass('open');
        });

        // Bind document click events
        $('.wpd-docs-item-link').on('click', function(e) {
            e.preventDefault();
            const docKey = $(this).data('doc-key');
            const docPath = $(this).data('doc-path');
            wpdShowDocument(docKey, docPath);
            
            // Update active state
            $('.wpd-docs-item-link').removeClass('active');
            $(this).addClass('active');
        });
    }

    /**
     * Build navigation items HTML recursively
     */
    function wpdBuildNavItems(items, parentPath = '') {
        let html = '';

        // Sort items: folders first, then documents, preserving number prefix order
        const sortedKeys = Object.keys(items).sort(function(a, b) {
            const itemA = items[a];
            const itemB = items[b];
            
            // Folders come before documents
            if (itemA.type === 'folder' && itemB.type !== 'folder') {
                return -1;
            }
            if (itemA.type !== 'folder' && itemB.type === 'folder') {
                return 1;
            }
            
            // Within same type, preserve original key order (which respects number prefixes)
            // This maintains the existing sort order that was working
            return 0;
        });

        sortedKeys.forEach(function(key) {
            const item = items[key];
            
            if (item.type === 'folder') {
                html += '<div class="wpd-docs-folder">';
                html += '<div class="wpd-docs-folder-header">';
                html += '<div class="wpd-docs-folder-name">';
                html += '<span class="wpd-docs-folder-icon">📁</span>';
                html += '<span>' + wpdEscapeHtml(item.name) + '</span>';
                html += '</div>';
                html += '<span class="wpd-docs-folder-toggle"></span>';
                html += '</div>';
                html += '<div class="wpd-docs-folder-items">';
                html += wpdBuildNavItems(item.items, item.path);
                html += '</div>';
                html += '</div>';
            } else if (item.type === 'document') {
                html += '<div class="wpd-docs-item">';
                html += '<a href="#" class="wpd-docs-item-link" data-doc-key="' + wpdEscapeHtml(key) + '" data-doc-path="' + wpdEscapeHtml(parentPath) + '">';
                html += wpdEscapeHtml(item.title || key);
                html += '</a>';
                html += '</div>';
            }
        });

        return html;
    }

    /**
     * Show a specific document
     */
    function wpdShowDocument(docKey, docPath) {
        const doc = wpdFindDocument(docKey, wpdDocsData);
        
        if (!doc) {
            wpdShowDocsError('Document not found.');
            return;
        }

        wpdCurrentDoc = doc;
        const $viewer = $('.wpd-docs-viewer');
        
        $viewer.html('<div class="wpd-docs-content">' + doc.content + '</div>');
        
        // Bind internal link navigation
        wpdBindDocumentLinks($viewer);
        
        // Scroll to top
        $viewer.scrollTop(0);
        
        // Expand parent folders and highlight in sidebar
        wpdExpandAndHighlightDocument(docKey);
    }

    /**
     * Bind click handlers to internal documentation links
     */
    function wpdBindDocumentLinks($container) {
        $container.find('a[href^="/documentation/alpha-insights/"]').on('click', function(e) {
            e.preventDefault();
            const href = $(this).attr('href');
            wpdNavigateToDoc(href);
        });
    }

    /**
     * Navigate to a documentation page by its URL
     */
    function wpdNavigateToDoc(url) {
        // Extract path from URL: /documentation/alpha-insights/category/subcategory/filename.html
        // Remove /documentation/alpha-insights/ prefix and .html suffix
        const match = url.match(/\/documentation\/alpha-insights\/(.+)\.html$/);
        
        if (!match) {
            console.warn('Invalid documentation URL:', url);
            return;
        }

        const path = match[1]; // e.g., "getting-started/activate-your-license"
        const parts = path.split('/');
        
        // Try to find the document by matching the path with numeric prefixes
        const doc = wpdFindDocumentByPath(parts, wpdDocsData);
        
        if (doc) {
            // Update the viewer
            wpdCurrentDoc = doc;
            const $viewer = $('.wpd-docs-viewer');
            $viewer.html('<div class="wpd-docs-content">' + doc.content + '</div>');
            
            // Bind links in new content
            wpdBindDocumentLinks($viewer);
            
            // Scroll to top
            $viewer.scrollTop(0);
            
            // Expand parent folders and highlight in sidebar
            const docKey = doc.filename ? doc.filename.replace('.html', '') : null;
            if (docKey) {
                wpdExpandAndHighlightDocument(docKey);
            }
        } else {
            wpdShowDocsError('Documentation page not found: ' + path);
        }
    }

    /**
     * Find a document by its URL path, accounting for numeric prefixes
     * @param {array} pathParts - Array of path segments (e.g., ['getting-started', 'activate-your-license'])
     * @param {object} items - The documentation data object to search
     * @param {number} depth - Current depth in the path (for recursion)
     * @returns {object|null} - The found document or null
     */
    function wpdFindDocumentByPath(pathParts, items, depth = 0) {
        if (depth >= pathParts.length) {
            return null;
        }

        const currentSegment = pathParts[depth];
        const isLastSegment = depth === pathParts.length - 1;

        // Try to find matching item (with or without numeric prefix)
        for (let key in items) {
            const item = items[key];
            
            // Normalize the key by removing numeric prefixes
            // Folder format: 00_getting-started -> getting-started
            // File format: 01-activate-your-license -> activate-your-license
            const normalizedKey = key.replace(/^\d{2}_/, '').replace(/^\d{2}-/, '');
            
            if (normalizedKey === currentSegment) {
                if (isLastSegment) {
                    // This should be a document
                    if (item.type === 'document') {
                        return item;
                    }
                } else {
                    // This should be a folder, continue searching
                    if (item.type === 'folder' && item.items) {
                        const found = wpdFindDocumentByPath(pathParts, item.items, depth + 1);
                        if (found) return found;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Find a document by key in the nested structure
     */
    function wpdFindDocument(key, items) {
        for (let itemKey in items) {
            const item = items[itemKey];
            
            if (itemKey === key && item.type === 'document') {
                return item;
            }
            
            if (item.type === 'folder' && item.items) {
                const found = wpdFindDocument(key, item.items);
                if (found) return found;
            }
        }
        
        return null;
    }

    /**
     * Show welcome screen
     */
    function wpdShowWelcomeScreen() {
        const $viewer = $('.wpd-docs-viewer');
        $viewer.html(
            '<div class="wpd-docs-empty">' +
            '<div class="wpd-docs-empty-icon">📚</div>' +
            '<h3>Welcome to Alpha Insights Documentation</h3>' +
            '<p>Select a topic from the sidebar to get started.</p>' +
            '</div>'
        );
    }

    /**
     * Show error message
     */
    function wpdShowDocsError(message) {
        const $viewer = $('.wpd-docs-viewer');
        $viewer.html(
            '<div class="wpd-docs-empty">' +
            '<div class="wpd-docs-empty-icon">⚠️</div>' +
            '<h3>Error</h3>' +
            '<p>' + wpdEscapeHtml(message) + '</p>' +
            '</div>'
        );
    }

    /**
     * Search functionality
     */
    $('#wpd-docs-search').on('input', function() {
        const query = $(this).val().toLowerCase().trim();
        
        if (query === '') {
            // Show all items in sidebar
            $('.wpd-docs-folder, .wpd-docs-item').show();
            
            // Restore previous view in content area
            if (wpdCurrentDoc) {
                // Show the current document if one was open
                const $viewer = $('.wpd-docs-viewer');
                $viewer.html('<div class="wpd-docs-content">' + wpdCurrentDoc.content + '</div>');
                wpdBindDocumentLinks($viewer);
            } else {
                // Otherwise show the welcome screen
                wpdShowWelcomeScreen();
            }
            return;
        }

        // Search through documents
        wpdSearchDocuments(query);
    });

    /**
     * Search through documents and display results in content area
     */
    function wpdSearchDocuments(query) {
        const results = [];
        const MAX_SNIPPET_LENGTH = 150;
        const CONTEXT_CHARS = 60;

        // Search through all documents
        $('.wpd-docs-item-link').each(function() {
            const $link = $(this);
            const docKey = $link.data('doc-key');
            const doc = wpdFindDocument(docKey, wpdDocsData);

            if (doc) {
                const title = doc.title || '';
                const contentText = $('<div>').html(doc.content).text();
                const titleLower = title.toLowerCase();
                const contentLower = contentText.toLowerCase();
                const queryLower = query.toLowerCase();

                const titleMatches = titleLower.includes(queryLower);
                const contentMatches = contentLower.includes(queryLower);

                if (titleMatches || contentMatches) {
                    // Extract snippets with context
                    const snippets = wpdExtractSnippets(contentText, query, CONTEXT_CHARS, 3);
                    
                    results.push({
                        title: title,
                        docKey: docKey,
                        doc: doc,
                        snippets: snippets,
                        titleMatch: titleMatches
                    });
                }
            }
        });

        // Display results in viewer
        wpdDisplaySearchResults(results, query);
    }

    /**
     * Extract text snippets with context around search matches
     */
    function wpdExtractSnippets(text, query, contextChars, maxSnippets) {
        const snippets = [];
        const queryLower = query.toLowerCase();
        const textLower = text.toLowerCase();
        
        let searchPos = 0;
        let foundCount = 0;

        while (foundCount < maxSnippets) {
            const matchIndex = textLower.indexOf(queryLower, searchPos);
            
            if (matchIndex === -1) {
                break;
            }

            // Calculate snippet boundaries
            let snippetStart = Math.max(0, matchIndex - contextChars);
            let snippetEnd = Math.min(text.length, matchIndex + query.length + contextChars);

            // Try to start at word boundary
            if (snippetStart > 0) {
                const spaceIndex = text.lastIndexOf(' ', matchIndex);
                if (spaceIndex > snippetStart && spaceIndex < matchIndex) {
                    snippetStart = spaceIndex + 1;
                }
            }

            // Try to end at word boundary
            if (snippetEnd < text.length) {
                const spaceIndex = text.indexOf(' ', matchIndex + query.length + contextChars);
                if (spaceIndex !== -1 && spaceIndex < snippetEnd + 20) {
                    snippetEnd = spaceIndex;
                }
            }

            const snippet = text.substring(snippetStart, snippetEnd);
            const prefix = snippetStart > 0 ? '...' : '';
            const suffix = snippetEnd < text.length ? '...' : '';

            snippets.push({
                text: snippet,
                prefix: prefix,
                suffix: suffix
            });

            foundCount++;
            searchPos = matchIndex + query.length;
        }

        // If no matches in content, return empty array
        return snippets;
    }

    /**
     * Highlight search query in text with yellow background
     */
    function wpdHighlightQuery(text, query) {
        if (!query || !text) {
            return wpdEscapeHtml(text);
        }

        const escapedText = wpdEscapeHtml(text);
        const escapedQuery = wpdEscapeHtml(query);
        
        // Create a case-insensitive regex to find all matches
        const regex = new RegExp('(' + escapedQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        
        return escapedText.replace(regex, '<mark style="background-color: #fef08a; padding: 2px 4px; border-radius: 2px;">$1</mark>');
    }

    /**
     * Display search results in the content viewer
     */
    function wpdDisplaySearchResults(results, query) {
        const $viewer = $('.wpd-docs-viewer');

        if (results.length === 0) {
            $viewer.html(
                '<div class="wpd-docs-no-results">' +
                '<h3>No results found</h3>' +
                '<p>No matches found for "' + wpdEscapeHtml(query) + '". Try different keywords or browse the sidebar.</p>' +
                '</div>'
            );
            return;
        }

        let html = '<div class="wpd-docs-search-results">';
        html += '<div class="wpd-docs-search-header">';
        html += '<h2>Search Results</h2>';
        html += '<p>Found ' + results.length + ' result' + (results.length !== 1 ? 's' : '') + ' for "' + wpdEscapeHtml(query) + '"</p>';
        html += '</div>';

        results.forEach(function(result) {
            html += '<div class="wpd-docs-search-result">';
            
            // Title with highlighting if it matches
            html += '<h3 class="wpd-docs-search-result-title">';
            html += '<a href="#" class="wpd-docs-result-link" data-doc-key="' + wpdEscapeHtml(result.docKey) + '">';
            html += wpdHighlightQuery(result.title, query);
            html += '</a>';
            html += '</h3>';

            // Display snippets with highlighted query
            if (result.snippets.length > 0) {
                html += '<div class="wpd-docs-search-snippets">';
                result.snippets.forEach(function(snippet) {
                    html += '<div class="wpd-docs-search-snippet">';
                    html += snippet.prefix;
                    html += wpdHighlightQuery(snippet.text, query);
                    html += snippet.suffix;
                    html += '</div>';
                });
                html += '</div>';
            } else {
                // If no content snippets (title-only match), show beginning of content
                const contentText = $('<div>').html(result.doc.content).text();
                const preview = contentText.substring(0, 150);
                html += '<div class="wpd-docs-search-snippet">';
                html += wpdEscapeHtml(preview) + (contentText.length > 150 ? '...' : '');
                html += '</div>';
            }

            html += '</div>';
        });

        html += '</div>';

        $viewer.html(html);

        // Bind click handlers to result links
        $('.wpd-docs-result-link').on('click', function(e) {
            e.preventDefault();
            const docKey = $(this).data('doc-key');
            const docPath = $(this).data('doc-path') || '';
            
            // Clear search and show all sidebar items
            $('#wpd-docs-search').val('');
            $('.wpd-docs-folder, .wpd-docs-item').show();
            
            // Show the document (this will also expand folders and highlight)
            wpdShowDocument(docKey, docPath);
        });
    }

    /**
     * Expand parent folders and highlight document in sidebar
     */
    function wpdExpandAndHighlightDocument(docKey) {
        // Remove active state from all links
        $('.wpd-docs-item-link').removeClass('active');
        
        // Find the link with this doc key
        const $targetLink = $('.wpd-docs-item-link[data-doc-key="' + docKey + '"]');
        
        if ($targetLink.length) {
            // Add active state
            $targetLink.addClass('active');
            
            // Find and open all parent folders
            $targetLink.parents('.wpd-docs-folder').each(function() {
                $(this).addClass('open');
            });
            
            // Scroll the sidebar to make the link visible
            const $sidebar = $('.wpd-docs-sidebar');
            const linkOffset = $targetLink.offset();
            const sidebarOffset = $sidebar.offset();
            
            if (linkOffset && sidebarOffset) {
                const relativeTop = linkOffset.top - sidebarOffset.top + $sidebar.scrollTop();
                const sidebarHeight = $sidebar.height();
                const linkHeight = $targetLink.outerHeight();
                
                // Scroll if the link is not fully visible
                if (relativeTop < $sidebar.scrollTop() || relativeTop + linkHeight > $sidebar.scrollTop() + sidebarHeight) {
                    $sidebar.animate({
                        scrollTop: relativeTop - (sidebarHeight / 2) + (linkHeight / 2)
                    }, 300);
                }
            }
        }
    }

    /**
     * Escape HTML for security
     */
    function wpdEscapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Close modal on close button click
     */
    $(document).on('click', '.wpd-docs-modal-close', function(e) {
        e.preventDefault();
        wpdCloseDocsModal();
    });

    /**
     * Close modal on overlay click (outside modal)
     */
    $(document).on('click', '.wpd-docs-modal-overlay', function(e) {
        if (e.target === this) {
            wpdCloseDocsModal();
        }
    });

    /**
     * Close modal on Escape key
     */
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#wpd-docs-modal-overlay').hasClass('active')) {
            wpdCloseDocsModal();
        }
    });

});
/**
 * Delete custom order cost
 */
jQuery(document).ready(function($) {
	$('.wpd-delete-custom-order-cost').click(function() {
		let targetRow = $(this).closest('tr').remove();
	});
});
jQuery(document).ready(function($) {
	jQuery('.wpd-data-point').click(function(e) {
		// Prevent anything else
		e.preventDefault();
		// Show pop notification
		wpdPopNotification( 'loading', 'Processing...', 'We are working on it!' );
		// Get value
		let customOrderCostName = jQuery(this).data('val');
		// Some data cleaning
		if ( customOrderCostName.length < 4 ) {
			wpdPopNotification( 'fail', 'Hm, Something Is Not Quite Right', 'We couldnt locate the custom order cost key.');
			return false;
		}
		// Pass in the data
		let data = {
			'action': 'wpd_delete_custom_order_cost',
			'url'   : window.location.href,
			'value' : customOrderCostName,
			'nonce' : (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.nonce) ? wpdAlphaInsights.nonce : ''
		};
        let ajaxUrl = wpdAlphaInsights.ajax_url;
		$.post(ajaxUrl, data)
		.done(function( response ) {
			var parsedResponse = wpdHandleAjaxResponse(
				response,
				'Your request has been successfully completed.',
				'Your action could not be completed.'
			);
			if (parsedResponse && parsedResponse.success) {
				window.postMessage(parsedResponse, "*");
			}
		})
		.fail(function( jqXHR, textStatus, errorThrown ) {
			var errorMessage = 'Your action could not be completed.';
			if (jqXHR.responseText) {
				try {
					var errorResponse = JSON.parse(jqXHR.responseText);
					errorMessage = wpdExtractResponseMessage(errorResponse, errorMessage);
				} catch(e) {
					// If we can't parse the error, use default message
				}
			}
			wpdPopNotification( 'fail', 'Request Failed', errorMessage );
		});
	});
});
jQuery(document).ready(function($) {
    $('.wpd-debug-log-option').click(function() {
        let targetLog = $(this).data('log');
        if ( targetLog ) {
            $('.wpd-debug-log-option').removeClass('active');
            $('.wpd-debug-log-output').hide();
            $('.wpd-debug-log-output.' + targetLog).show();
            $('.wpd-debug-log-option[data-log=\"'+ targetLog +'\"]').addClass('active');
        }
    });

    // Delete custom order cost handler
    $('.wpd-delete-custom-order-cost').click(function() {
        let targetRow = $(this).closest('tr').remove();
    });

    // Delete data point handler
    jQuery('.wpd-data-point').click(function(e) {
        // Prevent anything else
        e.preventDefault();
        
        // Check if localized strings are available
        var processingText = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.strings && wpdAlphaInsights.strings.processing) 
            ? wpdAlphaInsights.strings.processing 
            : 'Processing...';
        var workingText = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.strings && wpdAlphaInsights.strings.working) 
            ? wpdAlphaInsights.strings.working 
            : 'We are working on it!';
        var successText = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.strings && wpdAlphaInsights.strings.success) 
            ? wpdAlphaInsights.strings.success 
            : 'Your request has been successfully completed.';
        var errorText = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.strings && wpdAlphaInsights.strings.error) 
            ? wpdAlphaInsights.strings.error 
            : 'Your action could not be completed.';
        var requestFailedText = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.strings && wpdAlphaInsights.strings.requestFailed) 
            ? wpdAlphaInsights.strings.requestFailed 
            : 'Request Failed';
        var invalidKeyText = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.strings && wpdAlphaInsights.strings.invalidKey) 
            ? wpdAlphaInsights.strings.invalidKey 
            : 'Hm, Something Is Not Quite Right';
        var keyNotFoundText = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.strings && wpdAlphaInsights.strings.keyNotFound) 
            ? wpdAlphaInsights.strings.keyNotFound 
            : 'We couldnt locate the custom order cost key.';
        
        // Show pop notification
        wpdPopNotification( 'loading', processingText, workingText );
        
        // Get value
        let customOrderCostName = jQuery(this).data('val');
        
        // Some data cleaning
        if ( customOrderCostName.length < 4 ) {
            wpdPopNotification( 'fail', invalidKeyText, keyNotFoundText );
            return false;
        }
        
        // Pass in the data
        let data = {
            'action': 'wpd_delete_custom_order_cost',
            'url'   : window.location.href,
            'value' : customOrderCostName,
            'nonce' : (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.nonce) ? wpdAlphaInsights.nonce : ''
        };
        
        var ajaxurl = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.ajax_url) 
            ? wpdAlphaInsights.ajax_url 
            : '/wp-admin/admin-ajax.php';
        
        $.post(ajaxurl, data)
        .done(function( response ) {
            var parsedResponse = wpdHandleAjaxResponse(
                response,
                successText,
                errorText
            );
            if (parsedResponse && parsedResponse.success) {
                window.postMessage(parsedResponse, "*");
            }
        })
        .fail(function( jqXHR, textStatus, errorThrown ) {
            var errorMessage = errorText;
            if (jqXHR.responseText) {
                try {
                    var errorResponse = JSON.parse(jqXHR.responseText);
                    errorMessage = wpdExtractResponseMessage(errorResponse, errorMessage);
                } catch(e) {
                    // If we can't parse the error, use default message
                }
            }
            wpdPopNotification( 'fail', requestFailedText, errorMessage );
        });
    });

    /**
     * Generic AJAX action handler
     * Handles elements with data-wpd-ajax-action attribute
     */
    $(document).on('click', '[data-wpd-ajax-action]', function(e) {
        e.preventDefault();
        
        var $element = $(this);
        var action = $element.data('wpd-ajax-action');
        var formSelector = $element.data('wpd-ajax-form') || 'form';
        
        // Get localized strings
        var processingText = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.strings && wpdAlphaInsights.strings.processing) 
            ? wpdAlphaInsights.strings.processing 
            : 'Processing...';
        var workingText = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.strings && wpdAlphaInsights.strings.working) 
            ? wpdAlphaInsights.strings.working 
            : 'We are working on it!';
        var successText = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.strings && wpdAlphaInsights.strings.success) 
            ? wpdAlphaInsights.strings.success 
            : 'Your request has been successfully completed.';
        var errorText = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.strings && wpdAlphaInsights.strings.error) 
            ? wpdAlphaInsights.strings.error 
            : 'Your action could not be completed.';
        var requestFailedText = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.strings && wpdAlphaInsights.strings.requestFailed) 
            ? wpdAlphaInsights.strings.requestFailed 
            : 'Request Failed';
        
        // Show loading notification
        wpdPopNotification( 'loading', processingText, workingText );
        
        // Serialize form data if form exists
        var formData = [];
        var $form = $(formSelector);
        if ($form.length > 0) {
            formData = $form.serializeArray();
        }
        
        // Prepare AJAX data
        var data = {
            'action': action,
            'url': window.location.href,
            'form': formData,
            'nonce': (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.nonce) ? wpdAlphaInsights.nonce : ''
        };
        
        var ajaxurl = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.ajax_url) 
            ? wpdAlphaInsights.ajax_url 
            : '/wp-admin/admin-ajax.php';
        
        // Make AJAX request
        $.post(ajaxurl, data)
        .done(function( response ) {
            var parsedResponse = wpdHandleAjaxResponse(
                response,
                successText,
                errorText
            );
            if (parsedResponse && parsedResponse.success) {
                window.postMessage(parsedResponse, "*");
            }
        })
        .fail(function( jqXHR, textStatus, errorThrown ) {
            var errorMessage = errorText;
            if (jqXHR.responseText) {
                try {
                    var errorResponse = JSON.parse(jqXHR.responseText);
                    errorMessage = wpdExtractResponseMessage(errorResponse, errorMessage);
                } catch(e) {
                    // If we can't parse the error, use default message
                }
            }
            wpdPopNotification( 'fail', requestFailedText, errorMessage );
        });
    });

    /**
     * Email AJAX handler
     * Handles elements with data-wpd-email-ajax attribute
     */
    $(document).on('click', '[data-wpd-email-ajax]', function(e) {
        e.preventDefault();
        
        var $element = $(this);
        var emailType = $element.data('wpd-email-ajax');
        
        // Get localized strings
        var processingText = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.strings && wpdAlphaInsights.strings.processing) 
            ? wpdAlphaInsights.strings.processing 
            : 'Processing...';
        var workingText = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.strings && wpdAlphaInsights.strings.working) 
            ? wpdAlphaInsights.strings.working 
            : 'We are working on it!';
        var emailSuccessText = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.strings && wpdAlphaInsights.strings.emailSuccess) 
            ? wpdAlphaInsights.strings.emailSuccess 
            : 'Your email has been successfully sent.';
        var emailErrorText = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.strings && wpdAlphaInsights.strings.emailError) 
            ? wpdAlphaInsights.strings.emailError 
            : 'Your email was not sent.';
        var emailFailedText = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.strings && wpdAlphaInsights.strings.emailFailed) 
            ? wpdAlphaInsights.strings.emailFailed 
            : 'Email Failed';
        
        // Show loading notification
        wpdPopNotification( 'loading', processingText, workingText );
        
        // Prepare AJAX data
        var data = {
            'action': 'wpd_send_email',
            'email': emailType,
            'url': window.location.href,
            'nonce': (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.nonce) ? wpdAlphaInsights.nonce : ''
        };
        
        var ajaxurl = (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.ajax_url) 
            ? wpdAlphaInsights.ajax_url 
            : '/wp-admin/admin-ajax.php';
        
        // Make AJAX request
        $.post(ajaxurl, data)
        .done(function( response ) {
            var parsedResponse = wpdHandleAjaxResponse(
                response,
                emailSuccessText,
                emailErrorText
            );
        })
        .fail(function( jqXHR, textStatus, errorThrown ) {
            var errorMessage = emailErrorText;
            if (jqXHR.responseText) {
                try {
                    var errorResponse = JSON.parse(jqXHR.responseText);
                    errorMessage = wpdExtractResponseMessage(errorResponse, errorMessage);
                } catch(e) {
                    // If we can't parse the error, use default message
                }
            }
            wpdPopNotification( 'fail', emailFailedText, errorMessage );
        });
    });
});
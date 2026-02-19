/**
 * Modern Cost of Goods Manager
 * AJAX-based product COGS management with inline editing
 * 
 * @package Alpha Insights
 * @version 4.9.0
 */

(function($) {
    'use strict';

    const CogsManager = {
        currentPage: 1,
        perPage: 25,
        totalPages: 1,
        totalItems: 0,
        filters: {
            search: '',
            category: '',
            supplier: '',
            stock_status: '',
            product_type: '',
            has_cost: ''
        },
        sortBy: 'name',
        sortOrder: 'asc',
        products: [],
        isLoading: false,
        pendingChanges: {}, // Track unsaved changes

        init: function() {
            this.bindEvents();
            this.loadProducts();
            this.createFloatingSaveButton();
            this.createUnsavedBanner();
            this.createImportModal();
            this.createMigrationModal();
            this.createHelpModal();
            this.createBulkEditButton();
            this.createBulkEditModal();
        },

        csvData: null,
        csvHeaders: [],
        importMapping: {
            identifier_column: null,
            identifier_type: 'sku',
            cost_column: null
        },
        selectedProducts: [], // Track selected product IDs for bulk edit

        bindEvents: function() {
            const self = this;

            // Search input with debounce
            let searchTimeout;
            $('#wpd-cogs-search').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    self.filters.search = $('#wpd-cogs-search').val();
                    self.currentPage = 1;
                    self.loadProducts();
                }, 300);
            });

            // Filter changes
            $('.wpd-cogs-filter').on('change', function() {
                const filterName = $(this).data('filter');
                self.filters[filterName] = $(this).val();
                self.currentPage = 1;
                self.loadProducts();
            });

            // Per page change
            $('#wpd-cogs-per-page').on('change', function() {
                self.perPage = parseInt($(this).val());
                self.currentPage = 1;
                self.loadProducts();
            });

            // Clear filters
            $('#wpd-cogs-clear-filters').on('click', function() {
                self.clearFilters();
            });

            // Pagination - using event delegation
            $(document).on('click', '.wpd-cogs-page-btn', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                if (page && page !== self.currentPage) {
                    self.currentPage = page;
                    self.loadProducts();
                }
            });

            // Sort headers
            $(document).on('click', '.wpd-cogs-sortable', function() {
                const column = $(this).data('column');
                if (self.sortBy === column) {
                    self.sortOrder = self.sortOrder === 'asc' ? 'desc' : 'asc';
                } else {
                    self.sortBy = column;
                    self.sortOrder = 'asc';
                }
                self.loadProducts();
            });

            // Inline cost editing - just update calculations, don't save yet
            $(document).on('input', '.wpd-cogs-cost-input', function() {
                const productId = $(this).data('product-id');
                const newCost = $(this).val();
                const originalCost = $(this).data('original');
                const row = $(this).closest('tr');
                
                // Normalize values for comparison
                const currentValue = newCost.trim();
                const originalValue = originalCost === null || originalCost === undefined || originalCost === '' ? '' : String(originalCost);
                
                // Handle international number formats for comparison
                const decimalSep = wpdCogsManager.price_decimal_sep || '.';
                const parseNumber = function(val) {
                    // Handle empty string explicitly - don't convert to 0
                    if (val === '' || val === null || val === undefined) return null;
                    const normalized = decimalSep === ',' ? String(val).replace(',', '.') : String(val);
                    const parsed = parseFloat(normalized);
                    return isNaN(parsed) ? null : parsed;
                };
                
                // Check if changed (properly handle empty vs 0 vs other values)
                let hasChanged = false;
                
                const currentParsed = parseNumber(currentValue);
                const originalParsed = parseNumber(originalValue);
                
                // Both empty - no change
                if (currentParsed === null && originalParsed === null) {
                    hasChanged = false;
                }
                // One is empty, other is not - changed
                else if (currentParsed === null || originalParsed === null) {
                    hasChanged = true;
                }
                // Both have values - compare numerically
                else {
                    hasChanged = currentParsed !== originalParsed;
                }
                
                // Track pending change
                if (hasChanged) {
                    self.pendingChanges[productId] = currentValue; // Store actual value or empty string
                    $(this).addClass('wpd-cogs-changed');
                    row.addClass('wpd-cogs-row-changed'); // Highlight entire row
                } else {
                    delete self.pendingChanges[productId];
                    $(this).removeClass('wpd-cogs-changed');
                    row.removeClass('wpd-cogs-row-changed'); // Remove row highlight
                }
                
                // Update row calculations in real-time
                self.updateRowCalculations(productId, currentValue, row);
                
                // Show/hide floating save button
                self.updateFloatingSaveButton();
            });

            // Enter key on cost input - save changes
            $(document).on('keypress', '.wpd-cogs-cost-input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    self.saveAllChanges();
                }
            });

            // Floating save button
            $(document).on('click', '#wpd-cogs-floating-save', function() {
                self.saveAllChanges();
            });

            // Floating cancel button
            $(document).on('click', '#wpd-cogs-floating-cancel', function() {
                self.cancelAllChanges();
            });

            // Export to CSV
            $('#wpd-cogs-export-csv').on('click', function() {
                self.exportToCSV();
            });

            // Migrate From
            $('#wpd-cogs-migrate-from').on('click', function() {
                self.openMigrationModal();
            });

            // Stats button
            $('#wpd-cogs-stats-btn').on('click', function() {
                self.toggleStats();
            });

            // Help button
            $('#wpd-cogs-help-btn').on('click', function() {
                self.openHelpModal();
            });

            // Import CSV
            $('#wpd-cogs-import-csv').on('click', function() {
                self.openImportModal();
            });

            // Select all checkbox
            $(document).on('change', '#wpd-cogs-select-all', function() {
                const isChecked = $(this).prop('checked');
                $('.wpd-cogs-row-checkbox').prop('checked', isChecked);
                self.updateSelectedProducts();
            });

            // Individual row checkbox
            $(document).on('change', '.wpd-cogs-row-checkbox', function() {
                self.updateSelectedProducts();
                
                // Update select all state
                const totalCheckboxes = $('.wpd-cogs-row-checkbox').length;
                const checkedCheckboxes = $('.wpd-cogs-row-checkbox:checked').length;
                $('#wpd-cogs-select-all').prop('checked', totalCheckboxes === checkedCheckboxes);
            });

            // Bulk edit button
            $(document).on('click', '#wpd-cogs-bulk-edit-btn', function() {
                self.openBulkEditModal();
            });

            // Import modal events
            $(document).on('click', '#wpd-cogs-import-modal-close, #wpd-cogs-import-modal-cancel', function() {
                self.closeImportModal();
            });

            $(document).on('change', '#wpd-cogs-csv-file-input', function(e) {
                self.handleCSVUpload(e.target.files[0]);
            });

            $(document).on('change', '#wpd-cogs-identifier-column, #wpd-cogs-identifier-type, #wpd-cogs-cost-column', function() {
                self.updateImportMapping();
            });

            $(document).on('click', '#wpd-cogs-import-process', function() {
                self.processImport();
            });

            $(document).on('click', '#wpd-cogs-import-done', function() {
                self.closeImportModal();
                self.loadProducts(); // Reload table
            });
        },

        clearFilters: function() {
            this.filters = {
                search: '',
                category: '',
                supplier: '',
                stock_status: '',
                product_type: '',
                has_cost: ''
            };
            $('#wpd-cogs-search').val('');
            $('.wpd-cogs-filter').val('');
            this.currentPage = 1;
            this.loadProducts();
        },

        loadProducts: function() {
            const self = this;
            
            if (self.isLoading) return;
            self.isLoading = true;

            self.showLoading();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpd_get_cogs_products',
                    nonce: wpdCogsManager.nonce,
                    page: self.currentPage,
                    per_page: self.perPage,
                    filters: self.filters,
                    sort_by: self.sortBy,
                    sort_order: self.sortOrder
                },
                success: function(response) {
                    if (response.success) {
                        self.products = response.data.products;
                        self.totalPages = response.data.total_pages;
                        self.totalItems = response.data.total_items;
                        self.renderProducts();
                        self.renderPagination();
                        self.updateStats(response.data.stats);
                    } else {
                        self.showError(response.data.message || 'Failed to load products');
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('Network error: ' + error);
                },
                complete: function() {
                    self.isLoading = false;
                    self.hideLoading();
                }
            });
        },

        renderProducts: function() {
            const self = this;
            const tbody = $('#wpd-cogs-table-body');
            
            if (self.products.length === 0) {
                tbody.html('<tr><td colspan="9" class="wpd-cogs-empty">No products found matching your criteria.</td></tr>');
                return;
            }

            let html = '';
            self.products.forEach(function(product) {
                // For variable products, RRP, sell price, margin, and profit are null (N/A)
                // Margin and profit come from server (calculated from sell price)
                const isVariableProduct = product.type === 'variable';
                const margin = product.margin !== null && product.margin !== undefined ? Number(product.margin).toFixed(1) : null;
                const profit = product.profit !== null && product.profit !== undefined ? product.profit : null;

                const rowClass = product.cost > 0 ? 'has-cost' : 'no-cost';
                const stockClass = product.stock_status === 'instock' ? 'in-stock' : 'out-of-stock';
                const isSelected = self.selectedProducts.includes(product.id);
                const sellPriceData = product.sell_price !== null && product.sell_price !== undefined ? product.sell_price : '';

                html += `
                    <tr class="wpd-cogs-row ${rowClass}" data-product-id="${product.id}" data-sell-price="${sellPriceData}">
                        <td class="wpd-cogs-checkbox-col">
                            <input type="checkbox" class="wpd-cogs-row-checkbox" data-product-id="${product.id}" ${isSelected ? 'checked' : ''}>
                        </td>
                        <td class="wpd-cogs-product">
                            <div class="wpd-cogs-product-content">
                                <div class="wpd-cogs-product-image">
                                    ${product.image ? `<img src="${product.image}" alt="${product.name}">` : '<div class="wpd-cogs-no-image">📦</div>'}
                                </div>
                                <div class="wpd-cogs-product-info">
                                    <div class="wpd-cogs-product-name">${self.escapeHtml(product.name)}</div>
                                    <div class="wpd-cogs-product-meta">
                                        <span class="wpd-cogs-sku">${product.sku || 'No SKU'}</span>
                                        <span class="wpd-cogs-type ${product.type}">${product.type}</span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="wpd-cogs-rrp">${product.rrp !== null && product.rrp !== undefined ? self.formatCurrency(product.rrp) : 'N/A'}</td>
                        <td class="wpd-cogs-sell-price">${product.sell_price !== null && product.sell_price !== undefined ? self.formatCurrency(product.sell_price) : 'N/A'}</td>
                        <td class="wpd-cogs-cost-cell">
                            <div class="wpd-cogs-cost-wrapper">
                                <div class="wpd-cogs-cost-input-wrapper">
                                    <span class="wpd-cogs-currency">${wpdCogsManager.currency_symbol}</span>
                                    <input 
                                        type="text" 
                                        class="wpd-cogs-cost-input" 
                                        data-product-id="${product.id}"
                                        data-original="${product.meta_cost !== null && product.meta_cost !== undefined ? product.meta_cost : ''}"
                                        data-default-cost="${product.default_cost || 0}"
                                        data-default-cost-formatted="${product.default_cost_formatted ? self.escapeHtml(product.default_cost_formatted) : ''}"
                                        value="${product.meta_cost !== null && product.meta_cost !== undefined ? self.formatCostForInput(product.meta_cost) : ''}"
                                        placeholder="Enter cost"
                                        inputmode="decimal"
                                    >
                                    <span class="wpd-cogs-save-indicator" style="display:none;">✓</span>
                                </div>
                                ${product.default_cost_formatted && product.meta_cost === null ? 
                                    '<div class="wpd-cogs-default-text">Default: ' + self.decodeHtmlEntities(product.default_cost_formatted) + '</div>' : 
                                    ''}
                            </div>
                        </td>
                        <td class="wpd-cogs-margin ${margin !== null && margin > 0 ? 'positive' : ''}">${margin !== null ? margin + '%' : 'N/A'}</td>
                        <td class="wpd-cogs-profit ${profit !== null ? (profit > 0 ? 'positive' : profit < 0 ? 'negative' : '') : ''}">${profit !== null && profit !== undefined ? self.formatCurrency(profit) : 'N/A'}</td>
                        <td class="wpd-cogs-stock ${stockClass}">
                            <span class="wpd-cogs-stock-qty">${product.stock_quantity || '-'}</span>
                            <span class="wpd-cogs-stock-status">${product.stock_status}</span>
                        </td>
                        <td class="wpd-cogs-actions">
                            <a href="${product.edit_url}" target="_blank" class="wpd-cogs-edit-btn" title="Edit Product">
                                <span class="dashicons dashicons-edit"></span>
                            </a>
                        </td>
                    </tr>
                `;
            });

            tbody.html(html);
        },

        renderPagination: function() {
            const self = this;
            const container = $('#wpd-cogs-pagination');
            
            // Update toolbar pagination info
            $('#wpd-cogs-current-page').text(self.currentPage);
            $('#wpd-cogs-total-pages').text(self.totalPages);
            $('#wpd-cogs-total-items').text(self.totalItems);
            
            if (self.totalPages <= 1) {
                container.html('');
                return;
            }

            let html = '<div class="wpd-cogs-pagination-wrapper">';
            html += `<span class="wpd-cogs-pagination-info">Page ${self.currentPage} of ${self.totalPages} (${self.totalItems} items)</span>`;
            html += '<div class="wpd-cogs-pagination-buttons">';

            // Previous button
            if (self.currentPage > 1) {
                html += `<button class="wpd-cogs-page-btn" data-page="${self.currentPage - 1}">← Previous</button>`;
            }

            // Page numbers
            const maxButtons = 5;
            let startPage = Math.max(1, self.currentPage - Math.floor(maxButtons / 2));
            let endPage = Math.min(self.totalPages, startPage + maxButtons - 1);
            
            if (endPage - startPage + 1 < maxButtons) {
                startPage = Math.max(1, endPage - maxButtons + 1);
            }

            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === self.currentPage ? 'active' : '';
                html += `<button class="wpd-cogs-page-btn ${activeClass}" data-page="${i}">${i}</button>`;
            }

            // Next button
            if (self.currentPage < self.totalPages) {
                html += `<button class="wpd-cogs-page-btn" data-page="${self.currentPage + 1}">Next →</button>`;
            }

            html += '</div></div>';
            container.html(html);
        },

        cancelAllChanges: function() {
            const self = this;
            
            if (!confirm('Discard all unsaved changes?')) {
                return;
            }

            // Revert all changed inputs to their original values
            Object.keys(self.pendingChanges).forEach(function(productId) {
                const input = $('.wpd-cogs-cost-input[data-product-id="' + productId + '"]');
                const originalCost = input.data('original');
                const row = input.closest('tr');
                
                // Revert value
                if (originalCost) {
                    input.val(self.formatCostForInput(originalCost));
                } else {
                    input.val('');
                }
                
                // Remove changed classes
                input.removeClass('wpd-cogs-changed wpd-cogs-saved wpd-cogs-error');
                row.removeClass('wpd-cogs-row-changed');
                
                // Recalculate with original value
                self.updateRowCalculations(productId, originalCost || 0, row);
            });

            // Clear pending changes
            self.pendingChanges = {};
            
            // Update UI
            self.updateFloatingSaveButton();
        },

        saveAllChanges: function() {
            const self = this;
            const changeCount = Object.keys(self.pendingChanges).length;

            if (changeCount === 0) {
                return;
            }

            // Show progress
            self.showProgress(0, changeCount);

            let completed = 0;
            const errors = [];
            const savedProducts = [];

            // Save each change
            Object.keys(self.pendingChanges).forEach(function(productId) {
                const newCost = self.pendingChanges[productId];
                const inputElement = $('.wpd-cogs-cost-input[data-product-id="' + productId + '"]');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpd_update_product_cost',
                        nonce: wpdCogsManager.nonce,
                        product_id: productId,
                        cost: newCost
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update original value so it's no longer "changed"
                            inputElement.data('original', newCost);
                            
                            // Remove all state classes first
                            inputElement.removeClass('wpd-cogs-changed wpd-cogs-error').addClass('wpd-cogs-saved');
                            
                            // Remove row highlight
                            inputElement.closest('tr').removeClass('wpd-cogs-row-changed');
                            
                            // Track successful save
                            savedProducts.push(productId);
                            
                            // Show green briefly then remove
                            setTimeout(function() {
                                inputElement.removeClass('wpd-cogs-saved');
                            }, 2000);
                        } else {
                            errors.push(productId);
                            inputElement.removeClass('wpd-cogs-changed wpd-cogs-saved').addClass('wpd-cogs-error');
                        }
                    },
                    error: function() {
                        errors.push(productId);
                        inputElement.removeClass('wpd-cogs-changed wpd-cogs-saved').addClass('wpd-cogs-error');
                    },
                    complete: function() {
                        completed++;
                        self.showProgress(completed, changeCount);
                        
                        if (completed === changeCount) {
                            // Remove saved products from pending changes
                            savedProducts.forEach(function(id) {
                                delete self.pendingChanges[id];
                            });
                            
                            setTimeout(function() {
                                self.hideProgress();
                                
                                // Update UI to hide button/banner if no more changes
                                self.updateFloatingSaveButton();
                                
                                // Refresh stats
                                self.refreshStatsOnly();
                                
                                if (errors.length > 0) {
                                    self.showError('Failed to save ' + errors.length + ' product(s). Please try again.');
                                }
                            }, 500);
                        }
                    }
                });
            });
        },

        createFloatingSaveButton: function() {
            // Create container for floating buttons if it doesn't exist
            if ($('#wpd-cogs-floating-buttons').length === 0) {
                $('body').append('<div id="wpd-cogs-floating-buttons"></div>');
            }
            
            if ($('#wpd-cogs-floating-cancel').length === 0) {
                const cancelButton = $('<button type="button" id="wpd-cogs-floating-cancel" class="wpd-btn wpd-btn-secondary" style="display: none;">' +
                    '<span class="dashicons dashicons-no"></span> Cancel Changes' +
                    '</button>');
                $('#wpd-cogs-floating-buttons').append(cancelButton);
            }
            
            if ($('#wpd-cogs-floating-save').length === 0) {
                const saveButton = $('<button type="button" id="wpd-cogs-floating-save" class="wpd-btn wpd-btn-primary" style="display: none;">' +
                    '<span class="dashicons dashicons-yes"></span> Save All Changes <span class="wpd-cogs-change-count"></span>' +
                    '</button>');
                $('#wpd-cogs-floating-buttons').append(saveButton);
            }
        },

        createUnsavedBanner: function() {
            if ($('#wpd-cogs-unsaved-banner').length === 0) {
                const banner = $('<div id="wpd-cogs-unsaved-banner">' +
                    '<span class="dashicons dashicons-warning"></span> ' +
                    'You have <strong><span id="wpd-cogs-unsaved-count">0</span> unsaved change(s)</strong>. ' +
                    'Click "Save All Changes" to update your costs.' +
                    '</div>');
                // Append to the container instead of body
                $('.wpd-cogs-container').append(banner);
            }
        },

        createImportModal: function() {
            if ($('#wpd-cogs-import-modal').length === 0) {
                const modal = $(`
                    <div id="wpd-cogs-import-modal" class="wpd-cogs-modal" style="display: none;">
                        <div class="wpd-cogs-modal-overlay"></div>
                        <div class="wpd-cogs-modal-content">
                            <div class="wpd-cogs-modal-header">
                                <h2>Import Cost of Goods from CSV</h2>
                                <button type="button" id="wpd-cogs-import-modal-close" class="wpd-btn wpd-btn-secondary">&times;</button>
                            </div>
                            <div class="wpd-cogs-modal-body">
                                <div id="wpd-cogs-import-step-1" class="wpd-cogs-import-step">
                                    <h3>Step 1: Upload CSV File</h3>
                                    <p>Upload a CSV file containing your product identifiers (SKU or ID) and cost prices.</p>
                                    <div class="wpd-cogs-file-upload">
                                        <input type="file" id="wpd-cogs-csv-file-input" accept=".csv" style="display: none;">
                                        <button type="button" class="wpd-btn wpd-btn-primary" onclick="document.getElementById('wpd-cogs-csv-file-input').click();">
                                            <span class="dashicons dashicons-upload"></span> Choose CSV File
                                        </button>
                                        <span id="wpd-cogs-file-name" class="wpd-cogs-file-name"></span>
                                    </div>
                                    <div id="wpd-cogs-csv-preview" style="display: none;">
                                        <h4>File Preview (first 5 rows)</h4>
                                        <div class="wpd-cogs-csv-preview-table"></div>
                                    </div>
                                </div>

                                <div id="wpd-cogs-import-step-2" class="wpd-cogs-import-step" style="display: none;">
                                    <h3>Step 2: Map Columns</h3>
                                    <p>Select which columns contain your product identifiers and cost prices.</p>
                                    <div class="wpd-cogs-mapping-form">
                                        <div class="wpd-cogs-form-row">
                                            <label>Product Identifier Column:</label>
                                            <select id="wpd-cogs-identifier-column" class="wpd-input">
                                                <option value="">-- Select Column --</option>
                                            </select>
                                        </div>
                                        <div class="wpd-cogs-form-row">
                                            <label>Identifier Type:</label>
                                            <select id="wpd-cogs-identifier-type" class="wpd-input">
                                                <option value="sku">Product SKU</option>
                                                <option value="id">Product ID</option>
                                            </select>
                                        </div>
                                        <div class="wpd-cogs-form-row">
                                            <label>Cost Price Column:</label>
                                            <select id="wpd-cogs-cost-column" class="wpd-input">
                                                <option value="">-- Select Column --</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div id="wpd-cogs-import-step-3" class="wpd-cogs-import-step" style="display: none;">
                                    <h3>Step 3: Processing...</h3>
                                    <div class="wpd-cogs-import-progress">
                                        <div class="wpd-cogs-progress-text">Processing records...</div>
                                        <div class="wpd-cogs-progress-bar">
                                            <div class="wpd-cogs-progress-fill" style="width: 0%;"></div>
                                        </div>
                                        <div class="wpd-cogs-progress-numbers">0 / 0</div>
                                    </div>
                                </div>

                                <div id="wpd-cogs-import-step-4" class="wpd-cogs-import-step" style="display: none;">
                                    <h3>Import Complete!</h3>
                                    <div class="wpd-cogs-import-results">
                                        <div class="wpd-cogs-result-stat">
                                            <span class="wpd-cogs-result-number" id="wpd-cogs-result-total">0</span>
                                            <span class="wpd-cogs-result-label">Total Records</span>
                                        </div>
                                        <div class="wpd-cogs-result-stat wpd-success">
                                            <span class="wpd-cogs-result-number" id="wpd-cogs-result-updated">0</span>
                                            <span class="wpd-cogs-result-label">Updated</span>
                                        </div>
                                        <div class="wpd-cogs-result-stat wpd-warning">
                                            <span class="wpd-cogs-result-number" id="wpd-cogs-result-skipped">0</span>
                                            <span class="wpd-cogs-result-label">Skipped</span>
                                        </div>
                                        <div class="wpd-cogs-result-stat wpd-error">
                                            <span class="wpd-cogs-result-number" id="wpd-cogs-result-failed">0</span>
                                            <span class="wpd-cogs-result-label">Failed</span>
                                        </div>
                                    </div>
                                    <div id="wpd-cogs-import-log">
                                        <h4>Import Log:</h4>
                                        <div class="wpd-cogs-log-list"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="wpd-cogs-modal-footer">
                                <button type="button" id="wpd-cogs-import-modal-cancel" class="wpd-btn wpd-btn-secondary">Cancel</button>
                                <button type="button" id="wpd-cogs-import-process" class="wpd-btn wpd-btn-primary" style="display: none;">Process Import</button>
                                <button type="button" id="wpd-cogs-import-done" class="wpd-btn wpd-btn-secondary" style="display: none;">Close</button>
                            </div>
                        </div>
                    </div>
                `);
                $('body').append(modal);
            }
        },

        createMigrationModal: function() {
            if ($('#wpd-cogs-migrate-modal').length === 0) {
                const modal = $(`
                    <div id="wpd-cogs-migrate-modal" class="wpd-cogs-modal" style="display: none;">
                        <div class="wpd-cogs-modal-overlay"></div>
                        <div class="wpd-cogs-modal-content">
                            <div class="wpd-cogs-modal-header">
                                <h2>Migrate Cost of Goods Data</h2>
                                <button type="button" class="wpd-btn wpd-btn-secondary" id="wpd-cogs-migrate-close">&times;</button>
                            </div>
                            <div class="wpd-cogs-modal-body">
                                <div class="wpd-cogs-migrate-content">
                                    <div id="wpd-cogs-migrate-step-1">
                                        <p>Migrate your cost of goods data from another plugin or WooCommerce's native COGS feature. Select the source plugin below:</p>
                                        
                                        <div class="wpd-cogs-migrate-selector">
                                            <label>Select Source Plugin:</label>
                                            <select id="wpd-cogs-migrate-source" class="wpd-input">
                                                <option value="">-- Select a plugin --</option>
                                                <option value="_wc_cog_cost">WooCommerce Cost of Goods (SkyVerge)</option>
                                                <option value="_cogs_total_value">WooCommerce 10.0+ (Native COGS)</option>
                                                <option value="_atum_purchase_price">ATUM Inventory Management</option>
                                                <option value="_alg_wc_cog_cost">Cost of Goods for WooCommerce (Algoritmika)</option>
                                                <option value="_yith_cog_cost">YITH Cost of Goods</option>
                                                <option value="_wc_cost_of_good">WC Cost of Goods (Various)</option>
                                                <option value="_purchase_price">Purchase Price (Generic)</option>
                                                <option value="custom">Custom meta key</option>
                                            </select>
                                        </div>

                                        <div id="wpd-cogs-migrate-custom-meta-container" style="display: none; margin-top: 16px;">
                                            <label>Select Custom Meta Key:</label>
                                            <select id="wpd-cogs-migrate-custom-meta" class="wpd-input">
                                                <option value="">-- Loading meta keys --</option>
                                            </select>
                                        </div>

                                        <div id="wpd-cogs-migrate-info-container" style="display: none;">
                                            <div class="wpd-cogs-migrate-info">
                                                <div class="wpd-cogs-migrate-info-title">Products Found</div>
                                                <div class="wpd-cogs-migrate-info-text">
                                                    We found <strong id="wpd-cogs-migrate-count">0</strong> products with cost data from this plugin.
                                                </div>
                                                <div class="wpd-cogs-migrate-info-text">
                                                    Meta key: <code id="wpd-cogs-migrate-meta-key"></code>
                                                </div>
                                            </div>

                                            <div class="wpd-cogs-migrate-samples" id="wpd-cogs-migrate-samples" style="display: none;">
                                                <div class="wpd-cogs-migrate-info-title">Sample Products</div>
                                                <table>
                                                    <thead>
                                                        <tr>
                                                            <th>Product ID</th>
                                                            <th>Product Name</th>
                                                            <th>Cost of Goods</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="wpd-cogs-migrate-samples-body"></tbody>
                                                </table>
                                            </div>

                                            <div class="wpd-cogs-migrate-option">
                                                <label>
                                                    <input type="checkbox" id="wpd-cogs-migrate-overwrite" />
                                                    Overwrite existing costs (by default, only products without costs will be updated)
                                                </label>
                                            </div>

                                            <div class="wpd-cogs-migrate-warning">
                                                <div class="wpd-cogs-migrate-warning-title">⚠️ Important</div>
                                                <div class="wpd-cogs-migrate-info-text">
                                                    This will copy cost data to Alpha Insights. The original data will remain unchanged.
                                                    It's recommended to backup your database before proceeding.
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="wpd-cogs-migrate-step-2" style="display: none;">
                                        <h3>Migration in Progress...</h3>
                                        <div class="wpd-cogs-migrate-progress">
                                            <div class="wpd-cogs-migrate-progress-bar">
                                                <div class="wpd-cogs-migrate-progress-fill" style="width: 0%;">0%</div>
                                            </div>
                                            <div class="wpd-cogs-migrate-status">Please wait...</div>
                                        </div>
                                    </div>

                                    <div id="wpd-cogs-migrate-step-3" style="display: none;">
                                        <h3>Migration Complete!</h3>
                                        <div class="wpd-cogs-migrate-results">
                                            <div class="wpd-cogs-migrate-results-title">✓ Migration Summary</div>
                                            <div class="wpd-cogs-migrate-results-item">
                                                Total products found: <strong id="wpd-cogs-migrate-result-total">0</strong>
                                            </div>
                                            <div class="wpd-cogs-migrate-results-item">
                                                Successfully migrated: <strong id="wpd-cogs-migrate-result-migrated">0</strong>
                                            </div>
                                            <div class="wpd-cogs-migrate-results-item">
                                                Skipped (already had costs): <strong id="wpd-cogs-migrate-result-skipped">0</strong>
                                            </div>
                                        </div>
                                        <div id="wpd-cogs-migrate-errors-container" style="display: none;">
                                            <div class="wpd-cogs-migrate-errors">
                                                <div class="wpd-cogs-migrate-errors-title">Errors</div>
                                                <div id="wpd-cogs-migrate-errors-list"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="wpd-cogs-modal-footer">
                                <button type="button" id="wpd-cogs-migrate-cancel" class="wpd-btn wpd-btn-secondary">Cancel</button>
                                <button type="button" id="wpd-cogs-migrate-start" class="wpd-btn wpd-btn-primary" style="display: none;">Start Migration</button>
                                <button type="button" id="wpd-cogs-migrate-done" class="wpd-btn wpd-btn-primary" style="display: none;">Done</button>
                            </div>
                        </div>
                    </div>
                `);
                $('body').append(modal);

                // Bind modal events
                this.bindMigrationEvents();
            }
        },

        bindMigrationEvents: function() {
            const self = this;

            // Close modal
            $(document).on('click', '#wpd-cogs-migrate-close, #wpd-cogs-migrate-cancel', function() {
                self.closeMigrationModal();
            });

            // Plugin selection change
            $(document).on('change', '#wpd-cogs-migrate-source', function() {
                const selectedValue = $(this).val();
                
                if (selectedValue === 'custom') {
                    // Show custom meta key selector
                    $('#wpd-cogs-migrate-custom-meta-container').show();
                    $('#wpd-cogs-migrate-info-container').hide();
                    $('#wpd-cogs-migrate-start').hide();
                    
                    // Load available meta keys if not already loaded
                    if ($('#wpd-cogs-migrate-custom-meta option').length <= 1) {
                        self.loadAvailableMetaKeys();
                    }
                } else if (selectedValue) {
                    // Hide custom meta key selector
                    $('#wpd-cogs-migrate-custom-meta-container').hide();
                    self.getMigrationCount(selectedValue);
                } else {
                    // Hide everything
                    $('#wpd-cogs-migrate-custom-meta-container').hide();
                    $('#wpd-cogs-migrate-info-container').hide();
                    $('#wpd-cogs-migrate-start').hide();
                }
            });

            // Custom meta key selection change
            $(document).on('change', '#wpd-cogs-migrate-custom-meta', function() {
                const customMetaKey = $(this).val();
                if (customMetaKey) {
                    // Update the source dropdown to use the custom meta key value
                    // We'll store it in a data attribute for the migration
                    $('#wpd-cogs-migrate-source').data('custom-meta-key', customMetaKey);
                    self.getMigrationCount(customMetaKey);
                } else {
                    $('#wpd-cogs-migrate-info-container').hide();
                    $('#wpd-cogs-migrate-start').hide();
                }
            });

            // Start migration
            $(document).on('click', '#wpd-cogs-migrate-start', function() {
                self.startMigration();
            });

            // Done button
            $(document).on('click', '#wpd-cogs-migrate-done', function() {
                self.closeMigrationModal();
                self.loadProducts(); // Refresh the table
            });

            // Close on overlay click
            $(document).on('click', '.wpd-cogs-modal-overlay', function() {
                if ($(this).parent().attr('id') === 'wpd-cogs-migrate-modal') {
                    self.closeMigrationModal();
                }
            });
        },

        openMigrationModal: function() {
            $('#wpd-cogs-migrate-modal').fadeIn(200);
            // Reset modal
            this.resetMigrationModal();
        },

        closeMigrationModal: function() {
            $('#wpd-cogs-migrate-modal').fadeOut(200);
            setTimeout(() => {
                this.resetMigrationModal();
            }, 300);
        },

        resetMigrationModal: function() {
            $('#wpd-cogs-migrate-step-1').show();
            $('#wpd-cogs-migrate-step-2').hide();
            $('#wpd-cogs-migrate-step-3').hide();
            $('#wpd-cogs-migrate-source').val('').removeData('custom-meta-key');
            $('#wpd-cogs-migrate-custom-meta').val('');
            $('#wpd-cogs-migrate-custom-meta-container').hide();
            $('#wpd-cogs-migrate-overwrite').prop('checked', false);
            $('#wpd-cogs-migrate-info-container').hide();
            $('#wpd-cogs-migrate-start').hide();
            $('#wpd-cogs-migrate-done').hide();
            $('#wpd-cogs-migrate-cancel').show();
        },

        getMigrationCount: function(sourceMetaKey) {
            const self = this;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpd_get_migration_count',
                    nonce: wpdCogsManager.nonce,
                    source_meta_key: sourceMetaKey
                },
                success: function(response) {
                    if (response.success) {
                        const count = response.data.count;
                        const samples = response.data.sample_products || [];

                        $('#wpd-cogs-migrate-count').text(count);
                        $('#wpd-cogs-migrate-meta-key').text(sourceMetaKey);
                        $('#wpd-cogs-migrate-info-container').show();

                        if (count > 0) {
                            $('#wpd-cogs-migrate-start').show();

                            // Show samples if available
                            if (samples.length > 0) {
                                let samplesHtml = '';
                                samples.forEach(function(product) {
                                    samplesHtml += `
                                        <tr>
                                            <td>${product.ID}</td>
                                            <td>${self.escapeHtml(product.post_title)}</td>
                                            <td>${self.formatCurrency(product.cost)}</td>
                                        </tr>
                                    `;
                                });
                                $('#wpd-cogs-migrate-samples-body').html(samplesHtml);
                                $('#wpd-cogs-migrate-samples').show();
                            }
                        } else {
                            $('#wpd-cogs-migrate-start').hide();
                            $('#wpd-cogs-migrate-samples').hide();
                        }
                    } else {
                        self.showError(response.data.message || 'Failed to get migration count');
                    }
                },
                error: function() {
                    self.showError('Failed to check migration data');
                }
            });
        },

        loadAvailableMetaKeys: function() {
            const self = this;
            const $select = $('#wpd-cogs-migrate-custom-meta');
            
            $select.html('<option value="">-- Loading meta keys --</option>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpd_get_available_meta_keys',
                    nonce: wpdCogsManager.nonce
                },
                success: function(response) {
                    if (response.success && response.data.meta_keys) {
                        $select.html('<option value="">-- Select a meta key --</option>');
                        response.data.meta_keys.forEach(function(metaKey) {
                            $select.append(`<option value="${self.escapeHtml(metaKey.meta_key)}">${self.escapeHtml(metaKey.meta_key)} (${metaKey.count} products)</option>`);
                        });
                    } else {
                        $select.html('<option value="">-- No meta keys found --</option>');
                        if (response.data && response.data.message) {
                            self.showError(response.data.message);
                        }
                    }
                },
                error: function() {
                    $select.html('<option value="">-- Error loading meta keys --</option>');
                    self.showError('Failed to load available meta keys');
                }
            });
        },

        startMigration: function() {
            const self = this;
            let sourceMetaKey = $('#wpd-cogs-migrate-source').val();
            
            // If custom meta key is selected, use the custom meta key value
            if (sourceMetaKey === 'custom') {
                sourceMetaKey = $('#wpd-cogs-migrate-custom-meta').val();
            }
            
            const overwrite = $('#wpd-cogs-migrate-overwrite').is(':checked');

            if (!sourceMetaKey) {
                self.showError('Please select a source plugin or meta key');
                return;
            }

            // Show progress
            $('#wpd-cogs-migrate-step-1').hide();
            $('#wpd-cogs-migrate-step-2').show();
            $('#wpd-cogs-migrate-cancel').hide();

            // Update progress bar
            $('.wpd-cogs-migrate-progress-fill').css('width', '50%').text('50%');
            $('.wpd-cogs-migrate-status').text('Migrating cost data...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpd_migrate_cogs_data',
                    nonce: wpdCogsManager.nonce,
                    source_meta_key: sourceMetaKey,
                    overwrite: overwrite
                },
                success: function(response) {
                    // Complete progress
                    $('.wpd-cogs-migrate-progress-fill').css('width', '100%').text('100%');
                    
                    if (response.success) {
                        // Show results
                        setTimeout(function() {
                            $('#wpd-cogs-migrate-step-2').hide();
                            $('#wpd-cogs-migrate-step-3').show();
                            $('#wpd-cogs-migrate-done').show();

                            $('#wpd-cogs-migrate-result-total').text(response.data.total_found);
                            $('#wpd-cogs-migrate-result-migrated').text(response.data.migrated_count);
                            $('#wpd-cogs-migrate-result-skipped').text(response.data.skipped_count);

                            // Show errors if any
                            if (response.data.errors && response.data.errors.length > 0) {
                                let errorsHtml = '';
                                response.data.errors.forEach(function(error) {
                                    errorsHtml += `<div class="wpd-cogs-migrate-error-item">${self.escapeHtml(error)}</div>`;
                                });
                                $('#wpd-cogs-migrate-errors-list').html(errorsHtml);
                                $('#wpd-cogs-migrate-errors-container').show();
                            }
                        }, 500);
                    } else {
                        self.showError(response.data.message || 'Migration failed');
                        self.closeMigrationModal();
                    }
                },
                error: function() {
                    self.showError('Migration failed - network error');
                    self.closeMigrationModal();
                }
            });
        },

        createHelpModal: function() {
            if ($('#wpd-cogs-help-modal').length === 0) {
                const defaultCostPercent = wpdCogsManager.default_cost_percent || 0;
                const settingsUrl = wpdCogsManager.settings_url || '#';
                
                const modal = $(`
                    <div id="wpd-cogs-help-modal" class="wpd-cogs-modal" style="display: none;">
                        <div class="wpd-cogs-modal-overlay"></div>
                        <div class="wpd-cogs-modal-content wpd-cogs-help-modal-content">
                            <div class="wpd-cogs-modal-header">
                                <h2>Cost of Goods Help & Documentation</h2>
                                <button type="button" class="wpd-btn wpd-btn-secondary" id="wpd-cogs-help-close">&times;</button>
                            </div>
                            <div class="wpd-cogs-modal-body">
                                <div class="wpd-cogs-help-tabs">
                                    <button class="wpd-cogs-help-tab active" data-tab="calculation">Cost Calculation</button>
                                    <button class="wpd-cogs-help-tab" data-tab="editor">Editor & Bulk Update</button>
                                    <button class="wpd-cogs-help-tab" data-tab="import-export">Import & Export</button>
                                    <button class="wpd-cogs-help-tab" data-tab="migration">Migration Tool</button>
                                </div>
                                <div class="wpd-cogs-help-content">
                                    <!-- Cost Calculation Tab -->
                                    <div class="wpd-cogs-help-panel active" data-panel="calculation">
                                        <h3>How Costs Are Calculated</h3>
                                        <p>Alpha Insights uses a hierarchical system to determine product costs. The system checks each level in order and uses the first available value:</p>
                                        
                                        <div class="wpd-cogs-help-hierarchy">
                                            <div class="wpd-cogs-help-level">
                                                <div class="wpd-cogs-help-level-number">1</div>
                                                <div class="wpd-cogs-help-level-content">
                                                    <h4>Product-Specific Cost</h4>
                                                    <p>If you have set a cost directly on a product using the Cost of Goods Manager or product edit page, that value will always be used. This is stored in the product meta field <code>_wpd_ai_product_cost</code> and has the highest priority.</p>
                                                </div>
                                            </div>
                                            
                                            <div class="wpd-cogs-help-level">
                                                <div class="wpd-cogs-help-level-number">2</div>
                                                <div class="wpd-cogs-help-level-content">
                                                    <h4>WooCommerce Native COGS</h4>
                                                    <p>If no Alpha Insights cost is set, the system checks for WooCommerce's native Cost of Goods Sold (COGS) value. This is stored in the <code>_cogs_total_value</code> meta field (WooCommerce 10.0+).</p>
                                                </div>
                                            </div>
                                            
                                            <div class="wpd-cogs-help-level">
                                                <div class="wpd-cogs-help-level-number">3</div>
                                                <div class="wpd-cogs-help-level-content">
                                                    <h4>Parent Product Cost (Variable Products)</h4>
                                                    <p>For variable products, if no cost is set on the variation, the system checks the parent product for both Alpha Insights cost (<code>_wpd_ai_product_cost</code>) and WooCommerce native COGS (<code>_cogs_total_value</code>). Variations will inherit the parent's cost if available.</p>
                                                </div>
                                            </div>
                                            
                                            <div class="wpd-cogs-help-level">
                                                <div class="wpd-cogs-help-level-number">4</div>
                                                <div class="wpd-cogs-help-level-content">
                                                    <h4>Default Cost Percentage</h4>
                                                    <p>If no specific cost is set at any level, the system uses your default cost percentage setting. This calculates the cost as a percentage of the product's regular retail price (RRP).</p>
                                                    <div class="wpd-cogs-help-setting">
                                                        <strong>Current Setting:</strong> ${defaultCostPercent}% of RRP
                                                        <br><a href="${settingsUrl}" target="_blank">Change in General Settings →</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="wpd-cogs-help-note">
                                            <strong>💡 Tip:</strong> Setting costs at the product level gives you the most accurate profit tracking. Use the default percentage as a fallback for products where you don't have specific cost data.
                                        </div>
                                        
                                        <div class="wpd-cogs-help-note" style="margin-top: 16px; padding: 12px; background: #f0f9ff; border-left: 3px solid #00aff0;">
                                            <strong>📝 Important Note:</strong> You can also update your cost of goods per line item once an order has been placed. This will take hierarchy above all else and override any product-level cost settings for that specific order line item.
                                        </div>
                                    </div>
                                    
                                    <!-- Editor & Bulk Update Tab -->
                                    <div class="wpd-cogs-help-panel" data-panel="editor">
                                        <h3>Using the Editor & Bulk Updater</h3>
                                        
                                        <h4>Inline Editing</h4>
                                        <ul>
                                            <li><strong>Single Product:</strong> Click on any cost field in the table to edit it directly</li>
                                            <li><strong>Save Changes:</strong> Your changes are tracked in real-time. Click the floating "Save Changes" button to commit all edits</li>
                                            <li><strong>Cancel Changes:</strong> Click "Cancel" to revert all unsaved changes</li>
                                            <li><strong>Delete Cost:</strong> Clear a cost field to remove the custom cost and fallback to the default calculation</li>
                                        </ul>
                                        
                                        <h4>Bulk Operations</h4>
                                        <ul>
                                            <li><strong>Select Products:</strong> Use checkboxes to select multiple products</li>
                                            <li><strong>Select All:</strong> Click the checkbox in the header to select all visible products</li>
                                            <li><strong>Bulk Edit:</strong> After selecting products, click "Bulk Edit" to set costs for all selected items at once</li>
                                            <li><strong>Filtering:</strong> Use filters (category, supplier, stock status) to narrow down products before bulk editing</li>
                                        </ul>
                                        
                                        <h4>Filters & Search</h4>
                                        <ul>
                                            <li><strong>Search:</strong> Type product name or SKU in the search box</li>
                                            <li><strong>Category Filter:</strong> Filter by product category</li>
                                            <li><strong>Supplier Filter:</strong> Filter by supplier (if configured)</li>
                                            <li><strong>Stock Status:</strong> Show only in-stock, out-of-stock, or backordered items</li>
                                            <li><strong>Cost Status:</strong> Filter products with or without custom costs set</li>
                                        </ul>
                                    </div>
                                    
                                    <!-- Import & Export Tab -->
                                    <div class="wpd-cogs-help-panel" data-panel="import-export">
                                        <h3>Import & Export Functions</h3>
                                        
                                        <h4>Exporting Costs (CSV)</h4>
                                        <ol>
                                            <li>Click the <strong>"Export CSV"</strong> button in the toolbar</li>
                                            <li>The CSV file will download containing:
                                                <ul>
                                                    <li>Product ID</li>
                                                    <li>Product Name</li>
                                                    <li>SKU</li>
                                                    <li>Product Type</li>
                                                    <li>RRP (Regular Price)</li>
                                                    <li>Sell Price (actual selling price)</li>
                                                    <li>Cost of Goods (if set)</li>
                                                    <li>Margin % (based on sell price)</li>
                                                    <li>Profit (based on sell price)</li>
                                                    <li>Stock Quantity</li>
                                                </ul>
                                            </li>
                                            <li>Use this file for backup or bulk editing in Excel/Sheets</li>
                                        </ol>
                                        
                                        <h4>Importing Costs (CSV)</h4>
                                        <ol>
                                            <li>Click the <strong>"Import CSV"</strong> button</li>
                                            <li><strong>Step 1:</strong> Upload your CSV file</li>
                                            <li><strong>Step 2:</strong> Map columns:
                                                <ul>
                                                    <li>Select which column contains the Product Identifier (SKU or ID)</li>
                                                    <li>Select which column contains the Cost Price</li>
                                                </ul>
                                            </li>
                                            <li><strong>Step 3:</strong> Review and process the import</li>
                                            <li><strong>Step 4:</strong> View results showing updated, skipped, and failed items</li>
                                        </ol>
                                        
                                        <div class="wpd-cogs-help-note">
                                            <strong>📝 CSV Format:</strong> Your CSV should have columns for product identifier (SKU or ID) and cost price. All other columns are optional.
                                        </div>
                                    </div>
                                    
                                    <!-- Migration Tab -->
                                    <div class="wpd-cogs-help-panel" data-panel="migration">
                                        <h3>Using the Migration Tool</h3>
                                        <p>If you're switching from another cost of goods plugin, use the migration tool to copy your existing data:</p>
                                        
                                        <h4>Supported Plugins</h4>
                                        <ul>
                                            <li>WooCommerce Cost of Goods (SkyVerge)</li>
                                            <li>WooCommerce 10.0+ Native COGS</li>
                                            <li>ATUM Inventory Management</li>
                                            <li>Cost of Goods for WooCommerce (Algoritmika)</li>
                                            <li>YITH Cost of Goods</li>
                                            <li>Other generic cost plugins</li>
                                        </ul>
                                        
                                        <h4>Migration Steps</h4>
                                        <ol>
                                            <li>Click the <strong>"Migrate From"</strong> button in the toolbar</li>
                                            <li>Select the source plugin from the dropdown</li>
                                            <li>Review the count of products found with cost data</li>
                                            <li>Choose whether to overwrite existing costs (default: skip products that already have costs)</li>
                                            <li>Click <strong>"Start Migration"</strong></li>
                                            <li>Wait for the process to complete</li>
                                            <li>Review the migration summary showing migrated and skipped products</li>
                                        </ol>
                                        
                                        <div class="wpd-cogs-help-note">
                                            <strong>⚠️ Important:</strong> Migration only copies data - it doesn't delete or modify the original plugin's data. It's recommended to backup your database before running any migration.
                                        </div>
                                        
                                        <div class="wpd-cogs-help-note">
                                            <strong>💡 Tip:</strong> After migration, you can deactivate the old plugin. Alpha Insights will maintain its own copy of the cost data.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="wpd-cogs-modal-footer">
                                <button type="button" id="wpd-cogs-help-done" class="wpd-btn wpd-btn-primary">Got it!</button>
                            </div>
                        </div>
                    </div>
                `);
                $('body').append(modal);
                
                // Bind help modal events
                this.bindHelpModalEvents();
            }
        },

        bindHelpModalEvents: function() {
            const self = this;

            // Close modal
            $(document).on('click', '#wpd-cogs-help-close, #wpd-cogs-help-done', function() {
                self.closeHelpModal();
            });

            // Tab switching
            $(document).on('click', '.wpd-cogs-help-tab', function() {
                const tab = $(this).data('tab');
                
                // Update tab buttons
                $('.wpd-cogs-help-tab').removeClass('active');
                $(this).addClass('active');
                
                // Update panels
                $('.wpd-cogs-help-panel').removeClass('active');
                $(`.wpd-cogs-help-panel[data-panel="${tab}"]`).addClass('active');
            });

            // Close on overlay click
            $(document).on('click', '.wpd-cogs-modal-overlay', function() {
                if ($(this).parent().attr('id') === 'wpd-cogs-help-modal') {
                    self.closeHelpModal();
                }
            });
        },

        openHelpModal: function() {
            $('#wpd-cogs-help-modal').fadeIn(200);
            // Reset to first tab
            $('.wpd-cogs-help-tab').removeClass('active').first().addClass('active');
            $('.wpd-cogs-help-panel').removeClass('active').first().addClass('active');
        },

        closeHelpModal: function() {
            $('#wpd-cogs-help-modal').fadeOut(200);
        },

        toggleStats: function() {
            const statsContainer = $('.wpd-cogs-stats');
            const statsBtn = $('#wpd-cogs-stats-btn');
            
            statsContainer.toggleClass('visible');
            
            // Update button text/state
            if (statsContainer.hasClass('visible')) {
                statsBtn.html('<span class="dashicons dashicons-chart-bar"></span> Hide Stats');
            } else {
                statsBtn.html('<span class="dashicons dashicons-chart-bar"></span> Stats');
            }
        },

        updateFloatingSaveButton: function() {
            const changeCount = Object.keys(this.pendingChanges).length;
            const saveButton = $('#wpd-cogs-floating-save');
            const cancelButton = $('#wpd-cogs-floating-cancel');
            const banner = $('#wpd-cogs-unsaved-banner');
            const buttonsContainer = $('#wpd-cogs-floating-buttons');
            
            if (changeCount > 0) {
                saveButton.find('.wpd-cogs-change-count').text('(' + changeCount + ')');
                saveButton.fadeIn(200);
                cancelButton.fadeIn(200); // Show cancel button
                banner.find('#wpd-cogs-unsaved-count').text(changeCount);
                banner.fadeIn(200);
                buttonsContainer.addClass('with-banner'); // Shift buttons up
            } else {
                saveButton.fadeOut(200);
                cancelButton.fadeOut(200); // Hide cancel button
                banner.fadeOut(200);
                buttonsContainer.removeClass('with-banner'); // Move buttons down
            }
        },

        updateRowCalculations: function(productId, newCost, row) {
            const self = this;
            const sellPriceRaw = row.data('sell-price');
            const sellPrice = sellPriceRaw !== '' && sellPriceRaw !== undefined ? parseFloat(sellPriceRaw) : NaN;

            // Check if this is a variable product (no sell price)
            const isVariableProduct = (sellPriceRaw === '' || sellPriceRaw === undefined || isNaN(sellPrice));

            // For variable products, don't update calculations - keep as N/A
            if (isVariableProduct) {
                row.find('.wpd-cogs-margin').text('N/A').removeClass('positive negative');
                row.find('.wpd-cogs-profit').text('N/A').removeClass('positive negative');
                return;
            }

            const input = row.find('.wpd-cogs-cost-input');
            const costWrapper = row.find('.wpd-cogs-cost-wrapper');

            // Determine which cost to use for calculations
            let cost;
            let isUsingDefault = false;

            if (newCost === '' || newCost === null || newCost === undefined) {
                // Use default cost if input is empty
                const defaultCost = input.data('default-cost');
                cost = parseFloat(defaultCost) || 0;
                isUsingDefault = true;
            } else {
                // Handle international number formats (convert comma to period for parsing)
                const decimalSep = wpdCogsManager.price_decimal_sep || '.';
                const costForParsing = decimalSep === ',' ? newCost.replace(',', '.') : newCost;
                cost = parseFloat(costForParsing) || 0;
            }

            // Calculate margin and profit using sell price
            if (sellPrice > 0 && cost >= 0) {
                const margin = ((sellPrice - cost) / sellPrice * 100).toFixed(1);
                const profit = sellPrice - cost;

                row.find('.wpd-cogs-margin').text(margin + '%').removeClass('positive negative').addClass(margin > 0 ? 'positive' : '');
                row.find('.wpd-cogs-profit').text(this.formatCurrency(profit)).removeClass('positive negative').addClass(profit > 0 ? 'positive' : profit < 0 ? 'negative' : '');
            }

            // Show/hide default text
            const defaultCostFormatted = input.data('default-cost-formatted');
            let existingDefaultText = costWrapper.find('.wpd-cogs-default-text');
            
            if (isUsingDefault && defaultCostFormatted) {
                // Show default text
                if (existingDefaultText.length === 0) {
                    costWrapper.append(`<div class="wpd-cogs-default-text">Default: ${self.decodeHtmlEntities(defaultCostFormatted)}</div>`);
                }
            } else {
                // Hide default text
                existingDefaultText.remove();
            }

            // Update row class
            if (cost > 0 && !isUsingDefault) {
                row.removeClass('no-cost').addClass('has-cost');
            } else {
                row.removeClass('has-cost').addClass('no-cost');
            }
        },


        exportToCSV: function() {
            const params = new URLSearchParams({
                action: 'wpd_export_cogs_csv',
                nonce: wpdCogsManager.nonce,
                filters: JSON.stringify(this.filters),
                sort_by: this.sortBy,
                sort_order: this.sortOrder
            });

            window.location.href = ajaxurl + '?' + params.toString();
        },

        updateStats: function(stats) {
            if (!stats) return;

            $('#wpd-cogs-total-products').text(stats.total_products || 0);
            $('#wpd-cogs-products-with-cost').text(stats.products_with_cost || 0);
            $('#wpd-cogs-products-without-cost').text(stats.products_without_cost || 0);
            $('#wpd-cogs-avg-margin').text((stats.avg_margin || 0).toFixed(1) + '%');
            $('#wpd-cogs-total-stock-value-rrp').text(this.formatCurrency(stats.total_stock_value_rrp || 0));
            $('#wpd-cogs-total-stock-value-sell').text(this.formatCurrency(stats.total_stock_value_sell || 0));
            $('#wpd-cogs-total-stock-value-cost').text(this.formatCurrency(stats.total_stock_value_cost || 0));
        },

        refreshStatsOnly: function() {
            const self = this;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpd_get_cogs_products',
                    nonce: wpdCogsManager.nonce,
                    page: self.currentPage,
                    per_page: self.perPage,
                    filters: self.filters,
                    sort_by: self.sortBy,
                    sort_order: self.sortOrder
                },
                success: function(response) {
                    if (response.success && response.data.stats) {
                        self.updateStats(response.data.stats);
                    }
                },
                error: function() {
                    // Silently fail - stats refresh is not critical
                }
            });
        },

        formatCurrency: function(amount) {
            return wpdCogsManager.currency_symbol + parseFloat(amount).toFixed(2);
        },

        formatCostForInput: function(cost) {
            // Format cost value for input field using WooCommerce decimal settings
            // This respects the store's decimal separator (comma vs period)
            // IMPORTANT: 0 is a valid cost, only null/undefined should return empty
            if (cost === null || cost === undefined || cost === '') return '';
            
            const decimals = wpdCogsManager.price_decimals || 2;
            const decimal_sep = wpdCogsManager.price_decimal_sep || '.';
            
            // Convert to fixed decimals (0 will become "0.00")
            let formatted = parseFloat(cost).toFixed(decimals);
            
            // Replace decimal separator if needed
            if (decimal_sep !== '.') {
                formatted = formatted.replace('.', decimal_sep);
            }
            
            return formatted;
        },

        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        decodeHtmlEntities: function(text) {
            if (!text) return '';
            const textarea = document.createElement('textarea');
            textarea.innerHTML = text;
            return textarea.value;
        },

        showLoading: function() {
            $('#wpd-cogs-table-body').html('<tr><td colspan="9" class="wpd-cogs-loading">Loading products...</td></tr>');
            $('.wpd-cogs-filter, #wpd-cogs-search, #wpd-cogs-per-page').prop('disabled', true);
        },

        hideLoading: function() {
            $('.wpd-cogs-filter, #wpd-cogs-search, #wpd-cogs-per-page').prop('disabled', false);
        },

        showError: function(message) {
            const notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
            $('.wpd-cogs-container').prepend(notice);
            setTimeout(function() {
                notice.fadeOut(function() { $(this).remove(); });
            }, 5000);
        },

        showProgress: function(completed, total) {
            const percent = (completed / total * 100).toFixed(0);
            let progressHtml = `
                <div class="wpd-cogs-progress-overlay">
                    <div class="wpd-cogs-progress-content">
                        <div class="wpd-cogs-progress-text">Saving changes... ${completed} of ${total}</div>
                        <div class="wpd-cogs-progress-bar">
                            <div class="wpd-cogs-progress-fill" style="width: ${percent}%"></div>
                        </div>
                    </div>
                </div>
            `;
            
            if ($('.wpd-cogs-progress-overlay').length === 0) {
                $('body').append(progressHtml);
            } else {
                $('.wpd-cogs-progress-text').text(`Saving changes... ${completed} of ${total}`);
                $('.wpd-cogs-progress-fill').css('width', percent + '%');
            }
        },

        hideProgress: function() {
            $('.wpd-cogs-progress-overlay').fadeOut(function() {
                $(this).remove();
            });
        },

        // ==================== BULK EDIT METHODS ====================

        createBulkEditButton: function() {
            // Create container for floating buttons if it doesn't exist
            if ($('#wpd-cogs-floating-buttons').length === 0) {
                $('body').append('<div id="wpd-cogs-floating-buttons"></div>');
            }
            
            if ($('#wpd-cogs-bulk-edit-btn').length === 0) {
                const button = $('<button type="button" id="wpd-cogs-bulk-edit-btn" class="wpd-btn wpd-btn-primary" style="display: none;">' +
                    '<span class="dashicons dashicons-edit"></span> Bulk Edit <span id="wpd-cogs-bulk-count"></span>' +
                    '</button>');
                $('#wpd-cogs-floating-buttons').append(button);
            }
        },

        updateSelectedProducts: function() {
            const self = this;
            self.selectedProducts = [];
            
            $('.wpd-cogs-row-checkbox:checked').each(function() {
                const productId = parseInt($(this).data('product-id'));
                self.selectedProducts.push(productId);
            });

            // Show/hide bulk edit button
            const bulkButton = $('#wpd-cogs-bulk-edit-btn');
            
            if (self.selectedProducts.length > 0) {
                $('#wpd-cogs-bulk-count').text('(' + self.selectedProducts.length + ')');
                bulkButton.fadeIn(200);
            } else {
                bulkButton.fadeOut(200);
            }
        },

        createBulkEditModal: function() {
            if ($('#wpd-cogs-bulk-edit-modal').length === 0) {
                const modal = $(`
                    <div id="wpd-cogs-bulk-edit-modal" class="wpd-cogs-modal" style="display: none;">
                        <div class="wpd-cogs-modal-overlay"></div>
                        <div class="wpd-cogs-modal-content">
                            <div class="wpd-cogs-modal-header">
                                <h2>Bulk Edit Cost of Goods</h2>
                                <button type="button" class="wpd-btn wpd-btn-secondary" onclick="jQuery('#wpd-cogs-bulk-edit-modal').fadeOut(200);">&times;</button>
                            </div>
                            <div class="wpd-cogs-modal-body">
                                <p>Update costs for <strong id="wpd-bulk-selected-count">0</strong> selected product(s).</p>
                                
                                <div class="wpd-cogs-form-row">
                                    <label>Transformation Type:</label>
                                    <select id="wpd-bulk-transform-type" class="wpd-input">
                                        <option value="set">Set to specific value</option>
                                        <option value="increase_value">Increase by fixed amount</option>
                                        <option value="decrease_value">Decrease by fixed amount</option>
                                        <option value="increase_percent">Increase by percentage</option>
                                        <option value="decrease_percent">Decrease by percentage</option>
                                        <option value="set_margin">Set to achieve target margin %</option>
                                    </select>
                                </div>

                                <div class="wpd-cogs-form-row" id="wpd-bulk-value-row">
                                    <label id="wpd-bulk-value-label">Value:</label>
                                    <div class="wpd-cogs-cost-input-wrapper">
                                        <span class="wpd-cogs-currency" id="wpd-bulk-currency-symbol">${wpdCogsManager.currency_symbol}</span>
                                        <input type="text" id="wpd-bulk-value" class="wpd-cogs-cost-input" placeholder="0.00" inputmode="decimal">
                                    </div>
                                </div>

                                <div class="wpd-cogs-form-row">
                                    <label>
                                        <input type="checkbox" id="wpd-bulk-only-empty" checked>
                                        Only update products without custom cost set
                                    </label>
                                </div>

                                <div id="wpd-bulk-preview" class="wpd-cogs-bulk-preview" style="display: none;">
                                    <h4>Preview Changes:</h4>
                                    <div class="wpd-cogs-bulk-preview-info">
                                        <span>Estimated updates: <strong id="wpd-bulk-preview-count">0</strong></span>
                                    </div>
                                </div>
                            </div>
                            <div class="wpd-cogs-modal-footer">
                                <button type="button" class="wpd-btn wpd-btn-secondary" onclick="jQuery('#wpd-cogs-bulk-edit-modal').fadeOut(200);">Cancel</button>
                                <button type="button" id="wpd-bulk-apply" class="wpd-btn wpd-btn-primary">Apply Changes</button>
                            </div>
                        </div>
                    </div>
                `);
                $('body').append(modal);
            }

            // Bind events for bulk edit modal (outside the if check so they always work)
            const self = this;
            $(document).off('change', '#wpd-bulk-transform-type').on('change', '#wpd-bulk-transform-type', function() {
                self.updateBulkEditUI();
            });

            $(document).off('click', '#wpd-bulk-apply').on('click', '#wpd-bulk-apply', function() {
                self.applyBulkEdit();
            });
        },

        openBulkEditModal: function() {
            if (this.selectedProducts.length === 0) {
                alert('Please select products to edit');
                return;
            }

            $('#wpd-bulk-selected-count').text(this.selectedProducts.length);
            $('#wpd-bulk-transform-type').val('set').trigger('change');
            $('#wpd-bulk-value').val('');
            $('#wpd-bulk-only-empty').prop('checked', false);
            $('#wpd-cogs-bulk-edit-modal').fadeIn(200);
        },

        updateBulkEditUI: function() {
            const transformType = $('#wpd-bulk-transform-type').val();
            const valueLabel = $('#wpd-bulk-value-label');
            const currencySymbol = $('#wpd-bulk-currency-symbol');

            switch(transformType) {
                case 'set':
                    valueLabel.text('New Cost Value:');
                    currencySymbol.show();
                    break;
                case 'increase_value':
                    valueLabel.text('Increase By:');
                    currencySymbol.show();
                    break;
                case 'decrease_value':
                    valueLabel.text('Decrease By:');
                    currencySymbol.show();
                    break;
                case 'increase_percent':
                    valueLabel.text('Increase By (%):');
                    currencySymbol.hide();
                    break;
                case 'decrease_percent':
                    valueLabel.text('Decrease By (%):');
                    currencySymbol.hide();
                    break;
                case 'set_margin':
                    valueLabel.text('Target Margin (%):');
                    currencySymbol.hide();
                    break;
            }
        },

        applyBulkEdit: function() {
            const self = this;
            const transformType = $('#wpd-bulk-transform-type').val();
            const value = parseFloat($('#wpd-bulk-value').val());
            const onlyEmpty = $('#wpd-bulk-only-empty').prop('checked');

            if (isNaN(value) || value < 0) {
                alert('Please enter a valid value');
                return;
            }

            $('#wpd-cogs-bulk-edit-modal').fadeOut(200);

            let updated = 0;
            let skipped = 0;

            self.selectedProducts.forEach(function(productId) {
                // Get product data from current table
                const row = $('tr[data-product-id="' + productId + '"]');
                const input = row.find('.wpd-cogs-cost-input');
                const currentCost = parseFloat(input.data('original')) || 0;
                const sellPriceRaw = row.data('sell-price');
                const sellPrice = sellPriceRaw !== '' && sellPriceRaw !== undefined ? parseFloat(sellPriceRaw) : 0;

                // Check if we should skip (only empty filter)
                if (onlyEmpty && currentCost > 0) {
                    skipped++;
                    return;
                }

                // Calculate new cost based on transformation
                let newCost = 0;
                switch(transformType) {
                    case 'set':
                        newCost = value;
                        break;
                    case 'increase_value':
                        newCost = currentCost + value;
                        break;
                    case 'decrease_value':
                        newCost = Math.max(0, currentCost - value);
                        break;
                    case 'increase_percent':
                        newCost = currentCost * (1 + value / 100);
                        break;
                    case 'decrease_percent':
                        newCost = currentCost * (1 - value / 100);
                        break;
                    case 'set_margin':
                        // Calculate cost to achieve target margin using sell price
                        // margin = (sell_price - cost) / sell_price * 100
                        // cost = sell_price * (1 - margin / 100)
                        if (sellPrice > 0) {
                            newCost = sellPrice * (1 - value / 100);
                        } else {
                            skipped++;
                            return;
                        }
                        break;
                }

                newCost = Math.max(0, newCost); // Ensure non-negative

                // Update input value (don't save yet - just populate)
                input.val(self.formatCostForInput(newCost));
                
                // Mark as changed
                if (parseFloat(newCost) !== parseFloat(input.data('original'))) {
                    self.pendingChanges[productId] = newCost;
                    input.addClass('wpd-cogs-changed');
                    row.addClass('wpd-cogs-row-changed');
                    updated++;
                }
                
                // Update row calculations in real-time
                self.updateRowCalculations(productId, newCost, row);
            });

            // Clear selections
            self.selectedProducts = [];
            $('.wpd-cogs-row-checkbox').prop('checked', false);
            $('#wpd-cogs-select-all').prop('checked', false);
            $('#wpd-cogs-bulk-edit-btn').fadeOut(200);

            // Show floating save button if there are changes
            self.updateFloatingSaveButton();
        },

        // ==================== CSV IMPORT METHODS ====================

        openImportModal: function() {
            this.resetImportModal();
            $('#wpd-cogs-import-modal').fadeIn(200);
        },

        closeImportModal: function() {
            $('#wpd-cogs-import-modal').fadeOut(200);
            this.resetImportModal();
        },

        resetImportModal: function() {
            this.csvData = null;
            this.csvHeaders = [];
            this.importMapping = {
                identifier_column: null,
                identifier_type: 'sku',
                cost_column: null
            };
            
            $('#wpd-cogs-import-step-1').show();
            $('#wpd-cogs-import-step-2, #wpd-cogs-import-step-3, #wpd-cogs-import-step-4').hide();
            $('#wpd-cogs-csv-preview').hide();
            $('#wpd-cogs-file-name').text('');
            $('#wpd-cogs-csv-file-input').val('');
            $('#wpd-cogs-import-process').hide();
            $('#wpd-cogs-import-done').hide();
            $('#wpd-cogs-import-modal-cancel').show();
            $('.wpd-cogs-log-list').empty();
        },

        handleCSVUpload: function(file) {
            const self = this;
            
            if (!file) return;

            $('#wpd-cogs-file-name').text(file.name);

            const reader = new FileReader();
            reader.onload = function(e) {
                const csv = e.target.result;
                self.parseCSV(csv);
            };
            reader.readAsText(file);
        },

        parseCSV: function(csv) {
            const self = this;
            const lines = csv.split('\n').filter(line => line.trim());
            
            if (lines.length < 2) {
                alert('CSV file must contain at least a header row and one data row.');
                return;
            }

            // Parse headers
            const headers = self.parseCSVLine(lines[0]);
            self.csvHeaders = headers;

            // Parse data rows
            const data = [];
            for (let i = 1; i < lines.length; i++) {
                const row = self.parseCSVLine(lines[i]);
                if (row.length === headers.length) {
                    const rowObj = {};
                    headers.forEach((header, index) => {
                        rowObj[header] = row[index];
                    });
                    data.push(rowObj);
                }
            }

            self.csvData = data;

            // Show preview
            self.showCSVPreview(headers, data.slice(0, 5));

            // Show mapping step
            self.showMappingStep();
        },

        parseCSVLine: function(line) {
            // Simple CSV parser - handles quoted fields
            const result = [];
            let current = '';
            let inQuotes = false;

            for (let i = 0; i < line.length; i++) {
                const char = line[i];

                if (char === '"') {
                    inQuotes = !inQuotes;
                } else if (char === ',' && !inQuotes) {
                    result.push(current.trim());
                    current = '';
                } else {
                    current += char;
                }
            }

            result.push(current.trim());
            return result;
        },

        showCSVPreview: function(headers, rows) {
            let html = '<table class="wpd-cogs-preview-table"><thead><tr>';
            headers.forEach(header => {
                html += '<th>' + this.escapeHtml(header) + '</th>';
            });
            html += '</tr></thead><tbody>';

            rows.forEach(row => {
                html += '<tr>';
                headers.forEach(header => {
                    html += '<td>' + this.escapeHtml(row[header] || '') + '</td>';
                });
                html += '</tr>';
            });

            html += '</tbody></table>';
            $('.wpd-cogs-csv-preview-table').html(html);
            $('#wpd-cogs-csv-preview').fadeIn();
        },

        showMappingStep: function() {
            const self = this;

            // Populate column dropdowns
            let options = '<option value="">-- Select Column --</option>';
            self.csvHeaders.forEach((header, index) => {
                options += '<option value="' + index + '">' + self.escapeHtml(header) + '</option>';
            });

            $('#wpd-cogs-identifier-column, #wpd-cogs-cost-column').html(options);

            // Try to auto-detect columns
            self.autoDetectColumns();

            // Show step 2
            $('#wpd-cogs-import-step-1').hide();
            $('#wpd-cogs-import-step-2').show();
        },

        autoDetectColumns: function() {
            const self = this;
            
            // Try to find SKU column
            const skuIndex = self.csvHeaders.findIndex(h => 
                h.toLowerCase().includes('sku') || h.toLowerCase() === 'sku'
            );
            if (skuIndex !== -1) {
                $('#wpd-cogs-identifier-column').val(skuIndex);
                self.importMapping.identifier_column = skuIndex;
            }

            // Try to find ID column
            const idIndex = self.csvHeaders.findIndex(h => 
                h.toLowerCase().includes('product') && h.toLowerCase().includes('id') ||
                h.toLowerCase() === 'id' || h.toLowerCase() === 'product_id'
            );
            if (idIndex !== -1 && skuIndex === -1) {
                $('#wpd-cogs-identifier-column').val(idIndex);
                $('#wpd-cogs-identifier-type').val('id');
                self.importMapping.identifier_column = idIndex;
                self.importMapping.identifier_type = 'id';
            }

            // Try to find cost column
            const costIndex = self.csvHeaders.findIndex(h => 
                h.toLowerCase().includes('cost') || 
                h.toLowerCase().includes('price') ||
                h.toLowerCase().includes('cog')
            );
            if (costIndex !== -1) {
                $('#wpd-cogs-cost-column').val(costIndex);
                self.importMapping.cost_column = costIndex;
            }

            // Update UI
            self.updateImportMapping();
        },

        updateImportMapping: function() {
            const self = this;
            
            self.importMapping.identifier_column = parseInt($('#wpd-cogs-identifier-column').val());
            self.importMapping.identifier_type = $('#wpd-cogs-identifier-type').val();
            self.importMapping.cost_column = parseInt($('#wpd-cogs-cost-column').val());

            // Show process button if mapping is complete
            const isValid = !isNaN(self.importMapping.identifier_column) && 
                           !isNaN(self.importMapping.cost_column) &&
                           self.importMapping.identifier_column !== self.importMapping.cost_column;

            if (isValid) {
                $('#wpd-cogs-import-process').show();
            } else {
                $('#wpd-cogs-import-process').hide();
            }
        },

        processImport: function() {
            const self = this;

            if (!self.csvData || self.csvData.length === 0) {
                alert('No data to import');
                return;
            }

            // Validate mapping
            if (isNaN(self.importMapping.identifier_column) || isNaN(self.importMapping.cost_column)) {
                alert('Please complete the column mapping');
                return;
            }

            // Confirm import
            if (!confirm('Import ' + self.csvData.length + ' records? This will update product costs based on your CSV data.')) {
                return;
            }

            // Show progress step
            $('#wpd-cogs-import-step-2').hide();
            $('#wpd-cogs-import-step-3').show();
            $('#wpd-cogs-import-modal-cancel').hide();

            // Process rows
            const results = {
                total: self.csvData.length,
                updated: 0,
                skipped: 0,
                failed: 0,
                log: [] // Changed from errors to log to include all activities
            };

            let processed = 0;

            self.csvData.forEach(function(row, index) {
                const identifier = row[self.csvHeaders[self.importMapping.identifier_column]];
                const costValue = row[self.csvHeaders[self.importMapping.cost_column]];
                const rowNumber = index + 1; // Add 1 for 0-based index (header already excluded from csvData)

                // Check for missing values with specific messages
                if (!identifier && !costValue) {
                    results.failed++;
                    results.log.push({
                        type: 'wpd-error',
                        row: rowNumber,
                        identifier: 'N/A',
                        message: 'Missing both identifier and cost value'
                    });
                    processed++;
                    self.updateImportProgress(processed, results.total);
                    
                    if (processed === results.total) {
                        self.showImportResults(results);
                    }
                    return;
                } else if (!identifier) {
                    results.failed++;
                    results.log.push({
                        type: 'wpd-error',
                        row: rowNumber,
                        identifier: 'N/A',
                        message: 'Missing ' + (self.importMapping.identifier_type === 'sku' ? 'SKU' : 'Product ID')
                    });
                    processed++;
                    self.updateImportProgress(processed, results.total);
                    
                    if (processed === results.total) {
                        self.showImportResults(results);
                    }
                    return;
                } else if (!costValue) {
                    results.skipped++;
                    results.log.push({
                        type: 'skipped',
                        row: rowNumber,
                        identifier: identifier,
                        message: 'No cost value found, skipping row'
                    });
                    processed++;
                    self.updateImportProgress(processed, results.total);
                    
                    if (processed === results.total) {
                        self.showImportResults(results);
                    }
                    return;
                }

                // Send AJAX request to update cost
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpd_import_product_cost',
                        nonce: wpdCogsManager.nonce,
                        identifier: identifier,
                        identifier_type: self.importMapping.identifier_type,
                        cost: costValue
                    },
                    success: function(response) {
                        if (response.success) {
                            results.updated++;
                            results.log.push({
                                type: 'success',
                                row: rowNumber,
                                identifier: identifier,
                                cost: costValue,
                                message: 'Cost updated to ' + costValue
                            });
                        } else {
                            results.failed++;
                            // Use the specific error message from server
                            const errorMsg = response.data && response.data.message ? response.data.message : 'Update failed';
                            results.log.push({
                                type: 'wpd-error',
                                row: rowNumber,
                                identifier: identifier,
                                message: errorMsg
                            });
                        }
                    },
                    error: function() {
                        results.failed++;
                        results.log.push({
                            type: 'wpd-error',
                            row: rowNumber,
                            identifier: identifier,
                            message: 'Network error'
                        });
                    },
                    complete: function() {
                        processed++;
                        self.updateImportProgress(processed, results.total);
                        
                        if (processed === results.total) {
                            self.showImportResults(results);
                        }
                    }
                });
            });
        },

        updateImportProgress: function(current, total) {
            const percent = (current / total * 100).toFixed(0);
            $('#wpd-cogs-import-step-3 .wpd-cogs-progress-fill').css('width', percent + '%');
            $('#wpd-cogs-import-step-3 .wpd-cogs-progress-numbers').text(current + ' / ' + total);
        },

        showImportResults: function(results) {
            const self = this;
            
            $('#wpd-cogs-import-step-3').hide();
            $('#wpd-cogs-import-step-4').show();

            $('#wpd-cogs-result-total').text(results.total);
            $('#wpd-cogs-result-updated').text(results.updated);
            $('#wpd-cogs-result-skipped').text(results.skipped);
            $('#wpd-cogs-result-failed').text(results.failed);

            // Build import log
            let logHtml = '';
            
            if (results.log && results.log.length > 0) {
                // Sort log by row number
                results.log.sort((a, b) => a.row - b.row);
                
                // Get identifier label once to avoid repeated access
                const identifierLabel = (self.importMapping && self.importMapping.identifier_type === 'sku') ? 'SKU' : 'ID';
                
                results.log.forEach(function(entry) {
                    let iconHtml = '';
                    let rowClass = 'wpd-cogs-log-item';
                    
                    if (entry.type === 'success') {
                        iconHtml = '<span class="wpd-cogs-log-icon success">✓</span>';
                        rowClass += ' success';
                    } else if (entry.type === 'skipped') {
                        iconHtml = '<span class="wpd-cogs-log-icon skipped">⊘</span>';
                        rowClass += ' skipped';
                    } else if (entry.type === 'wpd-error') {
                        iconHtml = '<span class="wpd-cogs-log-icon wpd-error">✕</span>';
                        rowClass += ' wpd-error';
                    }
                    
                    const identifierText = entry.identifier !== 'N/A' ? entry.identifier : '';
                    
                    logHtml += '<div class="' + rowClass + '">';
                    logHtml += iconHtml;
                    logHtml += '<div class="wpd-cogs-log-content">';
                    logHtml += '<div class="wpd-cogs-log-header">';
                    logHtml += '<span class="wpd-cogs-log-row">Row ' + entry.row + '</span>';
                    if (identifierText) {
                        logHtml += '<span class="wpd-cogs-log-identifier">' + identifierLabel + ': ' + self.escapeHtml(identifierText) + '</span>';
                    }
                    logHtml += '</div>';
                    logHtml += '<div class="wpd-cogs-log-message">' + self.escapeHtml(entry.message) + '</div>';
                    logHtml += '</div>';
                    logHtml += '</div>';
                });
            } else {
                logHtml = '<div class="wpd-cogs-log-empty">No issues to report - all imports were successful!</div>';
            }
            
            $('.wpd-cogs-log-list').html(logHtml);

            // Hide Cancel and Process buttons, show Close button
            $('#wpd-cogs-import-modal-cancel').hide();
            $('#wpd-cogs-import-process').hide();
            $('#wpd-cogs-import-done').show();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#wpd-cogs-manager').length > 0) {
            CogsManager.init();
        }
    });

})(jQuery);


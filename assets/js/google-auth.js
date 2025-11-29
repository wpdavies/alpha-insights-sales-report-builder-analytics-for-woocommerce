/**
 * Google Ads Authentication Handler
 * Modern modal-based authentication flow
 */
(function($) {
    'use strict';
    
    const WPD_Google_Auth = {
        modal: null,
        disconnectModal: null,
        authWindow: null,
        authState: null,
        tempAuthData: null,
        selectedAccount: null,
        
        /**
         * Initialize
         */
        init: function() {
            this.modal = $('#wpd-google-auth-modal');
            this.disconnectModal = $('#wpd-google-disconnect-modal');
            this.bindEvents();
            this.setupPostMessageListener();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;
            
            // Connect button
            $(document).on('click', '#wpd-google-connect-btn', function(e) {
                e.preventDefault();
                self.openAuthModal();
            });
            
            // Modal close buttons
            $(document).on('click', '.wpd-google-modal-overlay, .wpd-google-modal-close, .wpd-google-cancel-btn, .wpd-google-cancel-auth-btn', function(e) {
                e.preventDefault();
                self.closeAuthModal();
            });
            
            // Done button (on complete and fetch-error steps)
            $(document).on('click', '.wpd-google-done-btn', function(e) {
                e.preventDefault();
                // Reload page to show connection status
                location.reload();
            });
            
            // Stop propagation on modal content
            $(document).on('click', '.wpd-google-modal-content', function(e) {
                e.stopPropagation();
            });
            
            // Authorize button
            $(document).on('click', '.wpd-google-authorize-btn', function(e) {
                e.preventDefault();
                self.startAuthFlow();
            });
            
            // Ad account selection
            $(document).on('click', '.wpd-google-account-item', function(e) {
                e.preventDefault();
                self.selectAccount($(this));
            });
            
            // Save account button
            $(document).on('click', '.wpd-google-save-account-btn', function(e) {
                e.preventDefault();
                self.saveSelectedAccount();
            });
            
            // Retry button
            $(document).on('click', '.wpd-google-retry-btn', function(e) {
                e.preventDefault();
                self.resetToInitialStep();
            });
            
            // Disconnect button
            $(document).on('click', '#wpd-google-disconnect-btn', function(e) {
                e.preventDefault();
                self.openDisconnectModal();
            });
            
            // Fetch All Data button
            $(document).on('click', '.wpd-google-fetch-data-btn', function(e) {
                e.preventDefault();
                self.fetchAllData();
            });
            
            // Disconnect modal actions
            $(document).on('click', '.wpd-google-cancel-disconnect-btn', function(e) {
                e.preventDefault();
                self.closeDisconnectModal();
            });
            
            $(document).on('click', '.wpd-google-confirm-disconnect-btn', function(e) {
                e.preventDefault();
                self.confirmDisconnect();
            });
            
            // ESC key to close
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.modal.is(':visible')) {
                    self.closeAuthModal();
                }
                if (e.key === 'Escape' && self.disconnectModal.is(':visible')) {
                    self.closeDisconnectModal();
                }
            });
        },
        
        /**
         * Setup postMessage listener for auth response from wpdavies.dev
         */
        setupPostMessageListener: function() {
            const self = this;
            
            window.addEventListener('message', function(event) {
                console.log('[Google Auth] Received postMessage:', {
                    origin: event.origin,
                    data: event.data
                });
                
                // Validate origin (wpdavies.dev)
                if (event.origin !== 'https://wpdavies.dev') {
                    console.log('[Google Auth] Ignoring message from:', event.origin);
                    return;
                }
                
                // Check if message is for Google Ads auth
                if (event.data && event.data.target === 'alpha-insights-google') {
                    console.log('[Google Auth] Processing auth response:', event.data);
                    self.handleAuthResponse(event.data);
                } else {
                    console.log('[Google Auth] Message not for Google Ads auth:', event.data);
                }
            });
        },

        /**
         * Handle authentication response from wpdavies.dev postMessage
         */
        handleAuthResponse: function(response) {
            console.log('[Google Auth] Handling auth response:', response);
            
            // Try to close auth window if still open (COOP may block this)
            if (this.authWindow) {
                try {
                    this.authWindow.close();
                } catch (e) {
                    // Cross-Origin-Opener-Policy may prevent this, ignore
                    console.log('[Google Auth] Could not close popup (COOP)');
                }
            }
            
            // Check for errors
            if (!response.success || response.error_message) {
                console.error('[Google Auth] Error response:', response.error_message);
                this.showError(response.error_message || 'Authentication failed. Please try again.');
                return;
            }
            
            // Validate we have required data
            if (!response.refreshToken || !response.customer_accounts || response.customer_accounts.length === 0) {
                console.error('[Google Auth] Invalid response data:', {
                    hasRefreshToken: !!response.refreshToken,
                    hasCustomerAccounts: !!response.customer_accounts,
                    accountsLength: response.customer_accounts ? response.customer_accounts.length : 0
                });
                this.showError('Invalid response from authentication server. Please try again.');
                return;
            }
            
            // Validate nonce matches
            if (response.nonce !== this.authState) {
                console.error('[Google Auth] Nonce mismatch:', {
                    received: response.nonce,
                    expected: this.authState
                });
                this.showError('Invalid authentication state. Please try again.');
                return;
            }
            
            console.log('[Google Auth] Validation passed, showing account selection');
            
            // Store the refresh token and accounts (to be saved when user selects account)
            this.tempAuthData = {
                refreshToken: response.refreshToken,
                customerAccounts: response.customer_accounts
            };
            
            // Show ad account selection
            this.showAdAccountSelection(response.customer_accounts);
        },
        
        /**
         * Open authentication modal
         */
        openAuthModal: function() {
            this.modal.fadeIn(200);
            this.showStep('initial');
        },
        
        /**
         * Close authentication modal
         */
        closeAuthModal: function() {
            this.modal.fadeOut(200);
            
            // Close auth window if open
            if (this.authWindow && !this.authWindow.closed) {
                this.authWindow.close();
            }
            
            // Reset after animation
            setTimeout(() => {
                this.resetToInitialStep();
            }, 200);
        },
        
        /**
         * Show specific step in modal
         */
        showStep: function(step) {
            this.modal.find('.wpd-google-step').hide();
            this.modal.find('.wpd-google-step-' + step).show();
        },
        
        /**
         * Reset to initial step
         */
        resetToInitialStep: function() {
            this.showStep('initial');
            this.selectedAccount = null;
            this.tempAuthData = null;
        },
        
        /**
         * Start authentication flow
         */
        startAuthFlow: function() {
            const self = this;
            
            console.log('[Google Auth] Starting auth flow...');
            
            // Show authorizing step
            this.showStep('authorizing');
            
            // Request auth URL from server
            $.ajax({
                url: wpdGoogleAuth.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpd_google_init_auth',
                    nonce: wpdGoogleAuth.nonce
                },
                success: function(response) {
                    console.log('[Google Auth] AJAX response:', response);
                    if (response.success) {
                        self.authState = response.data.state;
                        console.log('[Google Auth] Auth URL:', response.data.url);
                        console.log('[Google Auth] State nonce:', response.data.state);
                        self.openAuthWindow(response.data.url);
                    } else {
                        console.error('[Google Auth] AJAX error:', response.data);
                        self.showError(response.data.message || 'Failed to initialize authentication');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[Google Auth] Network error:', {xhr, status, error});
                    self.showError('Network error. Please try again.');
                }
            });
        },
        
        /**
         * Open authentication popup window
         */
        openAuthWindow: function(url) {
            const self = this;
            const width = 600;
            const height = 700;
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;
            
            const windowFeatures = `width=${width},height=${height},left=${left},top=${top},popup=yes`;
            
            this.authWindow = window.open(url, 'Google Ads Authentication', windowFeatures);
            
            // Focus the popup
            if (this.authWindow) {
                try {
                    this.authWindow.focus();
                } catch (e) {
                    // Cross-Origin-Opener-Policy may prevent focus, ignore
                }
                
                // Note: We can't monitor window.closed due to Cross-Origin-Opener-Policy
                // We rely on postMessage for success/error handling
                // The user can manually close the popup to cancel
            } else {
                this.showError('Popup was blocked. Please allow popups for this site and try again.');
            }
        },
        
        /**
         * Show ad account selection step
         */
        showAdAccountSelection: function(accounts) {
            const self = this;
            const accountList = this.modal.find('.wpd-google-account-list');
            
            // Clear existing accounts
            accountList.empty();
            
            // Render ad accounts (filter out manager accounts)
            const clientAccounts = accounts.filter(account => !account.manager);
            
            if (clientAccounts.length === 0) {
                this.showError('No client ad accounts found. Manager accounts cannot be used for API data collection.');
                return;
            }
            
            $.each(clientAccounts, function(index, account) {
                const accountItem = $('<div>', {
                    class: 'wpd-google-account-item',
                    'data-account-id': account.id,
                    'data-account-name': account.descriptiveName
                }).html(`
                    <div class="wpd-google-account-radio"></div>
                    <div class="wpd-google-account-info">
                        <div class="wpd-google-account-name-display">${self.escapeHtml(account.descriptiveName)}</div>
                        <div class="wpd-google-account-id-display">ID: ${self.escapeHtml(account.id)}</div>
                    </div>
                `);
                
                accountList.append(accountItem);
            });
            
            // Show the step
            this.showStep('select-account');
        },
        
        /**
         * Select ad account
         */
        selectAccount: function($item) {
            // Remove previous selection
            this.modal.find('.wpd-google-account-item').removeClass('selected');
            
            // Select this one
            $item.addClass('selected');
            
            // Store selection
            this.selectedAccount = {
                id: $item.data('account-id'),
                name: $item.data('account-name')
            };
            
            // Enable save button
            this.modal.find('.wpd-google-save-account-btn').prop('disabled', false);
        },
        
        /**
         * Save selected ad account
         */
        saveSelectedAccount: function() {
            const self = this;
            
            if (!this.selectedAccount || !this.tempAuthData) {
                return;
            }
            
            // Disable button and show loading
            const $saveBtn = this.modal.find('.wpd-google-save-account-btn');
            const originalText = $saveBtn.find('.wpd-google-btn-text').text();
            $saveBtn.prop('disabled', true).find('.wpd-google-btn-text').text('Connecting...');
            
            // Save to server with auth data from postMessage
            $.ajax({
                url: wpdGoogleAuth.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpd_google_save_account',
                    nonce: wpdGoogleAuth.nonce,
                    refresh_token: this.tempAuthData.refreshToken,
                    customer_accounts: this.tempAuthData.customerAccounts,
                    account_id: this.selectedAccount.id,
                    account_name: this.selectedAccount.name
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(response.data.account_name);
                    } else {
                        $saveBtn.prop('disabled', false).find('.wpd-google-btn-text').text(originalText);
                        self.showError(response.data.message || 'Failed to save account');
                    }
                },
                error: function() {
                    $saveBtn.prop('disabled', false).find('.wpd-google-btn-text').text(originalText);
                    self.showError('Network error. Please try again.');
                }
            });
        },
        
        /**
         * Show success step
         */
        showSuccess: function(accountName) {
            this.modal.find('.wpd-google-success-account strong').text(accountName);
            this.showStep('success');
            // No longer auto-reload - wait for user to fetch data
        },
        
        /**
         * Fetch all historical data
         */
        fetchAllData: function() {
            const self = this;
            
            // Show fetching step
            this.showStep('fetching');
            
            // Call the AJAX function to fetch all data
            $.ajax({
                url: wpdGoogleAuth.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wpd_refresh_all_google_data',
                    nonce: wpdGoogleAuth.nonce
                },
                success: function(response) {
                    console.log('[Google Auth] Fetch data response:', response);
                    
                    // Parse response if it's a string
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            console.error('[Google Auth] Failed to parse response:', e);
                            self.showFetchError('Invalid response from server.');
                            return;
                        }
                    }
                    
                    if (response.success) {
                        // Show complete step with success message
                        self.modal.find('.wpd-google-complete-message').text(response.message || 'Your Google Ads data has been successfully loaded!');
                        self.showStep('complete');
                        
                        // Auto-reload after 3 seconds on the complete step
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    } else {
                        // Show fetch error (connection was successful, just data fetch failed)
                        self.showFetchError(response.message || 'Could not fetch your data at this time.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[Google Auth] Fetch data error:', {xhr, status, error});
                    self.showFetchError('Network error while fetching data.');
                }
            });
        },
        
        /**
         * Show error step (for auth/connection failures)
         */
        showError: function(message) {
            this.modal.find('.wpd-google-error-message').text(message);
            this.showStep('error');
        },
        
        /**
         * Show fetch error step (connection successful, but data fetch failed)
         */
        showFetchError: function(message) {
            this.modal.find('.wpd-google-fetch-error-message').text(message || 'However, we couldn\'t fetch your data at this time. This may be due to a large ad account or temporary API issues.');
            this.showStep('fetch-error');
        },
        
        /**
         * Open disconnect confirmation modal
         */
        openDisconnectModal: function() {
            this.disconnectModal.fadeIn(200);
        },
        
        /**
         * Close disconnect modal
         */
        closeDisconnectModal: function() {
            this.disconnectModal.fadeOut(200);
        },
        
        /**
         * Confirm disconnection
         */
        confirmDisconnect: function() {
            const self = this;
            const $confirmBtn = this.disconnectModal.find('.wpd-google-confirm-disconnect-btn');
            const originalText = $confirmBtn.text();
            
            $confirmBtn.prop('disabled', true).text('Disconnecting...');
            
            $.ajax({
                url: wpdGoogleAuth.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpd_google_disconnect',
                    nonce: wpdGoogleAuth.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        $confirmBtn.prop('disabled', false).text(originalText);
                        alert(response.data.message || 'Failed to disconnect');
                    }
                },
                error: function() {
                    $confirmBtn.prop('disabled', false).text(originalText);
                    alert('Network error. Please try again.');
                }
            });
        },
        
        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        WPD_Google_Auth.init();
    });
    
})(jQuery);


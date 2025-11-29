/**
 * Facebook Authentication Handler
 * Modern modal-based authentication flow
 */
(function($) {
    'use strict';
    
    const WPD_Facebook_Auth = {
        modal: null,
        disconnectModal: null,
        authWindow: null,
        authState: null,
        tempAuthData: null,
        selectedAccount: null,
        pollInterval: null,
        maxPollAttempts: 60, // 60 seconds max
        pollAttempts: 0,
        
        /**
         * Initialize
         */
        init: function() {
            this.modal = $('#wpd-fb-auth-modal');
            this.disconnectModal = $('#wpd-fb-disconnect-modal');
            this.bindEvents();
            this.setupPostMessageListener();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;
            
            // Connect button
            $(document).on('click', '#wpd-fb-connect-btn', function(e) {
                e.preventDefault();
                self.openAuthModal();
            });
            
            // Modal close buttons
            $(document).on('click', '.wpd-fb-modal-overlay, .wpd-fb-modal-close, .wpd-fb-cancel-btn, .wpd-fb-cancel-auth-btn', function(e) {
                e.preventDefault();
                self.closeAuthModal();
            });
            
            // Done button (on complete and fetch-error steps)
            $(document).on('click', '.wpd-fb-done-btn', function(e) {
                e.preventDefault();
                // Reload page to show connection status
                location.reload();
            });
            
            // Stop propagation on modal content
            $(document).on('click', '.wpd-fb-modal-content', function(e) {
                e.stopPropagation();
            });
            
            // Authorize button
            $(document).on('click', '.wpd-fb-authorize-btn', function(e) {
                e.preventDefault();
                self.startAuthFlow();
            });
            
            // Ad account selection
            $(document).on('click', '.wpd-fb-account-item', function(e) {
                e.preventDefault();
                self.selectAccount($(this));
            });
            
            // Save account button
            $(document).on('click', '.wpd-fb-save-account-btn', function(e) {
                e.preventDefault();
                self.saveSelectedAccount();
            });
            
            // Retry button
            $(document).on('click', '.wpd-fb-retry-btn', function(e) {
                e.preventDefault();
                self.resetToInitialStep();
            });
            
            // Disconnect button
            $(document).on('click', '#wpd-fb-disconnect-btn', function(e) {
                e.preventDefault();
                self.openDisconnectModal();
            });
            
            // Fetch All Data button
            $(document).on('click', '.wpd-fb-fetch-data-btn', function(e) {
                e.preventDefault();
                self.fetchAllData();
            });
            
            // Disconnect modal actions
            $(document).on('click', '.wpd-fb-cancel-disconnect-btn', function(e) {
                e.preventDefault();
                self.closeDisconnectModal();
            });
            
            $(document).on('click', '.wpd-fb-confirm-disconnect-btn', function(e) {
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
                // Validate origin (wpdavies.dev)
                if (event.origin !== 'https://wpdavies.dev') {
                    return;
                }
                
                // Check if message is for us
                if (event.data && event.data.target === 'alpha-insights') {
                    self.handleAuthResponse(event.data);
                }
            });
        },

        /**
         * Handle authentication response from wpdavies.dev postMessage
         */
        handleAuthResponse: function(response) {
            // Close auth window if still open
            if (this.authWindow && !this.authWindow.closed) {
                this.authWindow.close();
            }
            
            // Stop polling if active
            this.stopPolling();
            
            // Check for errors
            if (!response.success || response.error_message) {
                this.showError(response.error_message || 'Authentication failed. Please try again.');
                return;
            }
            
            // Validate we have required data
            if (!response.accessToken || !response.ad_accounts || response.ad_accounts.length === 0) {
                this.showError('Invalid response from authentication server. Please try again.');
                return;
            }
            
            // Validate nonce matches
            if (response.nonce !== this.authState) {
                this.showError('Invalid authentication state. Please try again.');
                return;
            }
            
            // Store the token and expiry in temp data (to be saved when user selects account)
            this.tempAuthData = {
                accessToken: response.accessToken,
                tokenExpiry: response.tokenExpiry
            };
            
            // Show ad account selection
            this.showAdAccountSelection(response.ad_accounts);
        },

        /**
         * Start polling for auth completion (backup - postMessage is primary)
         */
        startPolling: function() {
            const self = this;
            this.pollAttempts = 0;
            
            this.pollInterval = setInterval(function() {
                self.pollAttempts++;
                
                // Check if auth is complete
                self.checkAuthStatus();
                
                // Stop after max attempts
                if (self.pollAttempts >= self.maxPollAttempts) {
                    self.stopPolling();
                    self.showError('Authentication timed out. Please try again.');
                }
            }, 1000); // Poll every second
        },
        
        /**
         * Stop polling
         */
        stopPolling: function() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
            this.pollAttempts = 0;
        },
        
        /**
         * Check authentication status
         */
        checkAuthStatus: function() {
            const self = this;
            
            $.ajax({
                url: wpdFacebookAuth.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpd_fb_check_auth',
                    nonce: wpdFacebookAuth.nonce
                },
                success: function(response) {
                    if (response.success && response.data.complete) {
                        // Auth is complete!
                        self.stopPolling();
                        self.handleAuthComplete(response.data.ad_accounts);
                    }
                },
                error: function() {
                    // Silent fail - will retry on next poll
                }
            });
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
            
            // Stop polling
            this.stopPolling();
            
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
            this.modal.find('.wpd-fb-step').hide();
            this.modal.find('.wpd-fb-step-' + step).show();
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
            
            // Show authorizing step
            this.showStep('authorizing');
            
            // Request auth URL from server
            $.ajax({
                url: wpdFacebookAuth.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpd_fb_init_auth',
                    nonce: wpdFacebookAuth.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.authState = response.data.state;
                        self.openAuthWindow(response.data.url);
                    } else {
                        self.showError(response.data.message || 'Failed to initialize authentication');
                    }
                },
                error: function() {
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
            
            this.authWindow = window.open(url, 'Facebook Authentication', windowFeatures);
            
            // Focus the popup
            if (this.authWindow) {
                this.authWindow.focus();
                
                // Note: Now using postMessage instead of polling for better compatibility
                
                // Also monitor if window is closed manually
                const checkClosed = setInterval(function() {
                    if (self.authWindow && self.authWindow.closed) {
                        clearInterval(checkClosed);
                        // Give it a couple more seconds in case auth just completed
                        setTimeout(function() {
                            if (self.pollInterval) {
                                self.stopPolling();
                                // Only show error if we're still on authorizing step
                                if (self.modal.find('.wpd-fb-step-authorizing').is(':visible')) {
                                    self.showError('Authentication was cancelled or the window was closed.');
                                }
                            }
                        }, 2000);
                    }
                }, 500);
            } else {
                this.showError('Popup was blocked. Please allow popups for this site and try again.');
            }
        },
        
        
        /**
         * Show ad account selection step
         */
        showAdAccountSelection: function(adAccounts) {
            const self = this;
            const accountList = this.modal.find('.wpd-fb-account-list');
            
            // Clear existing accounts
            accountList.empty();
            
            // Render ad accounts
            $.each(adAccounts, function(index, account) {
                const accountItem = $('<div>', {
                    class: 'wpd-fb-account-item',
                    'data-account-id': account.account_id,
                    'data-account-name': account.name
                }).html(`
                    <div class="wpd-fb-account-radio"></div>
                    <div class="wpd-fb-account-info">
                        <div class="wpd-fb-account-name-display">${self.escapeHtml(account.name)}</div>
                        <div class="wpd-fb-account-id-display">ID: ${self.escapeHtml(account.account_id)}</div>
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
            this.modal.find('.wpd-fb-account-item').removeClass('selected');
            
            // Select this one
            $item.addClass('selected');
            
            // Store selection
            this.selectedAccount = {
                id: $item.data('account-id'),
                name: $item.data('account-name')
            };
            
            // Enable save button
            this.modal.find('.wpd-fb-save-account-btn').prop('disabled', false);
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
            const $saveBtn = this.modal.find('.wpd-fb-save-account-btn');
            const originalText = $saveBtn.find('.wpd-fb-btn-text').text();
            $saveBtn.prop('disabled', true).find('.wpd-fb-btn-text').text('Connecting...');
            
            // Save to server with auth data from postMessage
            $.ajax({
                url: wpdFacebookAuth.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpd_fb_save_account',
                    nonce: wpdFacebookAuth.nonce,
                    access_token: this.tempAuthData.accessToken,
                    token_expiry: this.tempAuthData.tokenExpiry,
                    account_id: this.selectedAccount.id,
                    account_name: this.selectedAccount.name
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(response.data.account_name);
                    } else {
                        $saveBtn.prop('disabled', false).find('.wpd-fb-btn-text').text(originalText);
                        self.showError(response.data.message || 'Failed to save account');
                    }
                },
                error: function() {
                    $saveBtn.prop('disabled', false).find('.wpd-fb-btn-text').text(originalText);
                    self.showError('Network error. Please try again.');
                }
            });
        },
        
        /**
         * Show success step
         */
        showSuccess: function(accountName) {
            this.modal.find('.wpd-fb-success-account strong').text(accountName);
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
                url: wpdFacebookAuth.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wpd_refresh_all_facebook_api_data',
                    nonce: wpdFacebookAuth.nonce
                },
                success: function(response) {
                    console.log('[Facebook Auth] Fetch data response:', response);
                    
                    // Parse response if it's a string
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            console.error('[Facebook Auth] Failed to parse response:', e);
                            self.showFetchError('Invalid response from server.');
                            return;
                        }
                    }
                    
                    if (response.success) {
                        // Show complete step with success message
                        self.modal.find('.wpd-fb-complete-message').text(response.message || 'Your Facebook data has been successfully loaded!');
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
                    console.error('[Facebook Auth] Fetch data error:', {xhr, status, error});
                    self.showFetchError('Network error while fetching data.');
                }
            });
        },
        
        /**
         * Show error step (for auth/connection failures)
         */
        showError: function(message) {
            this.modal.find('.wpd-fb-error-message').text(message);
            this.showStep('error');
        },
        
        /**
         * Show fetch error step (connection successful, but data fetch failed)
         */
        showFetchError: function(message) {
            this.modal.find('.wpd-fb-fetch-error-message').text(message || 'However, we couldn\'t fetch your data at this time. This may be due to a large ad account or temporary API issues.');
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
            const $confirmBtn = this.disconnectModal.find('.wpd-fb-confirm-disconnect-btn');
            const originalText = $confirmBtn.text();
            
            $confirmBtn.prop('disabled', true).text('Disconnecting...');
            
            $.ajax({
                url: wpdFacebookAuth.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpd_fb_disconnect',
                    nonce: wpdFacebookAuth.nonce
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
        WPD_Facebook_Auth.init();
    });
    
})(jQuery);



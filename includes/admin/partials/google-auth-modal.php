<?php
/**
 * Google Ads Authentication Modal
 * 
 * @var array $status Connection status array
 * @var string $nonce Security nonce
 */
defined( 'ABSPATH' ) || exit;
?>

<!-- Google Auth Button -->
<div class="wpd-google-auth-container">
    <div class="wpd-google-connection-status <?php echo esc_attr( $status['status_class'] ); ?>">
        <div class="wpd-google-status-info">
            <strong>Connected To: </strong>
            <span class="wpd-google-account-name"><?php echo esc_html( $status['account_name'] ); ?></span>
            <?php if ( $status['is_connected'] ) : ?>
                <span class="wpd-google-status-badge wpd-google-<?php echo esc_attr( $status['status_class'] ); ?>">
                    Active
                </span>
            <?php endif; ?>
        </div>
        <?php if ( $status['is_connected'] && ! empty( $status['connection_date'] ) ) : ?>
            <div class="wpd-google-expiry-info">
                <small>Connected since: <?php echo esc_html( $status['connection_date'] ); ?></small>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="wpd-google-actions">
        <button type="button" class="button button-primary wpd-google-connect-btn" id="wpd-google-connect-btn">
            <?php echo esc_html( $status['is_connected'] ? 'Reconnect Google Ads' : 'Connect to Google Ads' ); ?>
        </button>
        
        <?php if ( $status['is_connected'] ) : ?>
            <button type="button" class="button button-secondary wpd-google-disconnect-btn" id="wpd-google-disconnect-btn">
                Disconnect
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Google Auth Modal -->
<div id="wpd-google-auth-modal" class="wpd-google-modal" style="display: none;">
    <div class="wpd-google-modal-overlay"></div>
    <div class="wpd-google-modal-content">
        <button type="button" class="wpd-google-modal-close" aria-label="Close">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
        
        <div class="wpd-google-modal-header">
            <div class="wpd-google-modal-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
            </div>
            <h2 class="wpd-google-modal-title">Connect to Google Ads</h2>
            <p class="wpd-google-modal-subtitle">Connect your Google Ads Account to track campaign performance and ad spend</p>
        </div>
        
        <div class="wpd-google-modal-body">
            <!-- Step 1: Initial / Loading -->
            <div class="wpd-google-step wpd-google-step-initial" data-step="initial">
                <div class="wpd-google-step-content">
                    <h3>What you'll need:</h3>
                    <ul class="wpd-google-requirements">
                        <li>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Admin access to your Google Ads Account
                        </li>
                        <li>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Active Google Ads account with campaigns
                        </li>
                        <li>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Permission to connect third-party apps
                        </li>
                    </ul>
                    
                    <div class="wpd-google-info-box">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        <div>
                            <strong>Permission Required:</strong> Google Ads API requires us to have full access to your ad account, we cannot request read-only access. Alpha Insights is not configured to make any changes to your campaigns or budgets.
                        </div>
                    </div>
                </div>
                
                <div class="wpd-google-modal-actions">
                    <button type="button" class="button button-secondary wpd-google-cancel-btn">Cancel</button>
                    <button type="button" class="button button-primary wpd-google-authorize-btn">
                        <span class="wpd-google-btn-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                            </svg>
                        </span>
                        <span class="wpd-google-btn-text">Authorize with Google</span>
                    </button>
                </div>
            </div>
            
            <!-- Step 2: Authorizing -->
            <div class="wpd-google-step wpd-google-step-authorizing" data-step="authorizing" style="display: none;">
                <div class="wpd-google-step-content wpd-google-center">
                    <div class="wpd-google-spinner"></div>
                    <h3>Connecting to Google Ads...</h3>
                    <p>Please complete the authorization in the popup window.</p>
                    <p class="wpd-google-popup-notice">
                        <small>If you don't see a popup, your browser may have blocked it. Please allow popups for this site.</small>
                    </p>
                </div>
                
                <div class="wpd-google-modal-actions">
                    <button type="button" class="button button-secondary wpd-google-cancel-auth-btn">Cancel</button>
                </div>
            </div>
            
            <!-- Step 3: Select Ad Account -->
            <div class="wpd-google-step wpd-google-step-select-account" data-step="select-account" style="display: none;">
                <div class="wpd-google-step-content">
                    <h3>Select Your Ad Account</h3>
                    <p>Choose the Google Ads Account you'd like to connect:</p>
                    
                    <div class="wpd-google-account-list">
                        <!-- Ad accounts will be populated here via JS -->
                    </div>
                </div>
                
                <div class="wpd-google-modal-actions">
                    <button type="button" class="button button-secondary wpd-google-cancel-btn">Cancel</button>
                    <button type="button" class="button button-primary wpd-google-save-account-btn" disabled>
                        <span class="wpd-google-btn-text">Connect Account</span>
                    </button>
                </div>
            </div>
            
            <!-- Step 4: Success -->
            <div class="wpd-google-step wpd-google-step-success" data-step="success" style="display: none;">
                <div class="wpd-google-step-content wpd-google-center">
                    <div class="wpd-google-success-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                    <h3>Successfully Connected!</h3>
                    <p class="wpd-google-success-account">Connected to: <strong></strong></p>
                    <p class="wpd-google-info-text">Ready to sync your Google Ads data. Click below to fetch all historical data.</p>
                    <p class="wpd-google-small-text"><small>This will load all historical ad spend data. After this initial fetch, we'll automatically keep your data up to date.</small></p>
                </div>
                
                <div class="wpd-google-modal-actions">
                    <button type="button" class="button button-primary wpd-google-fetch-data-btn">
                        <span class="wpd-google-btn-text">Fetch All Data</span>
                    </button>
                </div>
            </div>
            
            <!-- Step 4b: Fetching Data -->
            <div class="wpd-google-step wpd-google-step-fetching" data-step="fetching" style="display: none;">
                <div class="wpd-google-step-content wpd-google-center">
                    <div class="wpd-google-spinner"></div>
                    <h3>Fetching Your Data...</h3>
                    <p class="wpd-google-fetch-status">This may take a moment depending on your data volume.</p>
                </div>
            </div>
            
            <!-- Step 4c: Fetch Complete -->
            <div class="wpd-google-step wpd-google-step-complete" data-step="complete" style="display: none;">
                <div class="wpd-google-step-content wpd-google-center">
                    <div class="wpd-google-success-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                    <h3>All Set!</h3>
                    <p class="wpd-google-complete-message"></p>
                    <p class="wpd-google-small-text"><small>Your data will now be automatically synced regularly in the background.</small></p>
                </div>
                
                <div class="wpd-google-modal-actions">
                    <button type="button" class="button button-primary wpd-google-done-btn">Done</button>
                </div>
            </div>
            
            <!-- Step 4d: Fetch Error (Connection successful, but data fetch failed) -->
            <div class="wpd-google-step wpd-google-step-fetch-error" data-step="fetch-error" style="display: none;">
                <div class="wpd-google-step-content wpd-google-center">
                    <div class="wpd-google-warning-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                    </div>
                    <h3>Connection Successful</h3>
                    <p><strong>Your Google Ads account is now connected!</strong></p>
                    <p class="wpd-google-fetch-error-message">However, we couldn't fetch your data at this time. This may be due to a large ad account or temporary API issues.</p>
                    <div class="wpd-google-info-box" style="margin-top: 15px; text-align: left;">
                        <p style="margin: 0 0 10px 0;"><strong>What you can do:</strong></p>
                        <ul style="margin: 0; padding-left: 20px;">
                            <li>Try fetching data again later from the Google Ads settings page</li>
                            <li>If you have a large ad account, consider adjusting the "Account Age" setting to fetch a smaller date range</li>
                        </ul>
                    </div>
                </div>
                
                <div class="wpd-google-modal-actions">
                    <button type="button" class="button button-primary wpd-google-done-btn">Done</button>
                </div>
            </div>
            
            <!-- Step 5: Error (Connection/Auth failed) -->
            <div class="wpd-google-step wpd-google-step-error" data-step="error" style="display: none;">
                <div class="wpd-google-step-content wpd-google-center">
                    <div class="wpd-google-error-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                    </div>
                    <h3>Connection Failed</h3>
                    <p class="wpd-google-error-message"></p>
                </div>
                
                <div class="wpd-google-modal-actions">
                    <button type="button" class="button button-secondary wpd-google-cancel-btn">Cancel</button>
                    <button type="button" class="button button-primary wpd-google-retry-btn">Try Again</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Disconnect Confirmation Modal -->
<div id="wpd-google-disconnect-modal" class="wpd-google-modal wpd-google-modal-sm" style="display: none;">
    <div class="wpd-google-modal-overlay"></div>
    <div class="wpd-google-modal-content">
        <div class="wpd-google-modal-header">
            <h2 class="wpd-google-modal-title">Disconnect Google Ads?</h2>
        </div>
        
        <div class="wpd-google-modal-body">
            <p>Are you sure you want to disconnect your Google Ads account?</p>
            <p><strong>This will:</strong></p>
            <ul>
                <li>Stop syncing new ad data</li>
                <li>Remove your refresh token</li>
                <li>Keep existing historical data</li>
            </ul>
        </div>
        
        <div class="wpd-google-modal-actions">
            <button type="button" class="button button-secondary wpd-google-cancel-disconnect-btn">Cancel</button>
            <button type="button" class="button button-primary wpd-google-confirm-disconnect-btn">Disconnect</button>
        </div>
    </div>
</div>

<input type="hidden" id="wpd-google-auth-nonce" value="<?php echo esc_attr( $nonce ); ?>">


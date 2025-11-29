<?php
/**
 * Facebook Authentication Modal
 * 
 * @var array $status Connection status array
 * @var string $nonce Security nonce
 */
defined( 'ABSPATH' ) || exit;
?>

<!-- Facebook Auth Button -->
<div class="wpd-facebook-auth-container">
    <div class="wpd-fb-connection-status <?php echo esc_attr( $status['status_class'] ); ?>">
        <div class="wpd-fb-status-info">
            <strong>Connected To: </strong>
            <span class="wpd-fb-account-name"><?php echo esc_html( $status['account_name'] ); ?></span>
            <?php if ( $status['is_connected'] ) : ?>
                <span class="wpd-fb-status-badge wpd-fb-<?php echo esc_attr( $status['status_class'] ); ?>">
                    <?php 
                    if ( $status['status_class'] === 'expired' ) {
                        echo 'Expired';
                    } elseif ( $status['status_class'] === 'expiring-soon' ) {
                        echo 'Expiring Soon';
                    } else {
                        echo 'Active';
                    }
                    ?>
                </span>
            <?php endif; ?>
        </div>
        
        <?php if ( $status['is_connected'] && $status['days_until_expiry'] >= 0 ) : ?>
            <div class="wpd-fb-expiry-info">
                <small>
                    Token expires: <?php echo esc_html( $status['expiry_date'] ); ?>
                    (<?php echo absint( $status['days_until_expiry'] ); ?> days)
                </small>
            </div>
        <?php elseif ( $status['is_connected'] && $status['days_until_expiry'] < 0 ) : ?>
            <div class="wpd-fb-expiry-info wpd-fb-expired">
                <small>Token expired on: <?php echo esc_html( $status['expiry_date'] ); ?></small>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="wpd-fb-actions">
        <button type="button" class="button button-primary wpd-fb-connect-btn" id="wpd-fb-connect-btn">
            <?php echo $status['is_connected'] ? 'Reconnect Facebook' : 'Connect to Facebook'; ?>
        </button>
        
        <?php if ( $status['is_connected'] ) : ?>
            <button type="button" class="button button-secondary wpd-fb-disconnect-btn" id="wpd-fb-disconnect-btn">
                Disconnect
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Facebook Auth Modal -->
<div id="wpd-fb-auth-modal" class="wpd-fb-modal" style="display: none;">
    <div class="wpd-fb-modal-overlay"></div>
    <div class="wpd-fb-modal-content">
        <button type="button" class="wpd-fb-modal-close" aria-label="Close">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
        
        <div class="wpd-fb-modal-header">
            <div class="wpd-fb-modal-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="#1877f2">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
            </div>
            <h2 class="wpd-fb-modal-title">Connect to Facebook</h2>
            <p class="wpd-fb-modal-subtitle">Connect your Facebook Ad Account to track campaign performance and ad spend</p>
        </div>
        
        <div class="wpd-fb-modal-body">
            <!-- Step 1: Initial / Loading -->
            <div class="wpd-fb-step wpd-fb-step-initial" data-step="initial">
                <div class="wpd-fb-step-content">
                    <h3>What you'll need:</h3>
                    <ul class="wpd-fb-requirements">
                        <li>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Admin access to your Facebook Ad Account
                        </li>
                        <li>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Active Facebook Ads Manager account
                        </li>
                        <li>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Permission to connect third-party apps
                        </li>
                    </ul>
                    
                    <div class="wpd-fb-info-box">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        <div>
                            <strong>Read-only access:</strong> We only request permission to read your ad data. We cannot make changes to your ad account.
                        </div>
                    </div>
                </div>
                
                <div class="wpd-fb-modal-actions">
                    <button type="button" class="button button-secondary wpd-fb-cancel-btn">Cancel</button>
                    <button type="button" class="button button-primary wpd-fb-authorize-btn">
                        <span class="wpd-fb-btn-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </span>
                        <span class="wpd-fb-btn-text">Authorize with Facebook</span>
                    </button>
                </div>
            </div>
            
            <!-- Step 2: Authorizing -->
            <div class="wpd-fb-step wpd-fb-step-authorizing" data-step="authorizing" style="display: none;">
                <div class="wpd-fb-step-content wpd-fb-center">
                    <div class="wpd-fb-spinner"></div>
                    <h3>Connecting to Facebook...</h3>
                    <p>Please complete the authorization in the popup window.</p>
                    <p class="wpd-fb-popup-notice">
                        <small>If you don't see a popup, your browser may have blocked it. Please allow popups for this site.</small>
                    </p>
                </div>
                
                <div class="wpd-fb-modal-actions">
                    <button type="button" class="button button-secondary wpd-fb-cancel-auth-btn">Cancel</button>
                </div>
            </div>
            
            <!-- Step 3: Select Ad Account -->
            <div class="wpd-fb-step wpd-fb-step-select-account" data-step="select-account" style="display: none;">
                <div class="wpd-fb-step-content">
                    <h3>Select Your Ad Account</h3>
                    <p>Choose the Facebook Ad Account you'd like to connect:</p>
                    
                    <div class="wpd-fb-account-list">
                        <!-- Ad accounts will be populated here via JS -->
                    </div>
                </div>
                
                <div class="wpd-fb-modal-actions">
                    <button type="button" class="button button-secondary wpd-fb-cancel-btn">Cancel</button>
                    <button type="button" class="button button-primary wpd-fb-save-account-btn" disabled>
                        <span class="wpd-fb-btn-text">Connect Account</span>
                    </button>
                </div>
            </div>
            
            <!-- Step 4: Success -->
            <div class="wpd-fb-step wpd-fb-step-success" data-step="success" style="display: none;">
                <div class="wpd-fb-step-content wpd-fb-center">
                    <div class="wpd-fb-success-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                    <h3>Successfully Connected!</h3>
                    <p class="wpd-fb-success-account">Connected to: <strong></strong></p>
                    <p class="wpd-fb-info-text">Ready to sync your Facebook ad data. Click below to fetch all historical data.</p>
                    <p class="wpd-fb-small-text"><small>This will load all historical ad spend data. After this initial fetch, we'll automatically keep your data up to date.</small></p>
                </div>
                
                <div class="wpd-fb-modal-actions">
                    <button type="button" class="button button-primary wpd-fb-fetch-data-btn">
                        <span class="wpd-fb-btn-text">Fetch All Data</span>
                    </button>
                </div>
            </div>
            
            <!-- Step 4b: Fetching Data -->
            <div class="wpd-fb-step wpd-fb-step-fetching" data-step="fetching" style="display: none;">
                <div class="wpd-fb-step-content wpd-fb-center">
                    <div class="wpd-fb-spinner"></div>
                    <h3>Fetching Your Data...</h3>
                    <p class="wpd-fb-fetch-status">This may take a moment depending on your data volume.</p>
                </div>
            </div>
            
            <!-- Step 4c: Fetch Complete -->
            <div class="wpd-fb-step wpd-fb-step-complete" data-step="complete" style="display: none;">
                <div class="wpd-fb-step-content wpd-fb-center">
                    <div class="wpd-fb-success-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                    <h3>All Set!</h3>
                    <p class="wpd-fb-complete-message"></p>
                    <p class="wpd-fb-small-text"><small>Your data will now be automatically synced regularly in the background.</small></p>
                </div>
                
                <div class="wpd-fb-modal-actions">
                    <button type="button" class="button button-primary wpd-fb-done-btn">Done</button>
                </div>
            </div>
            
            <!-- Step 4d: Fetch Error (Connection successful, but data fetch failed) -->
            <div class="wpd-fb-step wpd-fb-step-fetch-error" data-step="fetch-error" style="display: none;">
                <div class="wpd-fb-step-content wpd-fb-center">
                    <div class="wpd-fb-warning-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                    </div>
                    <h3>Connection Successful</h3>
                    <p><strong>Your Facebook account is now connected!</strong></p>
                    <p class="wpd-fb-fetch-error-message">However, we couldn't fetch your data at this time. This may be due to a large ad account or temporary API issues.</p>
                    <div class="wpd-fb-info-box" style="margin-top: 15px; text-align: left;">
                        <p style="margin: 0 0 10px 0;"><strong>What you can do:</strong></p>
                        <ul style="margin: 0; padding-left: 20px;">
                            <li>Try fetching data again later from the Facebook settings page</li>
                            <li>If you have a large ad account, consider adjusting the date range settings to fetch smaller portions of data</li>
                        </ul>
                    </div>
                </div>
                
                <div class="wpd-fb-modal-actions">
                    <button type="button" class="button button-primary wpd-fb-done-btn">Done</button>
                </div>
            </div>
            
            <!-- Step 5: Error (Connection/Auth failed) -->
            <div class="wpd-fb-step wpd-fb-step-error" data-step="error" style="display: none;">
                <div class="wpd-fb-step-content wpd-fb-center">
                    <div class="wpd-fb-error-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                    </div>
                    <h3>Connection Failed</h3>
                    <p class="wpd-fb-error-message"></p>
                </div>
                
                <div class="wpd-fb-modal-actions">
                    <button type="button" class="button button-secondary wpd-fb-cancel-btn">Cancel</button>
                    <button type="button" class="button button-primary wpd-fb-retry-btn">Try Again</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Disconnect Confirmation Modal -->
<div id="wpd-fb-disconnect-modal" class="wpd-fb-modal wpd-fb-modal-sm" style="display: none;">
    <div class="wpd-fb-modal-overlay"></div>
    <div class="wpd-fb-modal-content">
        <div class="wpd-fb-modal-header">
            <h2 class="wpd-fb-modal-title">Disconnect Facebook?</h2>
        </div>
        
        <div class="wpd-fb-modal-body">
            <p>Are you sure you want to disconnect your Facebook account?</p>
            <p><strong>This will:</strong></p>
            <ul>
                <li>Stop syncing new ad data</li>
                <li>Remove your access token</li>
                <li>Keep existing historical data</li>
            </ul>
        </div>
        
        <div class="wpd-fb-modal-actions">
            <button type="button" class="button button-secondary wpd-fb-cancel-disconnect-btn">Cancel</button>
            <button type="button" class="button button-primary wpd-fb-confirm-disconnect-btn">Disconnect</button>
        </div>
    </div>
</div>

<input type="hidden" id="wpd-fb-auth-nonce" value="<?php echo esc_attr( $nonce ); ?>">



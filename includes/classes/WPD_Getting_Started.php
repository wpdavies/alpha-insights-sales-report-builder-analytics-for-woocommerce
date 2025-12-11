<?php
/**
 * Getting Started Handler
 * Handles the getting started page
 * 
 * @package Alpha Insights
 * @version 1.0.0
 * @since 5.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 */
defined( 'ABSPATH' ) || exit;

class WPD_Getting_Started {

    /**
     * Render the getting started page
     */
    public static function render_getting_started_page() {

        // Mark onboarding as viewed immediately when page loads
        update_option( 'wpd_ai_onboarding_completed', current_time( 'timestamp' ) );

        // Enqueue scripts and dashicons
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style( 'wpd-alpha-insights-getting-started', WPD_AI_URL_PATH . 'assets/css/getting-started.css', array( 'dashicons' ), WPD_AI_VER );
        wp_enqueue_script( 'wpd-alpha-insights-getting-started', WPD_AI_URL_PATH . 'assets/js/getting-started.js', array( 'jquery' ), WPD_AI_VER, true );

        // Get data for settings
        $cost_defaults = get_option( 'wpd_ai_cost_defaults' );
        $payment_gateway_cost_settings = wpd_get_payment_gateway_cost_settings();
        $available_payment_gateways = wpd_get_available_payment_gateways();
        $logo_icon_url = WPD_AI_URL_PATH . 'assets/img/Alpha-Insights-Icon-Large.png';
        
        // Get license data (only for Pro version)
        $license_key = '';
        $license_status = 'active'; // Free version doesn't need license, so treat as active
        $is_pro = defined('WPD_AI_PRO') && WPD_AI_PRO && class_exists('WPD_Authenticator');
        
        if ( $is_pro ) {
            $authenticator = new WPD_Authenticator();
            $license_data = $authenticator->license_details();
            $license_key = $license_data['license_key'];
            $license_status = $license_data['license_status'];
        }

        // Localize script with data
        wp_localize_script( 'wpd-alpha-insights-getting-started', 'wpdGettingStarted', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( WPD_AI_AJAX_NONCE_ACTION ),
            'salesReportUrl' => wpd_admin_page_url( 'reports-orders' ),
            'licenseStatus' => $license_status,
            'isPro' => $is_pro,
        ));

        // Render the page
        ?>
        <div id="wpd-gs-modal-overlay" class="wpd-gs-modal-overlay active">
            <div class="wpd-gs-modal">
                
                <!-- Left Side - Brand Panel -->
                <div class="wpd-gs-modal-brand">
                    <div class="wpd-gs-support-card">
                        <!-- Branding Section -->
                        <div class="wpd-gs-brand-logo-container">
                            <div class="wpd-gs-brand-logo-row">
                                <img src="<?php echo esc_url($logo_icon_url); ?>" alt="Alpha Insights Icon" class="wpd-gs-brand-logo-icon" />
                                <div class="wpd-gs-brand-logo-text">
                                    <div class="wpd-gs-brand-title"><?php esc_html_e('Alpha Insights', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></div>
                                    <div class="wpd-gs-brand-subtitle"><?php esc_html_e('Intelligent Profit Reports', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Progress Steps -->
                        <div class="wpd-gs-progress-container">
                            <div class="wpd-gs-progress-step active" data-step="1">
                                <div class="wpd-gs-progress-number">1</div>
                                <div class="wpd-gs-progress-label"><?php esc_html_e('Welcome', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></div>
                            </div>
                            <?php if ( $is_pro ) : ?>
                            <div class="wpd-gs-progress-step" data-step="2">
                                <div class="wpd-gs-progress-number">2</div>
                                <div class="wpd-gs-progress-label"><?php esc_html_e('License', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></div>
                            </div>
                            <?php endif; ?>
                            <div class="wpd-gs-progress-step" data-step="<?php echo $is_pro ? '3' : '2'; ?>">
                                <div class="wpd-gs-progress-number"><?php echo $is_pro ? '3' : '2'; ?></div>
                                <div class="wpd-gs-progress-label"><?php esc_html_e('Settings', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></div>
                            </div>
                            <div class="wpd-gs-progress-step" data-step="<?php echo $is_pro ? '4' : '3'; ?>">
                                <div class="wpd-gs-progress-number"><?php echo $is_pro ? '4' : '3'; ?></div>
                                <div class="wpd-gs-progress-label"><?php esc_html_e('Ready', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side - Content -->
                <div class="wpd-gs-modal-content">
                    
                    <!-- Header with Close Button -->
                    <div class="wpd-gs-modal-header">
                        <h2 class="wpd-gs-modal-title"><?php esc_html_e('Getting Started with Alpha Insights', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></h2>
                        <button type="button" class="wpd-gs-modal-close" aria-label="<?php esc_attr_e('Close', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>">
                            &times;
                        </button>
                    </div>
                    
                    <!-- Main Content Area -->
                    <div class="wpd-gs-modal-body">
                        
                        <!-- Step 1: Welcome -->
                        <div class="wpd-gs-step wpd-gs-step-1 active">
                            <div class="wpd-gs-hero">
                                <h1><?php esc_html_e('Welcome to Alpha Insights! 🎉', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></h1>
                                <p class="wpd-gs-lead"><?php esc_html_e('The most powerful profit tracking system for WooCommerce', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
                                <p class="wpd-gs-subtitle"><?php esc_html_e( 'Custom Report Builder | Profit & Loss | Advertising ROI | Expense Tracking | Visitor Analytics', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></p>
                            </div>
                            
                            <div class="wpd-gs-dashboard-preview">
                                <img src="<?php echo esc_url( WPD_AI_URL_PATH . 'assets/img/Alpha-Insights-Dashboard-Example.png' ); ?>" alt="<?php esc_attr_e('Alpha Insights Dashboard Preview', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>" class="wpd-gs-dashboard-image">
                                
                                <div class="wpd-gs-value-props">
                                    <div class="wpd-gs-value-prop">
                                        <div class="wpd-gs-value-content">
                                            <h3>
                                                <span class="dashicons dashicons-chart-line wpd-gs-icon"></span>
                                                <?php esc_html_e('Track Real-Time Profit', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>
                                            </h3>
                                            <p><?php esc_html_e('See your true profit on every order, product, and campaign. Know exactly what\'s making you money.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="wpd-gs-value-prop">
                                        <div class="wpd-gs-value-content">
                                            <h3>
                                                <span class="dashicons dashicons-money-alt wpd-gs-icon"></span>
                                                <?php esc_html_e('Understand Your True Costs', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>
                                            </h3>
                                            <p><?php esc_html_e('Track product costs, shipping fees, payment gateway charges, and all business expenses in one place.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="wpd-gs-value-prop">
                                        <div class="wpd-gs-value-content">
                                            <h3>
                                                <span class="dashicons dashicons-megaphone wpd-gs-icon"></span>
                                                <?php esc_html_e('Complete ROI Visibility', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>
                                            </h3>
                                            <p><?php esc_html_e('Connect Google and Facebook Ads to see which campaigns are actually profitable, not just generating revenue.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="wpd-gs-value-prop">
                                        <div class="wpd-gs-value-content">
                                            <h3>
                                                <span class="dashicons dashicons-visibility wpd-gs-icon"></span>
                                                <?php esc_html_e('Know Your Visitors', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>
                                            </h3>
                                            <p><?php esc_html_e('Track where your traffic comes from, which channels convert best, and optimize your marketing based on actual visitor data.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="wpd-gs-features-overview">
                                <h3><?php esc_html_e('What You Can Do:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></h3>
                                <ul class="wpd-gs-features-list">
                                    <li><span class="dashicons dashicons-yes-alt"></span> <strong><?php esc_html_e('Report Builder:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></strong> <?php esc_html_e('Create custom profit reports with drag-and-drop widgets', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></li>
                                    <li>
                                        <span class="dashicons dashicons-yes-alt"></span> 
                                        <div class="wpd-gs-feature-content">
                                            <strong><?php esc_html_e('Expense Manager:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></strong> 
                                            <?php esc_html_e('Track all your business expenses and allocate them to specific periods', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>
                                        </div>
                                        <?php if ( ! $is_pro ) : ?>
                                            <span class="wpd-gs-pro-badge" title="<?php esc_html_e('Pro Feature', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>"><?php esc_html_e('Pro', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></span>
                                        <?php endif; ?>
                                    </li>
                                    <li><span class="dashicons dashicons-yes-alt"></span> <strong><?php esc_html_e('Cost of Goods:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></strong> <?php esc_html_e('Easily manage product costs with bulk import/export', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></li>
                                    <li>
                                        <span class="dashicons dashicons-yes-alt"></span> 
                                        <div class="wpd-gs-feature-content">
                                            <strong><?php esc_html_e('Ad Integrations:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></strong> 
                                            <?php esc_html_e('Connect Facebook and Google Ads for true ROAS tracking', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>
                                        </div>
                                        <?php if ( ! $is_pro ) : ?>
                                            <span class="wpd-gs-pro-badge" title="<?php esc_html_e('Pro Feature', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>"><?php esc_html_e('Pro', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></span>
                                        <?php endif; ?>
                                    </li>
                                    <li><span class="dashicons dashicons-yes-alt"></span> <strong><?php esc_html_e('Website Traffic:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></strong> <?php esc_html_e('Monitor visitor sources, channels, and conversion paths to understand customer behavior', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Step 2: License Activation (Pro only) -->
                        <?php if ( $is_pro ) : ?>
                        <div class="wpd-gs-step wpd-gs-step-2">
                            <div class="wpd-gs-step-header">
                                <h2><?php esc_html_e('Activate Your License', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></h2>
                                <p><?php esc_html_e('Enter your license key to unlock all Alpha Insights features and receive automatic updates.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
                            </div>
                            
                            <form id="wpd-gs-license-form">
                                
                                <div class="wpd-gs-license-section">
                                    
                                    <?php if ( $license_status === 'active' ) : ?>
                                        
                                        <div class="wpd-gs-license-status wpd-gs-license-active">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <div class="wpd-gs-license-status-content">
                                                <h3><?php esc_html_e('License Active!', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></h3>
                                                <p><?php esc_html_e('Your license is active and all features are unlocked. You can proceed to the next step.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
                                            </div>
                                        </div>
                                    
                                    <?php else : ?>
                                    
                                        <div class="wpd-gs-license-input-section">
                                            <div class="wpd-gs-license-input-group">
                                                <label for="license_key"><?php esc_html_e('License Key', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></label>
                                                <input type="text" id="license_key" name="license_key" value="<?php echo esc_attr( $license_key ); ?>" placeholder="WPD-AI-XXXXXXXXXX-XXXXXXXXXX" class="wpd-gs-input wpd-gs-input-license">
                                                <p class="wpd-gs-input-help"><?php esc_html_e('Enter the license key from your purchase confirmation email', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
                                            </div>
                                            
                                            <div class="wpd-gs-license-help">
                                                <span class="dashicons dashicons-info"></span>
                                                <div class="wpd-gs-license-help-content">
                                                    <h4><?php esc_html_e('Where to Find Your License Key', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></h4>
                                                    <ul>
                                                        <li><?php esc_html_e('Check your purchase confirmation email', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></li>
                                                        <li><?php esc_html_e('Visit', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?> <a href="https://wpdavies.dev/my-account/licenses/" target="_blank"><?php esc_html_e('your WP Davies account', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div id="wpd-gs-license-status-message" class="wpd-gs-license-message" style="display: none;"></div>
                                    
                                    <?php endif; ?>
                                    
                                </div>
                                
                            </form>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Step 3: Settings (or Step 2 for Free) -->
                        <div class="wpd-gs-step wpd-gs-step-<?php echo $is_pro ? '3' : '2'; ?>">
                            <div class="wpd-gs-step-header">
                                <h2><?php esc_html_e('Configure Payment Gateway Fees', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></h2>
                                <p><?php esc_html_e('Set up the fees you pay for each payment method to accurately track your payment processing costs. You can always change these later in General Settings.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
                            </div>
                            
                            <form id="wpd-gs-settings-form">
                                
                                <!-- Payment Gateway Costs -->
                                <div class="wpd-gs-setting-section">
                                    <div class="wpd-gs-setting-header">
                                        <h3><?php esc_html_e('Payment Gateway Costs', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></h3>
                                        <p class="wpd-gs-setting-description"><?php esc_html_e('Configure the fees you pay for each payment method. Common rates: Stripe/PayPal 2.9% + $0.30, Square 2.6% + $0.10', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
                                    </div>
                                    <div class="wpd-gs-payment-gateways">
                                        <?php if ( is_array($available_payment_gateways) && ! empty($available_payment_gateways) ) : ?>
                                            <table class="wpd-gs-table">
                                                <thead>
                                                    <tr>
                                                        <th><?php esc_html_e('Payment Gateway', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></th>
                                                        <th><?php esc_html_e('Percent of Order', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></th>
                                                        <th><?php esc_html_e('Static Fee', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach( $available_payment_gateways as $payment_gateway_id => $payment_gateway_data ) : ?>
                                                        <tr>
                                                            <td class="wpd-gs-gateway-name"><?php echo esc_html( $payment_gateway_data['title'] ); ?></td>
                                                            <td>
                                                                <input type="number" name="payment_gateway_costs[<?php echo esc_attr($payment_gateway_id); ?>][percent_of_sales]" value="<?php echo esc_attr( $payment_gateway_cost_settings[$payment_gateway_id]['percent_of_sales'] ?? 0 ); ?>" step="0.01" placeholder="0.00" class="wpd-gs-input wpd-gs-input-sm">
                                                                <span class="wpd-gs-input-suffix">%</span>
                                                            </td>
                                                            <td>
                                                                <input type="number" name="payment_gateway_costs[<?php echo esc_attr($payment_gateway_id); ?>][static_fee]" value="<?php echo esc_attr( $payment_gateway_cost_settings[$payment_gateway_id]['static_fee'] ?? 0 ); ?>" step="0.01" placeholder="0.00" class="wpd-gs-input wpd-gs-input-sm">
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php else : ?>
                                            <p class="wpd-gs-no-gateways"><?php esc_html_e('No payment gateways detected. Enable payment gateways in WooCommerce to configure fees here.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="wpd-gs-save-notice">
                                    <span class="dashicons dashicons-info"></span>
                                    <p><?php esc_html_e('These fees will be automatically applied to all orders based on the payment method used. You can override individual order fees if needed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
                                </div>
                                
                            </form>
                        </div>
                        
                        <!-- Step 4: Next Steps (or Step 3 for Free) -->
                        <div class="wpd-gs-step wpd-gs-step-<?php echo $is_pro ? '4' : '3'; ?>">
                            <div class="wpd-gs-success-header">
                                <span class="dashicons dashicons-yes-alt wpd-gs-success-icon"></span>
                                <h2><?php esc_html_e('You\'re All Set!', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></h2>
                                <p><?php esc_html_e('Alpha Insights is now tracking your profits. Start exploring your data!', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
                            </div>
                            
                            <!-- Primary Action with Background -->
                            <div class="wpd-gs-primary-action">
                                <div class="wpd-gs-report-preview-bg"></div>
                                <a href="<?php echo esc_url( wpd_admin_page_url( 'reports-orders' ) ); ?>" class="wpd-gs-button wpd-gs-button-primary wpd-gs-button-large">
                                    <span class="dashicons dashicons-chart-bar"></span>
                                    <?php esc_html_e('View Your First Report', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>
                                </a>
                            </div>
                            
                            <!-- Secondary Actions -->
                            <div class="wpd-gs-secondary-actions">
                                <h3><?php esc_html_e('Recommended Next Steps', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></h3>
                                <p class="wpd-gs-optional-note"><?php esc_html_e('These are optional but will help you get the most accurate profit data:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
                                
                                <div class="wpd-gs-action-cards">
                                    <div class="wpd-gs-action-card">
                                        <div class="wpd-gs-action-content">
                                            <h4>
                                                <span class="dashicons dashicons-products"></span>
                                                <?php esc_html_e('Update Cost of Goods', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>
                                            </h4>
                                            <p><?php esc_html_e('Add actual supplier costs for your products instead of using default percentages.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
                                            <a href="<?php echo esc_url( wpd_admin_page_url( 'cost-of-goods-manager' ) ); ?>" class="wpd-gs-link"><?php esc_html_e('Go to Cost Manager →', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></a>
                                        </div>
                                    </div>
                                    
                                    <div class="wpd-gs-action-card<?php echo ! $is_pro ? ' wpd-gs-pro-feature' : ''; ?>">
                                        <div class="wpd-gs-action-content">
                                            <h4>
                                                <span class="dashicons dashicons-megaphone"></span>
                                                <?php esc_html_e('Connect Ad Platforms', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>
                                                <?php if ( ! $is_pro ) : ?>
                                                    <span class="wpd-gs-pro-badge" title="<?php esc_html_e('Pro Feature', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>"><?php esc_html_e('Pro', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></span>
                                                <?php endif; ?>
                                            </h4>
                                            <p><?php esc_html_e('Link Google Ads and Facebook Ads to track which campaigns are actually profitable.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
                                            <div class="wpd-gs-action-links">
                                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . WPD_Admin_Menu::$settings_slug . '&subpage=google-ads' ) ); ?>" class="wpd-gs-link"><?php esc_html_e('Connect Google Ads →', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></a>
                                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . WPD_Admin_Menu::$settings_slug . '&subpage=facebook' ) ); ?>" class="wpd-gs-link"><?php esc_html_e('Connect Facebook Ads →', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></a>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="wpd-gs-action-card<?php echo ! $is_pro ? ' wpd-gs-pro-feature' : ''; ?>">
                                        <div class="wpd-gs-action-content">
                                            <h4>
                                                <span class="dashicons dashicons-money-alt"></span>
                                                <?php esc_html_e('Add Business Expenses', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>
                                                <?php if ( ! $is_pro ) : ?>
                                                    <span class="wpd-gs-pro-badge" title="<?php esc_html_e('Pro Feature', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>"><?php esc_html_e('Pro', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></span>
                                                <?php endif; ?>
                                            </h4>
                                            <p><?php esc_html_e('Track rent, software subscriptions, salaries, and other costs for complete P&L reports.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . WPD_Admin_Menu::$manage_expenses_slug ) ); ?>" class="wpd-gs-link"><?php esc_html_e('Open Expense Manager →', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></a>
                                        </div>
                                    </div>
                                    
                                    <div class="wpd-gs-action-card">
                                        <div class="wpd-gs-action-content">
                                            <h4>
                                                <span class="dashicons dashicons-admin-generic"></span>
                                                <?php esc_html_e('Review All Settings', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>
                                            </h4>
                                            <p><?php esc_html_e('Fine-tune currency conversion, order statuses, and calculation methods.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
                                            <a href="<?php echo esc_url( wpd_admin_page_url( 'settings' ) ); ?>" class="wpd-gs-link"><?php esc_html_e('Open Settings →', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="wpd-gs-help-section">
                                <span class="dashicons dashicons-editor-help"></span>
                                <p><?php esc_html_e('Need help? Check out our', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?> <a href="https://wpdavies.dev/documentation/alpha-insights/getting-started/installing-alpha-insights/?utm_campaign=Alpha+Insights+Documentation&utm_source=Alpha+Insights+Plugin" target="_blank"><?php esc_html_e('documentation', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></a> <?php esc_html_e('or', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?> <a href="https://wpdavies.dev/contact-us/" target="_blank"><?php esc_html_e('contact support', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></a>.</p>
                            </div>
                        </div>
                        
                    </div>
                    
                    <!-- Footer with Navigation -->
                    <div class="wpd-gs-modal-footer">
                        <div class="wpd-gs-footer-left">
                            <button type="button" class="wpd-gs-button wpd-gs-button-text wpd-gs-button-skip">
                                <?php esc_html_e('Skip Setup', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>
                            </button>
                        </div>
                        <div class="wpd-gs-footer-right">
                            <button type="button" class="wpd-gs-button wpd-gs-button-secondary wpd-gs-button-prev" style="display: none;">
                                <?php esc_html_e('Previous', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>
                            </button>
                            <button type="button" class="wpd-gs-button wpd-gs-button-primary wpd-gs-button-next">
                                <?php esc_html_e('Let\'s Get Started', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>
                            </button>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
        <?php
    }

}
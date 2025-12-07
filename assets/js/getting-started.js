/**
 * Getting Started Wizard JavaScript
 * Handles step navigation and settings save
 * 
 * @package Alpha Insights
 * @version 5.0.0
 * @since 5.0.0
 */

(function($) {
    'use strict';

    // State management
    let currentStep = 1;
    const isPro = wpdGettingStarted.isPro || false;
    const totalSteps = isPro ? 4 : 3; // Free version skips license step

    /**
     * Initialize the getting started wizard
     */
    function init() {
        // Button event handlers
        $('.wpd-gs-button-next').on('click', handleNext);
        $('.wpd-gs-button-prev').on('click', handlePrevious);
        $('.wpd-gs-button-skip').on('click', handleSkip);
        $('.wpd-gs-modal-close').on('click', handleClose);
        
        // Update button text for first step
        updateNavigationButtons();
    }

    /**
     * Navigate to the next step
     */
    function handleNext() {
        const $button = $('.wpd-gs-button-next');
        
        // Step 2 (License) -> Step 3: Save license before moving (Pro only)
        if (isPro && currentStep === 2) {
            // Check if license is already active
            if (wpdGettingStarted.licenseStatus === 'active') {
                // License already active, just move to next step
                currentStep++;
                showStep(currentStep);
                return;
            }
            
            // Try to save and activate license
            saveLicense($button);
            return;
        }
        
        // Step 3 (Settings) -> Step 4: Save settings before moving (Pro)
        // Step 2 (Settings) -> Step 3: Save settings before moving (Free)
        const settingsStep = isPro ? 3 : 2;
        if (currentStep === settingsStep) {
            saveSettings($button);
            return;
        }
        
        // Other steps: Just navigate
        if (currentStep < totalSteps) {
            currentStep++;
            showStep(currentStep);
        } else {
            // Final step - redirect to sales report
            redirectToSalesReport();
        }
    }

    /**
     * Navigate to the previous step
     */
    function handlePrevious() {
        if (currentStep > 1) {
            currentStep--;
            showStep(currentStep);
        }
    }

    /**
     * Skip setup and go to sales report
     */
    function handleSkip() {
        redirectToSalesReport();
    }

    /**
     * Close modal and go to sales report
     */
    function handleClose() {
        redirectToSalesReport();
    }

    /**
     * Show a specific step
     * @param {number} stepNumber - The logical step to show (1-3 for free, 1-4 for pro)
     */
    function showStep(stepNumber) {
        // Update step visibility - show the HTML step with matching class number
        $('.wpd-gs-step').removeClass('active');
        $('.wpd-gs-step-' + stepNumber).addClass('active');
        
        // Update progress indicators
        // Progress step data-step values match logical step numbers:
        // Free: 1 (Welcome), 2 (Settings), 3 (Ready)
        // Pro: 1 (Welcome), 2 (License), 3 (Settings), 4 (Ready)
        $('.wpd-gs-progress-step').removeClass('active completed');
        $('.wpd-gs-progress-step').each(function() {
            const stepNum = parseInt($(this).data('step'), 10);
            
            // Mark steps before current as completed
            if (stepNum < stepNumber) {
                $(this).addClass('completed');
            } 
            // Mark current step as active
            else if (stepNum === stepNumber) {
                $(this).addClass('active');
            }
        });
        
        // Update navigation buttons
        updateNavigationButtons();
        
        // Scroll to top of content
        $('.wpd-gs-modal-body').scrollTop(0);
    }

    /**
     * Update navigation button states and text
     */
    function updateNavigationButtons() {
        const $prevButton = $('.wpd-gs-button-prev');
        const $nextButton = $('.wpd-gs-button-next');
        const $skipButton = $('.wpd-gs-button-skip');
        
        // Previous button visibility
        if (currentStep === 1) {
            $prevButton.hide();
        } else {
            $prevButton.show();
        }
        
        // Next button text
        if (currentStep === 1) {
            $nextButton.text("Let's Get Started");
        } else if (isPro && currentStep === 2) {
            // License step (Pro only)
            // Check if license is already active
            if (wpdGettingStarted.licenseStatus === 'active') {
                $nextButton.text('Continue');
            } else {
                $nextButton.html('<span class="dashicons dashicons-yes-alt"></span> Activate & Continue');
            }
        } else if (currentStep === (isPro ? 3 : 2)) {
            // Settings step
            $nextButton.html('<span class="dashicons dashicons-saved"></span> Save & Continue');
        } else if (currentStep === (isPro ? 4 : 3)) {
            // Final step
            $nextButton.html('<span class="dashicons dashicons-yes"></span> Done');
        }
        
        // Hide skip button on final step
        if (currentStep === (isPro ? 4 : 3)) {
            $skipButton.hide();
        } else {
            $skipButton.show();
        }
    }

    /**
     * Save and activate license via AJAX
     * @param {jQuery} $button - The button that triggered the save
     */
    function saveLicense($button) {
        // Get license key
        const licenseKey = $('#license_key').val().trim();
        
        if (!licenseKey) {
            showLicenseMessage('Please enter a license key', 'error');
            return;
        }
        
        // Add button loading state
        $button.addClass('loading').prop('disabled', true);
        
        // Make AJAX request
        $.ajax({
            url: wpdGettingStarted.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpd_getting_started_activate_license',
                nonce: wpdGettingStarted.nonce,
                license_key: licenseKey
            },
            success: function(response) {
                if (response.success) {
                    showLicenseMessage(response.data.message || 'License activated successfully!', 'success');
                    
                    // Update global license status
                    wpdGettingStarted.licenseStatus = 'active';
                    
                    // Move to next step after a brief delay
                    setTimeout(function() {
                        currentStep++;
                        showStep(currentStep);
                    }, 1000);
                } else {
                    showLicenseMessage(response.data || 'Failed to activate license. Please check your license key and try again.', 'error');
                }
            },
            error: function(xhr, status, error) {
                showLicenseMessage('Failed to activate license. Please try again.', 'error');
                console.error('AJAX Error:', error);
            },
            complete: function() {
                // Remove button loading state
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    }

    /**
     * Show license activation message
     * @param {string} message - The message to display
     * @param {string} type - The type of message ('success' or 'error')
     */
    function showLicenseMessage(message, type) {
        const $messageDiv = $('#wpd-gs-license-status-message');
        
        $messageDiv
            .removeClass('success error')
            .addClass(type)
            .html(message)
            .slideDown();
        
        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                $messageDiv.slideUp();
            }, 5000);
        }
    }

    /**
     * Save settings via AJAX
     * @param {jQuery} $button - The button that triggered the save
     */
    function saveSettings($button) {
        // Get form data
        const formData = serializeFormData();
        
        // Add button loading state
        $button.addClass('loading').prop('disabled', true);
        
        // Make AJAX request
        $.ajax({
            url: wpdGettingStarted.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpd_save_getting_started_settings',
                nonce: wpdGettingStarted.nonce,
                settings: formData
            },
            success: function(response) {
                if (response.success) {
                    // Move to next step
                    currentStep++;
                    showStep(currentStep);
                } else {
                    // Show error message
                    showNotification('Error saving settings: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Failed to save settings. Please try again.', 'error');
                console.error('AJAX Error:', error);
            },
            complete: function() {
                // Remove button loading state
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    }

    /**
     * Serialize form data into an object
     * @returns {Object} Form data as key-value pairs
     */
    function serializeFormData() {
        const formData = {};
        const $form = $('#wpd-gs-settings-form');
        
        // Get all form inputs
        $form.find('input[type="number"]').each(function() {
            const name = $(this).attr('name');
            const value = $(this).val();
            
            if (name) {
                // Handle nested arrays (e.g., payment_gateway_costs[stripe][percent_of_sales])
                if (name.includes('[')) {
                    setNestedValue(formData, name, value);
                } else {
                    formData[name] = value;
                }
            }
        });
        
        return formData;
    }

    /**
     * Set nested object value from bracket notation
     * @param {Object} obj - The object to set the value on
     * @param {string} path - The path in bracket notation (e.g., "gateway[stripe][fee]")
     * @param {*} value - The value to set
     */
    function setNestedValue(obj, path, value) {
        // Parse path like "payment_gateway_costs[stripe][percent_of_sales]"
        // Match everything that's NOT a bracket character
        const matches = path.match(/([^\[\]]+)/g);
        
        if (!matches || matches.length === 0) return;
        
        let current = obj;
        for (let i = 0; i < matches.length - 1; i++) {
            const key = matches[i];
            if (!current[key]) {
                current[key] = {};
            }
            current = current[key];
        }
        
        current[matches[matches.length - 1]] = value;
    }

    /**
     * Show a notification message
     * @param {string} message - The message to display
     * @param {string} type - The type of notification ('success' or 'error')
     */
    function showNotification(message, type) {
        // Create notification element
        const $notification = $('<div>')
            .addClass('wpd-gs-notification')
            .addClass('wpd-gs-notification-' + type)
            .html(message);
        
        // Append to body
        $('body').append($notification);
        
        // Show notification
        setTimeout(function() {
            $notification.addClass('active');
        }, 10);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $notification.removeClass('active');
            setTimeout(function() {
                $notification.remove();
            }, 300);
        }, 5000);
    }

    /**
     * Redirect to sales report page
     */
    function redirectToSalesReport() {
        window.location.href = wpdGettingStarted.salesReportUrl;
    }

    // Initialize when document is ready
    $(document).ready(function() {
        init();
    });

})(jQuery);

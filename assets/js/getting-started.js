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
    const totalSteps = 3; // Free version has 3 steps

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
        
        // Step 2 (Settings) -> Step 3: Save settings before moving
        if (currentStep === 2) {
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
     * @param {number} stepNumber - The logical step to show (1-3 for free version)
     */
    function showStep(stepNumber) {
        // Update step visibility - show the HTML step with matching class number
        $('.wpd-gs-step').removeClass('active');
        $('.wpd-gs-step-' + stepNumber).addClass('active');
        
        // Update progress indicators
        // Progress step data-step values match logical step numbers:
        // Free: 1 (Welcome), 2 (Settings), 3 (Ready)
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
        } else if (currentStep === 2) {
            // Settings step
            $nextButton.html('<span class="dashicons dashicons-saved"></span> Save & Continue');
        } else if (currentStep === 3) {
            // Final step
            $nextButton.html('<span class="dashicons dashicons-yes"></span> Done');
        }
        
        // Hide skip button on final step
        if (currentStep === 3) {
            $skipButton.hide();
        } else {
            $skipButton.show();
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

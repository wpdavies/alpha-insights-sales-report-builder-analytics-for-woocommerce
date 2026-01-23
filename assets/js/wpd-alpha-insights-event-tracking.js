/**
 * Alpha Insights Event Tracking
 * 
 * Manages WooCommerce event tracking with support for engaged session tracking
 * 
 * @see https://www.offset101.com/jquery-create-object-oriented-classes/
 * 
 * ## Sending Custom Events
 * 
 * You can send custom events to the tracking API using the global function:
 * 
 * ```javascript
 * WpdAiEventTracking({
 *     event_type: 'your_custom_event_name',
 *     event_quantity: 1,           // Optional, defaults to 1
 *     event_value: 0,              // Optional, defaults to 0
 *     object_id: 123,             // Optional, defaults to current post ID
 *     object_type: 'product',     // Optional, defaults to current post type
 *     product_id: 456,            // Optional, defaults to 0
 *     variation_id: 789,          // Optional, defaults to 0
 *     additional_data: {          // Optional, defaults to {}
 *         custom_field: 'value',
 *         another_field: 'another_value'
 *     }
 * });
 * ```
 * 
 * ### Example: Tracking a Blog Promo Click
 * 
 * ```javascript
 * $('.alpha-insights-blog-cta a').click(function() {
 *     WpdAiEventTracking({
 *         event_type: 'Blog Promo Click',
 *         additional_data: {
 *             promo_id: $(this).data('promo-id'),
 *             promo_location: 'sidebar'
 *         }
 *     });
 * });
 * ```
 * 
 * ### Required Parameters
 * - `event_type` (string): The name/type of the event being tracked
 * 
 * ### Optional Parameters
 * - `event_quantity` (number): Quantity associated with the event (default: 1)
 * - `event_value` (number): Monetary value associated with the event (default: 0)
 * - `object_id` (number): ID of the object being tracked (default: current post ID)
 * - `object_type` (string): Type of object being tracked (default: current post type)
 * - `product_id` (number): Product ID if tracking a product-related event (default: 0)
 * - `variation_id` (number): Variation ID if tracking a product variation (default: 0)
 * - `additional_data` (object): Any additional data to include with the event (default: {})
 * 
 * ### Notes
 * - If `object_type` is set to 'product', `product_id` will automatically be set to `object_id`
 * - All events are sent asynchronously via AJAX POST request
 * - Events are only sent if event tracking is enabled in the plugin settings
 * - When engaged session tracking is enabled, page views wait for user interaction before sending
 */
(function($) {
	'use strict';

	/**
	 * Main Event Tracking Class
	 */
	var WpdAiEventTracking = {
		
		// Configuration
		config: {
			initializedVariables: null,
			trackEngagedSessions: 0,
			engagedSessionCookie: 'wpd_ai_engaged_session',
			apiEndpoint: null,
			eventTrackingEnabled: 1,
			trackUser: 1,
			currentPostId: 0,
			currentPostType: '',
			eventTrackingToken: null
		},

		// State
		state: {
			engagedSessionSet: false,
			pageViewSent: false,
			scrollTimeout: null,
			pageLoaded: false,
			initialScrollPosition: 0
		},

		/**
		 * Initialize the tracking system
		 */
		init: function() {
			// Get initialized variables
			this.config.initializedVariables = (typeof wpdAlphaInsightsEventTracking !== 'undefined') 
				? wpdAlphaInsightsEventTracking 
				: null;

			if (!this.config.initializedVariables) {
				return false;
			}

			// Set configuration from localized variables
			this.config.trackEngagedSessions = (typeof this.config.initializedVariables.track_engaged_sessions !== 'undefined') 
				? parseInt(this.config.initializedVariables.track_engaged_sessions, 10) 
				: 0;

			this.config.apiEndpoint = (typeof this.config.initializedVariables.api_endpoint !== 'undefined') 
				? this.config.initializedVariables.api_endpoint 
				: null;

			this.config.eventTrackingEnabled = (typeof this.config.initializedVariables.event_tracking_enabled !== 'undefined') 
				? parseInt(this.config.initializedVariables.event_tracking_enabled, 10) 
				: 1;

			this.config.trackUser = (typeof this.config.initializedVariables.track_user !== 'undefined') 
				? parseInt(this.config.initializedVariables.track_user, 10) 
				: 1;

			this.config.currentPostId = (typeof this.config.initializedVariables.current_post_id !== 'undefined') 
				? this.config.initializedVariables.current_post_id 
				: 0;

			this.config.currentPostType = (typeof this.config.initializedVariables.current_post_type !== 'undefined') 
				? this.config.initializedVariables.current_post_type 
				: '';

			this.config.eventTrackingToken = (typeof this.config.initializedVariables.analytics_event_tracking_token !== 'undefined') 
				? this.config.initializedVariables.analytics_event_tracking_token 
				: null;

			// Check if user tracking is disabled (highest priority check)
			if (this.config.trackUser === 0) {
				return false;
			}

			// Check if tracking is disabled
			if (this.config.eventTrackingEnabled === 0) {
				return false;
			}

			// Check if API endpoint is available
			if (!this.config.apiEndpoint) {
				return false;
			}

			// Initialize engaged session tracking
			this.initEngagedSessionTracking();

			// Handle page views based on engaged session setting
			if (this.config.trackEngagedSessions === 1) {
				// Wait for engaged session before sending page view
				this.waitForEngagedSessionForPageView();
			} else {
				// Send page view immediately
				this.sendPageView();
			}

			// Initialize WooCommerce event listeners
			this.initWooCommerceEvents();

			// Initialize product click tracking
			this.initProductClickTracking();

			// Initialize form submission tracking
			this.initFormSubmissionTracking();
		},

		/**
		 * Initialize engaged session tracking
		 */
		initEngagedSessionTracking: function() {
			var self = this;

			// Don't automatically set engagedSessionSet based on cookie
			// We need actual interaction on this page to mark as engaged
			// The cookie check is only used to determine if we should wait

			// Set initial scroll position immediately
			self.state.initialScrollPosition = $(window).scrollTop();

			// Mark page as loaded after a short delay to prevent false scroll/click triggers
			// This prevents browser auto-scroll or other automatic events from triggering
			setTimeout(function() {
				self.state.pageLoaded = true;
			}, 500); // Wait 500ms after page load before accepting interaction events

			// Track on click (only after page is loaded to avoid false triggers)
			// Note: click events work on mobile but have ~300ms delay
			$(document).on('click', function(e) {
				// Only track if page is loaded (prevents immediate false triggers)
				if (self.state.pageLoaded) {
					self.markSessionAsEngaged();
				}
			});

			// Track on pointerdown for better mobile support (fires immediately on touch)
			// Pointer Events API handles mouse, touch, and pen interactions
			// This catches mobile touches immediately without the click delay
			$(document).on('pointerdown', function(e) {
				// Only track if page is loaded (prevents immediate false triggers)
				if (self.state.pageLoaded) {
					self.markSessionAsEngaged();
				}
			});

			// Track on scroll (with throttling and validation)
			$(window).on('scroll', function() {
				// Only track scroll if:
				// 1. Page is fully loaded
				// 2. User has actually scrolled (position changed from initial)
				if (!self.state.pageLoaded) {
					return;
				}

				var currentScrollPosition = $(window).scrollTop();
				// Only consider it a scroll if the position has changed from initial
				if (currentScrollPosition === self.state.initialScrollPosition) {
					return;
				}

				if (self.state.scrollTimeout) {
					clearTimeout(self.state.scrollTimeout);
				}
				self.state.scrollTimeout = setTimeout(function() {
					self.markSessionAsEngaged();
				}, 100);
			});
		},

		/**
		 * Mark session as engaged
		 */
		markSessionAsEngaged: function() {
			if (this.state.engagedSessionSet) {
				return; // Already set, don't fire again
			}

			// Set the cookie
			this.setCookie(this.config.engagedSessionCookie, '1');
			this.state.engagedSessionSet = true;

			// If we were waiting for engaged session to send page view, send it now
			if (this.config.trackEngagedSessions === 1 && !this.state.pageViewSent) {
				this.sendPageView();
			}
		},

		/**
		 * Wait for engaged session before sending page view
		 */
		waitForEngagedSessionForPageView: function() {
			// Check if cookie exists from previous interaction
			// If it does, we can send page view immediately (user was already engaged)
			// Otherwise, wait for actual interaction on this page
			if (this.getCookie(this.config.engagedSessionCookie)) {
				// Cookie exists from previous page - user was already engaged
				// Send page view immediately
				this.sendPageView();
				return;
			}

			// No cookie - wait for markSessionAsEngaged to be called
			// This will happen on first click or scroll
		},

		/**
		 * Send page view event
		 */
		sendPageView: function() {
			// Only send one page view per page load
			if (this.state.pageViewSent) {
				return;
			}

			var payload = {
				event_type: 'page_view',
				object_type: this.config.currentPostType,
				object_id: this.config.currentPostId
			};

			this.trackEvent(payload);
			this.state.pageViewSent = true;
		},

		/**
		 * Track a custom event
		 * 
		 * @param {Object} data Event data
		 */
		trackEvent: function(data) {
			if (!data || !data.event_type) {
				return false;
			}

			var payload = this.buildPayload(data);

			if (!payload) {
				return false;
			}

			return this.sendToAPI(payload);
		},

		/**
		 * Build event payload from data
		 * 
		 * @param {Object} data Raw event data
		 * @return {Object|false} Formatted payload or false on error
		 */
		buildPayload: function(data) {
			var payload = {
				page_href: document.location.href,
				event_type: data.event_type,
				event_quantity: (data.event_quantity) ? parseInt(data.event_quantity, 10) : 1,
				event_value: (data.event_value) ? parseFloat(data.event_value) : 0,
				object_id: (data.object_id !== undefined && data.object_id !== null) 
					? parseInt(data.object_id, 10) 
					: this.config.currentPostId,
				object_type: (data.object_type) 
					? data.object_type 
					: this.config.currentPostType,
				product_id: (data.product_id !== undefined && data.product_id !== null) 
					? parseInt(data.product_id, 10) 
					: 0,
				variation_id: (data.variation_id !== undefined && data.variation_id !== null) 
					? parseInt(data.variation_id, 10) 
					: 0,
				additional_data: (data.additional_data) ? data.additional_data : {}
			};

			// If object type is product, set product_id to object_id
			if (payload.object_type === 'product') {
				payload.product_id = payload.object_id;
			}

			return payload;
		},

		/**
		 * Send payload to API
		 * 
		 * @param {Object} payload Event payload
		 * @return {boolean} Success status
		 */
		sendToAPI: function(payload) {
			// Safety check: Don't send if user tracking is disabled
			if (this.config.trackUser === 0) {
				return false;
			}

			if (!this.config.apiEndpoint) {
				return false;
			}

			// Add event tracking token to payload for validation
			// Token is required for API validation - log warning if missing
			if (this.config.eventTrackingToken) {
				payload['event-tracking-token'] = this.config.eventTrackingToken;
			} else {
				// Token is missing - this will cause validation to fail
				// Log warning in development (only if console is available)
				if (typeof console !== 'undefined' && console.warn) {
					console.warn('Alpha Insights: Event tracking token is missing. Events may be rejected by the server.');
				}
			}

			$.ajax({
				url: this.config.apiEndpoint,
				method: 'POST',
				contentType: 'application/json; charset=UTF-8',
				data: JSON.stringify(payload),
				error: function(xhr, status, error) {
					console.error('Alpha Insights: Event tracking failed', error);
				}
			});

			return true;
		},

		/**
		 * Initialize product click tracking
		 */
		initProductClickTracking: function() {
			var self = this;

			$('.products .product a').on('click', function() {
				var $link = $(this);
				var $product = $link.closest('.product');
				var productId = 0;

				// Try to get product ID from data attribute
				var pid = $product.find('.wpd-ai-event-tracking-product-id').data('product-id');
				if (pid) {
					productId = parseInt(pid, 10);
				}

				var payload = {
					event_type: 'product_click',
					event_quantity: 1,
					event_value: 0,
					object_id: productId,
					object_type: 'product',
					product_id: productId,
					variation_id: 0
				};

				self.trackEvent(payload);
			});
		},

		/**
		 * Initialize form submission tracking
		 */
		initFormSubmissionTracking: function() {
			var self = this;

			$('body').on('submit', 'form', function(e) {
				var $form = $(this);

				// Determine form identifier
				var formId = $form.attr('id') 
					|| $form.attr('name')
					|| $form.attr('class') 
					|| 'unknown_form';

				// Combine class names if form_id is the same as class
				if (!formId || formId === $form.attr('class')) {
					var classNames = $form.attr('class');
					if (classNames) {
						formId = '.' + classNames.trim().split(/\s+/).join('.');
					}
				}

				var additionalData = {
					form_id: formId || 'unknown_form',
					form_element_id: $form.attr('id') || '',
					form_element_name: $form.attr('name') || '',
					form_element_class: $form.attr('class') || '',
					form_element_action: $form.attr('action') || '',
					form_method: $form.attr('method') || ''
				};

				var payload = {
					event_type: 'form_submit',
					additional_data: additionalData
				};

				// If form class names include "cart", don't send off the request, this is likely handled elsewhere
				var formClasses = $form.attr('class') || '';
				if (formClasses.indexOf('cart') !== -1) {
					return;
				}

				self.trackEvent(payload);
			});
		},

		/**
		 * Initialize WooCommerce standard events
		 */
		initWooCommerceEvents: function() {
			var self = this;
			var standardWooEvents = [
				'updated_checkout',
				'payment_method_selected',
				'checkout_error',
				'wc_cart_emptied',
				'updated_shipping_method',
				'applied_coupon',
				'removed_coupon'
			];

			// Note: "init_checkout" is not included - we use page views to track this server side

			standardWooEvents.forEach(function(eventType) {
				$('body').on(eventType, function(event, additional_data) {
					var payload = {
						event_type: eventType
					};

					// Special handling for checkout_error
					if (eventType === 'checkout_error') {
						// Delay to allow error messages to load
						setTimeout(function() {
							var errorMessages = $('.woocommerce-error, .woocommerce-notices-wrapper').text().trim();
							payload.additional_data = {
								error_message: errorMessages
							};
							self.trackEvent(payload);
						}, 300);
					} else {
						self.trackEvent(payload);
					}
				});
			});
		},

		/**
		 * Get cookie value by name
		 * 
		 * @param {string} name Cookie name
		 * @return {string|null} Cookie value or null
		 */
		getCookie: function(name) {
			var value = '; ' + document.cookie;
			var parts = value.split('; ' + name + '=');
			if (parts.length === 2) {
				return parts.pop().split(';').shift();
			}
			return null;
		},

		/**
		 * Set session cookie
		 * 
		 * @param {string} name Cookie name
		 * @param {string} value Cookie value
		 */
		setCookie: function(name, value) {
			// Session cookie (expires when browser closes)
			document.cookie = name + '=' + value + '; path=/; SameSite=Lax';
		}
	};

	// Initialize asynchronously to avoid blocking page ready event
	// This improves page speed scores by not delaying the document ready event
	// 
	// Industry best practice for analytics/tracking scripts:
	// 1. Use requestIdleCallback (runs when browser is idle) - modern browsers
	// 2. Fallback to requestAnimationFrame (runs before next paint) - good timing
	// 3. Fallback to setTimeout (runs in next event loop) - universal support
	//
	// This approach is used by Google Analytics, Facebook Pixel, and other major tracking scripts
	function initializeTracking() {
		WpdAiEventTracking.init();
	}

	// Try requestIdleCallback first (runs when browser is idle, best for non-critical scripts)
	if (typeof requestIdleCallback !== 'undefined') {
		requestIdleCallback(initializeTracking, { timeout: 2000 }); // Max 2s wait
	}
	// Fallback to requestAnimationFrame (runs before next repaint, widely supported)
	else if (typeof requestAnimationFrame !== 'undefined') {
		requestAnimationFrame(initializeTracking);
	}
	// Final fallback to setTimeout (universal support)
	else {
		setTimeout(initializeTracking, 0);
	}

	// Expose globally for backwards compatibility
	// This is available immediately, even before initialization completes
	window.WpdAiEventTracking = function(data) {
		// If not initialized yet, the call will be handled gracefully
		// The init() method checks for required variables before proceeding
		return WpdAiEventTracking.trackEvent(data);
	};

})(jQuery);

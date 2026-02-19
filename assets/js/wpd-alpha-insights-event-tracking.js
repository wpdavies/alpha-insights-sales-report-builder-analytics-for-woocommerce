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
			currentPostId: 0,
			currentPostType: '',
			eventTrackingToken: null,
			enableLogging: 0
		},

		// State
		state: {
			engagedSessionSet: false,
			pageViewSent: false,
			scrollTimeout: null,
			pageLoaded: false,
			initialScrollPosition: 0,
			initialized: false
		},

		/**
		 * Reset state for BFCache restore
		 * 
		 * BFCache (Back/Forward Cache) preserves the JavaScript heap, so state
		 * from the previous visit persists. We need to reset it on page restore.
		 */
		resetState: function() {
			this.log('log', 'Resetting state for BFCache restore or new page load', {
				previousState: {
					pageViewSent: this.state.pageViewSent,
					engagedSessionSet: this.state.engagedSessionSet,
					pageLoaded: this.state.pageLoaded,
					initialized: this.state.initialized
				}
			});

			// Clear any pending timeouts
			if (this.state.scrollTimeout) {
				clearTimeout(this.state.scrollTimeout);
				this.state.scrollTimeout = null;
			}

			// Reset state
			this.state.engagedSessionSet = false;
			this.state.pageViewSent = false;
			this.state.pageLoaded = false;
			this.state.initialScrollPosition = 0;
			// Note: We keep initialized = true to avoid re-initializing event listeners

			this.log('log', 'State reset complete');
		},

		/**
		 * Logging utility
		 * 
		 * @param {string} level Log level: 'log', 'warn', 'error'
		 * @param {string} message Log message
		 * @param {*} data Optional data to log
		 */
		log: function(level, message, data) {
			if (typeof console === 'undefined') {
				return;
			}

			var prefix = '[Alpha Insights]';
			var fullMessage = prefix + ' ' + message;

			// Always log warnings and errors
			if (level === 'warn' || level === 'error') {
				if (level === 'warn' && console.warn) {
					if (data !== undefined) {
						console.warn(fullMessage, data);
					} else {
						console.warn(fullMessage);
					}
				} else if (level === 'error' && console.error) {
					if (data !== undefined) {
						console.error(fullMessage, data);
					} else {
						console.error(fullMessage);
					}
				}
			}
			// Only log verbose info if logging is enabled
			else if (level === 'log' || level === 'info') {
				if (this.config.enableLogging === 1 && console.log) {
					if (data !== undefined) {
						console.log(fullMessage, data);
					} else {
						console.log(fullMessage);
					}
				}
			}
		},

		/**
		 * Initialize the tracking system
		 */
		init: function() {
			// Reset state on init to ensure clean state
			// This handles cases where script is cached and state might be stale
			if (!this.state.initialized) {
				this.log('log', 'First initialization - resetting state');
				this.resetState();
			} else {
				this.log('log', 'Re-initialization detected - state may be stale, resetting');
				this.resetState();
			}

			// Get initialized variables
			this.config.initializedVariables = (typeof wpdAlphaInsightsEventTracking !== 'undefined') 
				? wpdAlphaInsightsEventTracking 
				: null;

			if (!this.config.initializedVariables) {
				this.log('warn', 'Initialization failed: wpdAlphaInsightsEventTracking is not defined');
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

			this.config.currentPostId = (typeof this.config.initializedVariables.current_post_id !== 'undefined') 
				? this.config.initializedVariables.current_post_id 
				: 0;

			this.config.currentPostType = (typeof this.config.initializedVariables.current_post_type !== 'undefined') 
				? this.config.initializedVariables.current_post_type 
				: '';

			this.config.eventTrackingToken = (typeof this.config.initializedVariables.analytics_event_tracking_token !== 'undefined') 
				? this.config.initializedVariables.analytics_event_tracking_token 
				: null;

			// Set logging configuration (note: PHP has typo "enbable" but we'll use what's provided)
			this.config.enableLogging = (typeof this.config.initializedVariables.enbable_event_tracking_logging !== 'undefined') 
				? parseInt(this.config.initializedVariables.enbable_event_tracking_logging, 10) 
				: 0;

			this.log('log', 'Initialization started', {
				trackEngagedSessions: this.config.trackEngagedSessions,
				eventTrackingEnabled: this.config.eventTrackingEnabled,
				currentPostId: this.config.currentPostId,
				currentPostType: this.config.currentPostType,
				enableLogging: this.config.enableLogging
			});

			// Check if tracking is disabled
			if (this.config.eventTrackingEnabled === 0) {
				this.log('log', 'Initialization skipped: Event tracking is disabled');
				return false;
			}

			// Check if API endpoint is available
			if (!this.config.apiEndpoint) {
				this.log('warn', 'Initialization failed: API endpoint is not available');
				return false;
			}

			this.log('log', 'API endpoint configured', this.config.apiEndpoint);

			// Initialize engaged session tracking
			this.initEngagedSessionTracking();

			// Handle page views based on engaged session setting
			this.log('log', 'Determining page view strategy', {
				trackEngagedSessions: this.config.trackEngagedSessions,
				pageViewSent: this.state.pageViewSent
			});

			if (this.config.trackEngagedSessions === 1) {
				this.log('log', 'Engaged session tracking enabled - waiting for user interaction', {
					strategy: 'wait_for_engagement',
					willCheckCookie: true
				});
				// Wait for engaged session before sending page view
				this.waitForEngagedSessionForPageView();
			} else {
				this.log('log', 'Engaged session tracking disabled - sending page view immediately', {
					strategy: 'immediate',
					reason: 'trackEngagedSessions is 0'
				});
				// Send page view immediately
				this.sendPageView();
			}

			// Initialize WooCommerce event listeners
			this.initWooCommerceEvents();

			// Initialize product click tracking
			this.initProductClickTracking();

			// Initialize form submission tracking
			this.initFormSubmissionTracking();

			// Mark as initialized
			this.state.initialized = true;

			this.log('log', 'Initialization completed successfully');
		},

		/**
		 * Initialize engaged session tracking
		 */
		initEngagedSessionTracking: function() {
			var self = this;

			this.log('log', 'Initializing engaged session tracking', {
				alreadyInitialized: this.state.initialized
			});

			// Remove any existing handlers to prevent duplicates
			// This is important for BFCache scenarios where init might be called again
			$(document).off('click.alphaInsightsEngaged');
			$(document).off('pointerdown.alphaInsightsEngaged');
			$(window).off('scroll.alphaInsightsEngaged');

			// Check if cookie already exists - if so, we don't need to listen for engagement
			// This saves processing power by not setting up unnecessary event listeners
			var existingCookie = this.getCookie(this.config.engagedSessionCookie);
			if (existingCookie) {
				this.log('log', 'Engaged session cookie already exists - skipping event listener setup to save processing power', {
					cookieValue: existingCookie
				});
				// Still set initial scroll position for consistency
				self.state.initialScrollPosition = $(window).scrollTop();
				// Mark as engaged since cookie exists
				this.state.engagedSessionSet = true;
				return; // No need to set up listeners
			}

			// Don't automatically set engagedSessionSet based on cookie
			// We need actual interaction on this page to mark as engaged
			// The cookie check is only used to determine if we should wait

			// Set initial scroll position immediately
			self.state.initialScrollPosition = $(window).scrollTop();
			this.log('log', 'Initial scroll position set', self.state.initialScrollPosition);

			// Mark page as loaded after a short delay to prevent false scroll/click triggers
			// This prevents browser auto-scroll or other automatic events from triggering
			// Note: On BFCache restore, this will be set to true immediately by pageshow handler
			if (!self.state.pageLoaded) {
				setTimeout(function() {
					self.state.pageLoaded = true;
					self.log('log', 'Page marked as loaded - interaction tracking now active');
				}, 500); // Wait 500ms after page load before accepting interaction events
			} else {
				this.log('log', 'Page already marked as loaded (likely BFCache restore)');
			}

			// Track on click (only after page is loaded to avoid false triggers)
			// Note: click events work on mobile but have ~300ms delay
			// Using namespaced events to allow easy removal
			$(document).on('click.alphaInsightsEngaged', function(e) {
				// Only track if page is loaded (prevents immediate false triggers)
				if (self.state.pageLoaded) {
					self.log('log', 'Click interaction detected');
					self.markSessionAsEngaged();
				} else {
					self.log('log', 'Click detected but page not yet loaded, ignoring');
				}
			});

			// Track on pointerdown for better mobile support (fires immediately on touch)
			// Pointer Events API handles mouse, touch, and pen interactions
			// This catches mobile touches immediately without the click delay
			$(document).on('pointerdown.alphaInsightsEngaged', function(e) {
				// Only track if page is loaded (prevents immediate false triggers)
				if (self.state.pageLoaded) {
					self.log('log', 'Pointer interaction detected');
					self.markSessionAsEngaged();
				} else {
					self.log('log', 'Pointer interaction detected but page not yet loaded, ignoring');
				}
			});

			// Track on scroll (with throttling and validation)
			$(window).on('scroll.alphaInsightsEngaged', function() {
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
					self.log('log', 'Scroll interaction detected');
					self.markSessionAsEngaged();
				}, 100);
			});

			this.log('log', 'Engaged session event listeners set up - will be removed once engagement is detected');
		},

		/**
		 * Remove engaged session event listeners
		 * Called once engagement is detected to save processing power
		 */
		removeEngagedSessionListeners: function() {
			this.log('log', 'Removing engaged session event listeners to save processing power');

			// Clear any pending scroll timeout
			if (this.state.scrollTimeout) {
				clearTimeout(this.state.scrollTimeout);
				this.state.scrollTimeout = null;
			}

			// Remove event listeners using namespaced events
			$(document).off('click.alphaInsightsEngaged');
			$(document).off('pointerdown.alphaInsightsEngaged');
			$(window).off('scroll.alphaInsightsEngaged');

			this.log('log', 'Engaged session event listeners removed');
		},

		/**
		 * Mark session as engaged
		 */
		markSessionAsEngaged: function() {
			this.log('log', 'markSessionAsEngaged() called', {
				alreadyEngaged: this.state.engagedSessionSet,
				trackEngagedSessions: this.config.trackEngagedSessions,
				pageViewSent: this.state.pageViewSent
			});

			if (this.state.engagedSessionSet) {
				this.log('log', 'Session already marked as engaged, skipping');
				return; // Already set, don't fire again
			}

			this.log('log', 'Marking session as engaged', {
				cookieName: this.config.engagedSessionCookie
			});

			// Set the cookie
			this.setCookie(this.config.engagedSessionCookie, '1');
			this.state.engagedSessionSet = true;

			// Verify cookie was set
			var cookieCheck = this.getCookie(this.config.engagedSessionCookie);
			this.log('log', 'Cookie set, verification', {
				cookieSet: !!cookieCheck,
				cookieValue: cookieCheck
			});

			// Remove event listeners now that engagement is detected
			// This saves processing power by not listening to clicks/scrolls anymore
			this.removeEngagedSessionListeners();

			// If we were waiting for engaged session to send page view, send it now
			if (this.config.trackEngagedSessions === 1 && !this.state.pageViewSent) {
				this.log('log', 'Engaged session detected - sending page view now', {
					trackEngagedSessions: this.config.trackEngagedSessions,
					pageViewSent: this.state.pageViewSent
				});
				this.sendPageView();
			} else {
				this.log('log', 'Engaged session set but page view conditions not met', {
					trackEngagedSessions: this.config.trackEngagedSessions,
					pageViewSent: this.state.pageViewSent,
					reason: this.config.trackEngagedSessions !== 1 ? 'engaged sessions not enabled' : 'page view already sent'
				});
			}
		},

		/**
		 * Wait for engaged session before sending page view
		 */
		waitForEngagedSessionForPageView: function() {
			this.log('log', 'waitForEngagedSessionForPageView() called', {
				pageViewSent: this.state.pageViewSent,
				engagedSessionSet: this.state.engagedSessionSet
			});

			// Check if cookie exists from previous interaction
			// If it does, we can send page view immediately (user was already engaged)
			// Otherwise, wait for actual interaction on this page
			var existingCookie = this.getCookie(this.config.engagedSessionCookie);
			
			this.log('log', 'Checking for engaged session cookie', {
				cookieName: this.config.engagedSessionCookie,
				cookieFound: !!existingCookie,
				cookieValue: existingCookie
			});

			if (existingCookie) {
				// Cookie exists from previous page - user was already engaged
				this.log('log', 'Engaged session cookie found from previous page - sending page view immediately', {
					cookieValue: existingCookie
				});
				// Send page view immediately
				this.sendPageView();
				return;
			}

			this.log('log', 'No engaged session cookie found - waiting for user interaction', {
				waitingFor: ['click', 'pointerdown', 'scroll'],
				pageLoaded: this.state.pageLoaded
			});
			// No cookie - wait for markSessionAsEngaged to be called
			// This will happen on first click or scroll
		},

		/**
		 * Send page view event
		 */
		sendPageView: function() {
			this.log('log', 'sendPageView() called', {
				pageViewAlreadySent: this.state.pageViewSent,
				trackEngagedSessions: this.config.trackEngagedSessions,
				engagedSessionSet: this.state.engagedSessionSet,
				pageLoaded: this.state.pageLoaded
			});

			// Only send one page view per page load
			if (this.state.pageViewSent) {
				this.log('log', 'Page view already sent, skipping duplicate', {
					alreadySent: true,
					timestamp: new Date().toISOString()
				});
				return;
			}

			// Verify we have required data
			if (!this.config.apiEndpoint) {
				this.log('warn', 'Cannot send page view: API endpoint not available', {
					apiEndpoint: this.config.apiEndpoint
				});
				return;
			}

			if (this.config.eventTrackingEnabled === 0) {
				this.log('log', 'Cannot send page view: Event tracking is disabled');
				return;
			}

			this.log('log', 'Sending page view event', {
				postId: this.config.currentPostId,
				postType: this.config.currentPostType,
				url: document.location.href,
				apiEndpoint: this.config.apiEndpoint,
				hasToken: !!this.config.eventTrackingToken,
				timestamp: new Date().toISOString()
			});

			var payload = {
				event_type: 'page_view',
				object_type: this.config.currentPostType,
				object_id: this.config.currentPostId
			};

			this.log('log', 'Page view payload created', payload);

			var result = this.trackEvent(payload);
			this.state.pageViewSent = true;

			this.log('log', 'Page view tracking result', {
				success: result,
				pageViewSent: this.state.pageViewSent,
				timestamp: new Date().toISOString()
			});

			if (!result) {
				this.log('warn', 'Page view event failed to send - check previous logs for details');
			}
		},

		/**
		 * Track a custom event
		 * 
		 * @param {Object} data Event data
		 */
		trackEvent: function(data) {
			if (!data || !data.event_type) {
				this.log('warn', 'trackEvent called with invalid data', data);
				return false;
			}

			this.log('log', 'Tracking event: ' + data.event_type, data);

			var payload = this.buildPayload(data);

			if (!payload) {
				this.log('warn', 'Failed to build payload for event', data);
				return false;
			}

			this.log('log', 'Payload built successfully', payload);

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
		 * Uses sendBeacon for better reliability (especially during page unload),
		 * with AJAX fallback for older browsers or if sendBeacon fails.
		 * 
		 * @param {Object} payload Event payload
		 * @return {boolean} Success status
		 */
		sendToAPI: function(payload) {
			this.log('log', 'sendToAPI() called', {
				eventType: payload ? payload.event_type : 'unknown',
				hasApiEndpoint: !!this.config.apiEndpoint,
				apiEndpoint: this.config.apiEndpoint
			});

			if (!this.config.apiEndpoint) {
				this.log('warn', 'Sending blocked: API endpoint is not available');
				return false;
			}

			// Add event tracking token to payload for validation
			// Token is required for API validation - log warning if missing
			if (this.config.eventTrackingToken) {
				payload['event-tracking-token'] = this.config.eventTrackingToken;
				this.log('log', 'Event tracking token added to payload', {
					tokenLength: this.config.eventTrackingToken.length
				});
			} else {
				// Token is missing - this will cause validation to fail
				this.log('warn', 'Event tracking token is missing. Events may be rejected by the server.');
			}

			var payloadString = JSON.stringify(payload);
			var apiUrl = this.config.apiEndpoint;

			this.log('log', 'Sending event to API', {
				url: apiUrl,
				eventType: payload.event_type,
				payloadSize: payloadString.length,
				payloadPreview: payloadString.substring(0, 200) + (payloadString.length > 200 ? '...' : '')
			});

			// Try sendBeacon first (more reliable, especially during page unload)
			// sendBeacon is designed for analytics and works even when page is closing
			var sendBeaconAvailable = typeof navigator !== 'undefined' && navigator.sendBeacon;
			this.log('log', 'Checking sendBeacon availability', {
				available: sendBeaconAvailable,
				willUse: sendBeaconAvailable ? 'sendBeacon' : 'AJAX'
			});

			if (sendBeaconAvailable) {
				try {
					// Convert relative URL to absolute URL for sendBeacon
					// sendBeacon requires absolute URLs in some browsers
					var absoluteUrl = apiUrl;
					if (apiUrl.indexOf('http://') !== 0 && apiUrl.indexOf('https://') !== 0) {
						// Relative URL - convert to absolute
						absoluteUrl = window.location.origin + apiUrl;
						this.log('log', 'Converted relative URL to absolute', {
							original: apiUrl,
							absolute: absoluteUrl
						});
					}
					
					this.log('log', 'Attempting to send via sendBeacon', {
						url: absoluteUrl,
						payloadSize: payloadString.length
					});
					
					// sendBeacon accepts Blob, FormData, or string
					// Use Blob with JSON content type for proper API handling
					// Note: sendBeacon doesn't support credentials option like fetch,
					// but it automatically includes cookies for same-origin requests
					// This ensures user credentials are available on the server side
					var blob = new Blob([payloadString], { type: 'application/json; charset=UTF-8' });
					
					// sendBeacon returns true if successfully queued, false otherwise
					// Cookies are automatically included for same-origin requests
					var beaconResult = navigator.sendBeacon(absoluteUrl, blob);
					this.log('log', 'sendBeacon result', {
						success: beaconResult,
						url: absoluteUrl
					});

					if (beaconResult) {
						this.log('log', 'Event sent successfully via sendBeacon', {
							eventType: payload.event_type,
							method: 'sendBeacon'
						});
						return true;
					} else {
						this.log('warn', 'sendBeacon returned false - falling back to AJAX', {
							url: absoluteUrl,
							reason: 'sendBeacon returned false'
						});
					}
				} catch (e) {
					// sendBeacon failed, fall through to AJAX fallback
					this.log('warn', 'sendBeacon failed with error, falling back to AJAX', {
						error: e,
						errorMessage: e.message,
						errorStack: e.stack
					});
				}
			} else {
				this.log('log', 'sendBeacon not available - using AJAX fallback', {
					reason: 'navigator.sendBeacon is not available'
				});
			}

			// Fallback to AJAX (for older browsers or if sendBeacon failed)
			// AJAX provides better error handling and works in all browsers
			try {
				this.log('log', 'Sending event via AJAX', {
					url: apiUrl,
					method: 'POST',
					payloadSize: payloadString.length,
					jQueryAvailable: typeof $ !== 'undefined'
				});

				if (typeof $ === 'undefined') {
					this.log('error', 'jQuery not available for AJAX request');
					return false;
				}

				var self = this;
				var ajaxStartTime = Date.now();
				
				$.ajax({
					url: apiUrl,
					method: 'POST',
					contentType: 'application/json; charset=UTF-8',
					data: payloadString,
					timeout: 10000, // 10 second timeout
					xhrFields: {
						withCredentials: true // Include cookies/credentials for same-origin requests
					},
					success: function(data, textStatus, xhr) {
						var duration = Date.now() - ajaxStartTime;
						self.log('log', 'Event sent successfully via AJAX', {
							status: xhr.status,
							statusText: xhr.statusText,
							eventType: payload.event_type,
							duration: duration + 'ms',
							method: 'AJAX'
						});
					},
					error: function(xhr, status, error) {
						var duration = Date.now() - ajaxStartTime;
						self.log('error', 'Event tracking failed via AJAX', {
							status: xhr.status,
							statusText: xhr.statusText,
							error: error,
							statusCode: status,
							eventType: payload.event_type,
							duration: duration + 'ms',
							url: apiUrl,
							responseText: xhr.responseText ? xhr.responseText.substring(0, 200) : 'no response'
						});
					},
					complete: function(xhr, status) {
						var duration = Date.now() - ajaxStartTime;
						self.log('log', 'AJAX request completed', {
							status: status,
							httpStatus: xhr.status,
							duration: duration + 'ms',
							eventType: payload.event_type
						});
					}
				});
				return true;
			} catch (e) {
				// AJAX also failed (shouldn't happen, but handle gracefully)
				this.log('error', 'Both sendBeacon and AJAX failed', {
					error: e,
					errorMessage: e.message,
					errorStack: e.stack,
					eventType: payload.event_type
				});
				return false;
			}
		},

		/**
		 * Initialize product click tracking
		 */
		initProductClickTracking: function() {
			var self = this;

			$('body').on('click', '.products .product a', function() {
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
			var cookieStr = name + '=' + value + '; path=/; SameSite=Lax';
			if (typeof wpdAlphaInsightsEventTracking !== 'undefined' && wpdAlphaInsightsEventTracking.cookie_domain) {
				cookieStr += '; domain=' + wpdAlphaInsightsEventTracking.cookie_domain;
			}
			document.cookie = cookieStr;
		}
	};

	// Helper function to log before initialization is complete
	// Uses WpdAiEventTracking.log if available, otherwise falls back to console
	function logMessage(level, message, data) {
		if (WpdAiEventTracking && typeof WpdAiEventTracking.log === 'function') {
			WpdAiEventTracking.log(level, message, data);
		} else {
			// Fallback to console before initialization
			if (typeof console !== 'undefined') {
				var prefix = '[Alpha Insights]';
				var fullMessage = prefix + ' ' + message;
				if (level === 'warn' && console.warn) {
					if (data !== undefined) {
						console.warn(fullMessage, data);
					} else {
						console.warn(fullMessage);
					}
				} else if (level === 'error' && console.error) {
					if (data !== undefined) {
						console.error(fullMessage, data);
					} else {
						console.error(fullMessage);
					}
				} else if (console.log) {
					// For log/info, only show if we can't use the proper logging method
					// This ensures we see critical initialization messages
					if (data !== undefined) {
						console.log(fullMessage, data);
					} else {
						console.log(fullMessage);
					}
				}
			}
		}
	}

	// Initialize asynchronously to avoid blocking page ready event
	// This improves page speed scores by not delaying the document ready event
	// 
	// Industry best practice for analytics/tracking scripts:
	// 1. Use requestIdleCallback (runs when browser is idle) - modern browsers
	// 2. Fallback to requestAnimationFrame (runs before next paint) - good timing
	// 3. Fallback to setTimeout (runs in next event loop) - universal support
	//
	// This approach is used by Google Analytics, Facebook Pixel, and other major tracking scripts
	//
	// IMPORTANT: When scripts are cached/minified, they may execute before wp_localize_script
	// has added the wpdAlphaInsightsEventTracking variable. We need to wait for it to be available.
	function initializeTracking() {
		logMessage('log', 'initializeTracking() called', {
			documentReadyState: document.readyState,
			wpdAlphaInsightsEventTrackingAvailable: typeof wpdAlphaInsightsEventTracking !== 'undefined'
		});

		// Check if localized data is available
		// wp_localize_script outputs data inline, but cached scripts might execute before it
		if (typeof wpdAlphaInsightsEventTracking === 'undefined') {
			// Data not available yet - retry after a short delay
			// This handles cases where cached scripts load before localized data
			var retryCount = 0;
			var maxRetries = 50; // Try for up to 5 seconds (50 * 100ms)
			
			logMessage('warn', 'Localized data not available yet, starting retry mechanism');
			
			function retryInitialization() {
				retryCount++;
				if (typeof wpdAlphaInsightsEventTracking !== 'undefined') {
					// Data is now available, initialize
					logMessage('log', 'Localized data found on retry #' + retryCount + ', initializing...');
					WpdAiEventTracking.init();
				} else if (retryCount < maxRetries) {
					// Still not available, retry after 100ms
					// Only log every 10th retry to avoid spam
					if (retryCount % 10 === 0) {
						logMessage('log', 'Retry attempt #' + retryCount + ' of ' + maxRetries);
					}
					setTimeout(retryInitialization, 100);
				} else {
					// If max retries reached, give up (data might not be available on this page)
					logMessage('error', 'Failed to initialize: Localized data not available after ' + maxRetries + ' retries');
				}
			}
			
			// Start retry loop
			setTimeout(retryInitialization, 100);
			return;
		}
		
		// Data is available, initialize normally
		logMessage('log', 'Localized data available immediately, initializing...');
		WpdAiEventTracking.init();
	}

	// Wait for DOM to be ready before attempting initialization
	// This ensures wp_localize_script has had a chance to output its data
	function startInitialization() {
		var method = 'unknown';
		if (typeof requestIdleCallback !== 'undefined') {
			method = 'requestIdleCallback';
			logMessage('log', 'Starting initialization via requestIdleCallback', {
				documentReadyState: document.readyState,
				timeout: 2000
			});
			requestIdleCallback(initializeTracking, { timeout: 2000 }); // Max 2s wait
		}
		// Fallback to requestAnimationFrame (runs before next repaint, widely supported)
		else if (typeof requestAnimationFrame !== 'undefined') {
			method = 'requestAnimationFrame';
			logMessage('log', 'Starting initialization via requestAnimationFrame', {
				documentReadyState: document.readyState
			});
			requestAnimationFrame(initializeTracking);
		}
		// Final fallback to setTimeout (universal support)
		else {
			method = 'setTimeout';
			logMessage('log', 'Starting initialization via setTimeout', {
				documentReadyState: document.readyState
			});
			setTimeout(initializeTracking, 0);
		}
	}

	// Handle BFCache (Back/Forward Cache) restore
	// BFCache preserves the JS heap, so we need to detect restore and reset state
	// This is critical: without this, pageViewSent stays true and page views never fire
	window.addEventListener('pageshow', function(event) {
		logMessage('log', 'pageshow event fired', {
			persisted: event.persisted,
			readyState: document.readyState,
			isBFCacheRestore: event.persisted,
			currentState: {
				pageViewSent: WpdAiEventTracking.state.pageViewSent,
				pageLoaded: WpdAiEventTracking.state.pageLoaded,
				initialized: WpdAiEventTracking.state.initialized
			}
		});

		// If page was restored from BFCache, reset state and reinitialize
		if (event.persisted) {
			logMessage('warn', 'BFCache restore detected - resetting state and reinitializing', {
				previousState: {
					pageViewSent: WpdAiEventTracking.state.pageViewSent,
					engagedSessionSet: WpdAiEventTracking.state.engagedSessionSet,
					pageLoaded: WpdAiEventTracking.state.pageLoaded,
					initialized: WpdAiEventTracking.state.initialized
				},
				readyState: document.readyState
			});

			// Reset state (this clears pageViewSent, pageLoaded, etc.)
			// This is the critical fix - pageViewSent was staying true from previous visit
			WpdAiEventTracking.resetState();

			// Re-setup engaged session tracking state
			// BFCache pages are already fully loaded, so set pageLoaded immediately
			WpdAiEventTracking.state.initialScrollPosition = $(window).scrollTop();
			WpdAiEventTracking.state.pageLoaded = true; // BFCache pages are already loaded
			
			logMessage('log', 'State reset and page marked as loaded for BFCache restore');

			// Re-run page view logic based on engaged session setting
			// We need to reload config from localized data if available
			if (typeof wpdAlphaInsightsEventTracking !== 'undefined') {
				// Reload config in case it changed
				WpdAiEventTracking.config.trackEngagedSessions = (typeof wpdAlphaInsightsEventTracking.track_engaged_sessions !== 'undefined') 
					? parseInt(wpdAlphaInsightsEventTracking.track_engaged_sessions, 10) 
					: 0;
				WpdAiEventTracking.config.apiEndpoint = (typeof wpdAlphaInsightsEventTracking.api_endpoint !== 'undefined') 
					? wpdAlphaInsightsEventTracking.api_endpoint 
					: null;
				WpdAiEventTracking.config.eventTrackingEnabled = (typeof wpdAlphaInsightsEventTracking.event_tracking_enabled !== 'undefined') 
					? parseInt(wpdAlphaInsightsEventTracking.event_tracking_enabled, 10) 
					: 1;
				WpdAiEventTracking.config.currentPostId = (typeof wpdAlphaInsightsEventTracking.current_post_id !== 'undefined') 
					? wpdAlphaInsightsEventTracking.current_post_id 
					: 0;
				WpdAiEventTracking.config.currentPostType = (typeof wpdAlphaInsightsEventTracking.current_post_type !== 'undefined') 
					? wpdAlphaInsightsEventTracking.current_post_type 
					: '';
				WpdAiEventTracking.config.eventTrackingToken = (typeof wpdAlphaInsightsEventTracking.analytics_event_tracking_token !== 'undefined') 
					? wpdAlphaInsightsEventTracking.analytics_event_tracking_token 
					: null;

				logMessage('log', 'Config reloaded after BFCache restore', {
					trackEngagedSessions: WpdAiEventTracking.config.trackEngagedSessions,
					eventTrackingEnabled: WpdAiEventTracking.config.eventTrackingEnabled
				});

				// Check if tracking is enabled
				if (WpdAiEventTracking.config.eventTrackingEnabled === 0 || !WpdAiEventTracking.config.apiEndpoint) {
					logMessage('log', 'Tracking disabled or API endpoint missing - skipping page view after BFCache restore');
					return;
				}

				// Trigger page view based on engaged session setting
				if (WpdAiEventTracking.config.trackEngagedSessions === 1) {
					logMessage('log', 'BFCache restore: Engaged session tracking enabled - checking cookie');
					WpdAiEventTracking.waitForEngagedSessionForPageView();
				} else {
					logMessage('log', 'BFCache restore: Engaged session tracking disabled - sending page view immediately');
					WpdAiEventTracking.sendPageView();
				}
			} else {
				logMessage('warn', 'BFCache restore detected but localized data not available yet');
				// Wait a bit and try again (similar to initial load retry)
				var retryCount = 0;
				var maxRetries = 20; // 2 seconds max wait
				
				function retryBFCacheInit() {
					retryCount++;
					if (typeof wpdAlphaInsightsEventTracking !== 'undefined') {
						logMessage('log', 'Localized data now available after BFCache restore (retry #' + retryCount + ')');
						// Reload config and send page view
						if (WpdAiEventTracking.config.trackEngagedSessions === 1) {
							WpdAiEventTracking.waitForEngagedSessionForPageView();
						} else {
							WpdAiEventTracking.sendPageView();
						}
					} else if (retryCount < maxRetries) {
						setTimeout(retryBFCacheInit, 100);
					} else {
						logMessage('warn', 'Localized data not available after BFCache restore - giving up');
					}
				}
				
				setTimeout(retryBFCacheInit, 100);
			}
		}
	});

	// Wait for DOM to be ready
	logMessage('log', 'Script loaded, checking DOM ready state', {
		readyState: document.readyState,
		jQueryAvailable: typeof $ !== 'undefined',
		wpdAlphaInsightsEventTrackingAvailable: typeof wpdAlphaInsightsEventTracking !== 'undefined'
	});

	// Handle initial page load (not BFCache restore)
	// Note: On BFCache restore, readyState is already "complete" and DOMContentLoaded won't fire
	// The pageshow event handler will specifically handle BFCache restore
	function handleInitialLoad() {
		// Always reset state on script load to ensure clean state
		// This handles cases where script is cached and state might be stale
		// (BFCache restore will be handled separately by pageshow event)
		WpdAiEventTracking.resetState();
		WpdAiEventTracking.state.initialized = false; // Reset initialized flag for fresh init

		if (document.readyState === 'loading') {
			logMessage('log', 'DOM still loading - waiting for DOMContentLoaded event');
			document.addEventListener('DOMContentLoaded', function() {
				logMessage('log', 'DOMContentLoaded fired - starting initialization');
				startInitialization();
			});
		} else {
			// DOM is already ready (could be cached page, but not BFCache - that's handled by pageshow)
			logMessage('log', 'DOM already ready - starting initialization immediately', {
				readyState: document.readyState
			});
			startInitialization();
		}
	}

	handleInitialLoad();

	// Expose globally for backwards compatibility
	// This is available immediately, even before initialization completes
	window.WpdAiEventTracking = function(data) {
		// If not initialized yet, the call will be handled gracefully
		// The init() method checks for required variables before proceeding
		return WpdAiEventTracking.trackEvent(data);
	};

})(jQuery);

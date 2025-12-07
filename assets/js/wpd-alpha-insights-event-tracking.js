/**
 *
 *	Manage Custom Woocommerce event tracking 
 *  @see https://www.offset101.com/jquery-create-object-oriented-classes/
 * 
 *	@var page_href -> set by class
 * 
 *  @var event_type -> set by caller (Required)
 *  @var event_quantity -> set caller
 *  @var event_value -> set by caller
 *  @var object_id -> set by caller
 *  @var object_type -> set by caller
 *  @var product_id -> set by caller
 *  @var variation_id -> set by caller
 *
 */
 var WpdAiEventTracking = (function( data ) {

	var initialized_variables 	= wpdAlphaInsightsEventTracking;
	var page_href 				= document.location.href;
	var requiredAtts 			= true;

	var payload = {
		page_href: 			page_href,
		event_type: 		'',
		event_quantity: 	1,
		event_value: 		0,
		object_id: 			0,
		object_type: 		'',
		product_id: 		0,
		variation_id: 		0,
		additional_data: 	{},
	};

	// Setup the class object
	this.init = function() {

		payload = this.processVariables();
		if ( ! payload ) {

			return false;

		} else {

			var trackEvent = this.trackEvent( payload );

		}

	}

	// Check and clean inputs
	this.processVariables = function() {

		if ( ! data.event_type  ) {
			requiredAtts = false;
			return false;
		}

		payload.event_type 		= data.event_type;

		payload.event_quantity 	= ( data.event_quantity ) ? data.event_quantity : 1;
		payload.event_value 	= ( data.event_value ) ? data.event_value : 0;
		payload.object_id 		= ( data.object_id ) ? data.object_id : initialized_variables.current_post_id;
		payload.object_type 	= ( data.object_type ) ? data.object_type : initialized_variables.current_post_type;
		payload.product_id 		= ( data.product_id ) ? data.product_id : 0;
		payload.variation_id 	= ( data.variation_id ) ? data.variation_id : 0;
		payload.additional_data = ( data.additional_data ) ? data.additional_data : {};

		if ( payload.object_type == 'product' ) {
			payload.product_id 	= payload.object_id;
		}

		return payload;

	}

	// Send request to API
	this.trackEvent = function( payload ) {

	    jQuery.ajax({

	      url: initialized_variables.api_endpoint,
	      method: 'POST',
	      contentType: 'application/json; charset=UTF-8',
	      data: JSON.stringify(payload),

/* 	      beforeSend: function(xhr){
	        xhr.setRequestHeader( 'X-WP-Nonce', rest_api_settings.nonce );
	      } */

	    });

	}

	// Initialize class
	this.init();

});
/**
 *
 *	Load in Core WC Events
 *  @see https://gist.github.com/mpdevcl/a7299a28baf62e3e560dc84c664c0f95
 *
 */
jQuery(document).ready(function($) {

	// Page Views
	var payload = {
		event_type: 'page_view',
		object_type: ( typeof wpdAlphaInsightsEventTracking.current_post_type !== 'undefined' ) ? wpdAlphaInsightsEventTracking.current_post_type : '',
		object_id: ( typeof wpdAlphaInsightsEventTracking.current_post_id !== 'undefined' ) ? wpdAlphaInsightsEventTracking.current_post_id : '',
	};
	var page_view = WpdAiEventTracking( payload );

	// Product Clicks -> Update this to be more dynamic?
	$('.products .product a').click(function() {
		var product_id = 0;
		var pid = $(this).closest('.product').find('.wpd-ai-event-tracking-product-id').data('product-id');
		if ( pid ) {
			product_id = pid;
		}
		var payload = {
			event_type: 	'product_click',
			event_quantity: 1,
			event_value: 	0,
			object_id: 		product_id,
			object_type: 	'product',
			product_id: 	product_id,
			variation_id: 	0,
		};
		var product_click = WpdAiEventTracking( payload );
	});

	// Form Submissions
	$('body').on( 'submit', 'form', function(e) {

		var $form = $(this);

		// Determine a single form identifier
		var form_id = $form.attr('id') 
					|| $form.attr('name')
					|| $form.attr('class') 
					|| 'unknown_form'; // fallback if none exist

		// Combine into class names
		if (!form_id || form_id === $form.attr('class')) {
			var classNames = $form.attr('class');
			if (classNames) {
				form_id = classNames ? '.' + classNames.trim().split(/\s+/).join('.') : '';
			}
		}

		// Serialize form and filter out password fields
		var formData = $form.serializeArray().filter(function(field) {
			return !field.name.toLowerCase().includes('password');
		});

		var data = {
			form_id: form_id ?? 'unknown_form',
			form_element_id: $form.attr('id') ?? '',
			form_element_name: $form.attr('name') ?? '',
			form_element_class: $form.attr('class') ?? '',
			form_element_action: $form.attr('action') ?? '',
			form_method: $form.attr('method') ?? ''
			// form_data: formData // Not being used
		};

		var payload = {
			event_type: 'form_submit',
			additional_data: data
		};

		WpdAiEventTracking( payload );

	});

	// Standard events that dont require interference
	const standardWooEvents = ["updated_checkout", "payment_method_selected", "checkout_error", "wc_cart_emptied", "updated_shipping_method", "applied_coupon", "removed_coupon"];
	// "init_checkout" is not included, we will use our page views to track this server side.
	standardWooEvents.forEach(function (woocommerce_event, index) {
		$('body').on( woocommerce_event, function( event, additional_data ) {
			
			var payload = {
				event_type: woocommerce_event,
			};

			if ( woocommerce_event == 'checkout_error' ) {

				//300ms delay in case the error are loaded later via ajax
				setTimeout(function() {

					let errorMessages = jQuery('.woocommerce-error, .woocommerce-notices-wrapper').text().trim();
					payload.additional_data = {error_message: errorMessages};

					// Make sure the event fires as part of the delayed payload
					var woocommerce_event_trigger = WpdAiEventTracking( payload );

				}, 300);

			} else {

				var woocommerce_event_trigger = WpdAiEventTracking( payload );

			}
		});
	});


});
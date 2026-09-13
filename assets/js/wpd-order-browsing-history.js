/**
 * Order dashboard browsing history modal.
 *
 * @package Alpha Insights
 */
(function($) {
	'use strict';

	var config = window.wpdAiBrowsingHistory || {};
	var loadedOrderId = 0;
	var cache = null;

	function i18n(key, fallback) {
		return (config.i18n && config.i18n[key]) ? config.i18n[key] : fallback;
	}

	function escapeHtml(value) {
		return $('<div>').text(value == null ? '' : String(value)).html();
	}

	function ensureModal() {
		var $modal = $('#wpd-browsing-history-modal');
		if ($modal.length) {
			return $modal;
		}

		var html =
			'<div id="wpd-browsing-history-modal" class="wpd-bh-modal" hidden>' +
				'<div class="wpd-bh-overlay" data-wpd-bh-close="1"></div>' +
				'<div class="wpd-bh-dialog" role="dialog" aria-modal="true" aria-labelledby="wpd-bh-title">' +
					'<div class="wpd-bh-header">' +
						'<div>' +
							'<h2 id="wpd-bh-title">' + escapeHtml(i18n('title', 'Browsing History')) + '</h2>' +
							'<p class="wpd-bh-subtitle"></p>' +
						'</div>' +
						'<button type="button" class="wpd-bh-close" data-wpd-bh-close="1" aria-label="' + escapeHtml(i18n('close', 'Close')) + '">&times;</button>' +
					'</div>' +
					'<div class="wpd-bh-body">' +
						'<div class="wpd-bh-loading">' + escapeHtml(i18n('loading', 'Loading customer journey…')) + '</div>' +
					'</div>' +
				'</div>' +
			'</div>';

		$('body').append(html);
		return $('#wpd-browsing-history-modal');
	}

	function openModal() {
		var $modal = ensureModal();
		$modal.removeAttr('hidden').addClass('is-open');
		$('body').addClass('wpd-bh-modal-open');
	}

	function closeModal() {
		var $modal = $('#wpd-browsing-history-modal');
		$modal.attr('hidden', 'hidden').removeClass('is-open');
		$('body').removeClass('wpd-bh-modal-open');
	}

	function setBodyHtml(html) {
		ensureModal().find('.wpd-bh-body').html(html);
	}

	function pageViewCountLabel(count) {
		return String(count) + ' ' + i18n('pageViews', 'page views');
	}

	function renderBoundaryStep(kind, time) {
		var isStart = kind === 'start';
		var label = isStart ? i18n('sessionStart', 'Session start') : i18n('sessionEnd', 'Session end');
		var html = '<li class="wpd-bh-step wpd-bh-step-boundary wpd-bh-step-session-' + (isStart ? 'start' : 'end') + '">';
		html += '<span class="wpd-bh-step-dot"></span>';
		html += '<div class="wpd-bh-step-content">';
		html += '<div class="wpd-bh-step-top">';
		html += '<span class="wpd-bh-boundary-badge">' + escapeHtml(label) + '</span>';
		if (time) {
			html += '<time>' + escapeHtml(time) + '</time>';
		}
		html += '</div></div></li>';
		return html;
	}

	function renderSessions(payload) {
		var sessions = payload.sessions || [];
		var $modal = ensureModal();
		var subtitle = sessions.length ? (sessions.length + ' ' + i18n('sessionCount', 'sessions')) : '';
		$modal.find('.wpd-bh-subtitle').text(subtitle);

		if (!sessions.length) {
			setBodyHtml('<div class="wpd-bh-empty">' + escapeHtml(i18n('empty', 'No browsing history was found for this customer.')) + '</div>');
			return;
		}

		var html = '<div class="wpd-bh-sessions">';
		sessions.forEach(function(session, index) {
			var expanded = index === 0 ? ' is-open' : '';
			var source = session.traffic_source || i18n('direct', 'Direct');
			var landing = session.landing_page_path || session.landing_page || '';
			var referral = session.referral || '';
			var views = session.page_views || [];

			html += '<article class="wpd-bh-session' + expanded + '">';
			html += '<button type="button" class="wpd-bh-session-toggle" aria-expanded="' + (index === 0 ? 'true' : 'false') + '">';
			html += '<span class="wpd-bh-session-main">';
			html += '<span class="wpd-bh-session-date">' + escapeHtml(session.started_at || '') + '</span>';
			html += '<span class="wpd-bh-pills">';
			html += '<span class="wpd-bh-pill">' + escapeHtml(source) + '</span>';
			if (session.has_order || session.is_order_session) {
				html += '<span class="wpd-bh-pill wpd-bh-pill-order">' + escapeHtml(i18n('orderPlaced', 'Order Placed')) + '</span>';
			}
			html += '</span>';
			html += '</span>';
			html += '<span class="wpd-bh-session-meta">';
			if (session.duration) {
				html += '<span>' + escapeHtml(session.duration) + '</span>';
			}
			html += '<span>' + escapeHtml(pageViewCountLabel(session.page_view_count || views.length)) + '</span>';
			html += '<span class="wpd-bh-chevron"></span>';
			html += '</span>';
			html += '</button>';

			html += '<div class="wpd-bh-session-body">';
			html += '<div class="wpd-bh-session-summary">';
			if (landing) {
				html += '<div><span>' + escapeHtml(i18n('landingPage', 'Landing page')) + '</span>';
				if (session.landing_page) {
					html += '<a href="' + escapeHtml(session.landing_page) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(landing) + '</a>';
				} else {
					html += '<em>' + escapeHtml(landing) + '</em>';
				}
				html += '</div>';
			}
			if (referral) {
				html += '<div><span>' + escapeHtml(i18n('referral', 'Referral')) + '</span><em>' + escapeHtml(referral) + '</em></div>';
			}
			if (session.device) {
				html += '<div><span></span><em>' + escapeHtml(session.device) + '</em></div>';
			}
			html += '</div>';

			html += '<ol class="wpd-bh-timeline">';
			html += renderBoundaryStep('start', session.started_at);
			if (!views.length) {
				html += '<li class="wpd-bh-step wpd-bh-step-empty"><span class="wpd-bh-step-dot"></span><div class="wpd-bh-step-content"><p class="wpd-bh-empty-session">' + escapeHtml(i18n('noPageViews', 'No page views recorded for this session.')) + '</p></div></li>';
			} else {
				views.forEach(function(view) {
					var path = view.path || view.url || '';
					html += '<li class="wpd-bh-step wpd-bh-step-' + escapeHtml(view.type || 'event') + '">';
					html += '<span class="wpd-bh-step-dot"></span>';
					html += '<div class="wpd-bh-step-content">';
					html += '<div class="wpd-bh-step-top">';
					html += '<strong>' + escapeHtml(view.type_label || view.type || '') + '</strong>';
					if (view.time) {
						html += '<time>' + escapeHtml(view.time) + '</time>';
					}
					html += '</div>';
					if (path) {
						if (view.url) {
							html += '<a href="' + escapeHtml(view.url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(path) + '</a>';
						} else {
							html += '<span class="wpd-bh-step-path">' + escapeHtml(path) + '</span>';
						}
					}
					html += '</div>';
					html += '</li>';
				});
			}
			html += renderBoundaryStep('end', session.ended_at || session.started_at);
			html += '</ol>';

			html += '</div></article>';
		});
		html += '</div>';
		setBodyHtml(html);
	}

	function loadHistory(orderId) {
		if (cache && loadedOrderId === orderId) {
			renderSessions(cache);
			return;
		}

		setBodyHtml('<div class="wpd-bh-loading">' + escapeHtml(i18n('loading', 'Loading customer journey…')) + '</div>');
		ensureModal().find('.wpd-bh-subtitle').text('');

		$.ajax({
			url: config.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'wpd_get_order_browsing_history',
				nonce: config.nonce,
				order_id: orderId
			}
		}).done(function(response) {
			if (!response || !response.success || !response.data) {
				var message = (response && response.data && response.data.message) ? response.data.message : i18n('error', 'Unable to load browsing history. Please try again.');
				setBodyHtml('<div class="wpd-bh-empty">' + escapeHtml(message) + '</div>');
				return;
			}
			cache = response.data;
			loadedOrderId = orderId;
			renderSessions(cache);
		}).fail(function() {
			setBodyHtml('<div class="wpd-bh-empty">' + escapeHtml(i18n('error', 'Unable to load browsing history. Please try again.')) + '</div>');
		});
	}

	$(document).on('click', '.wpd-browsing-history-btn', function(event) {
		event.preventDefault();
		var orderId = parseInt($(this).data('order-id'), 10) || 0;
		if (!orderId) {
			return;
		}
		openModal();
		loadHistory(orderId);
	});

	$(document).on('click', '[data-wpd-bh-close]', function(event) {
		event.preventDefault();
		closeModal();
	});

	$(document).on('click', '.wpd-bh-session-toggle', function(event) {
		event.preventDefault();
		var $session = $(this).closest('.wpd-bh-session');
		var isOpen = $session.hasClass('is-open');
		$session.toggleClass('is-open', !isOpen);
		$(this).attr('aria-expanded', isOpen ? 'false' : 'true');
	});

	$(document).on('keydown', function(event) {
		if (event.key === 'Escape' && $('#wpd-browsing-history-modal').hasClass('is-open')) {
			closeModal();
		}
	});
})(jQuery);

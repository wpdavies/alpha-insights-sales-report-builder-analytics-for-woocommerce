/**
 * Alpha Insights cache-safe analytics client.
 * Stores session identity in localStorage; mirrors to cookies on cart/checkout only.
 */
(function() {
	'use strict';

	// Persisted browser key prefix. Do not rename — existing visitor sessions use these keys.
	var STORAGE_PREFIX = 'wpd_ai_v2_';
	var KEYS = {
		sessionId: STORAGE_PREFIX + 'session_id',
		sessionActivity: STORAGE_PREFIX + 'session_activity',
		landingPage: STORAGE_PREFIX + 'landing_page',
		landingSetAt: STORAGE_PREFIX + 'landing_set_at',
		referralSource: STORAGE_PREFIX + 'referral_source',
		referralSetAt: STORAGE_PREFIX + 'referral_set_at'
	};

	// Mirrors server-side tracking param detection (WPDAI_Session_Context / WPDAI_Session_Tracking).
	var TRACKING_PARAMS = [
		'gclid', 'gbraid', 'wbraid', 'dclid', 'fbclid', 'msclkid', 'ttclid', 'li_fat_id', 'srsltid',
		'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
		'google_cid', 'meta_cid', 'gad_source', 'gad_campaignid',
		'ref', 'source', 'referrer', 'referer'
	];

	function getConfig() {
		return (typeof wpdAiClientConfig !== 'undefined') ? wpdAiClientConfig : {};
	}

	function getSessionTimeoutMs() {
		var cfg = getConfig();
		var seconds = cfg.session_timeout_seconds ? parseInt(cfg.session_timeout_seconds, 10) : (30 * 60);
		return Math.max(60, seconds) * 1000;
	}

	function getAttributionTimeoutMs() {
		var cfg = getConfig();
		if (isSessionOnlyAttribution()) {
			return getSessionTimeoutMs();
		}
		var seconds = cfg.attribution_timeout_seconds ? parseInt(cfg.attribution_timeout_seconds, 10) : (3 * 86400);
		return Math.max(86400, seconds) * 1000;
	}

	function isSessionOnlyAttribution() {
		var cfg = getConfig();
		return parseInt(cfg.attribution_session_only, 10) === 1;
	}

	function shouldOverrideAttributionOnNewUtm() {
		var cfg = getConfig();
		return parseInt(cfg.override_attribution_on_new_utm, 10) === 1;
	}

	function getCookieStorageMode() {
		var cfg = getConfig();
		return cfg.cookie_storage_mode === 'immediate' ? 'immediate' : 'checkout_only';
	}

	function readCookie(name) {
		var value = '; ' + document.cookie;
		var parts = value.split('; ' + name + '=');
		if (parts.length === 2) {
			var raw = parts.pop().split(';').shift();
			try {
				return decodeURIComponent(raw);
			} catch (e) {
				return raw;
			}
		}
		return null;
	}

	function writeCookie(name, value, maxAgeSeconds) {
		var cookieStr = name + '=' + encodeURIComponent(value || '') + '; path=/; SameSite=Lax';
		var cfg = getConfig();
		if (cfg.cookie_domain) {
			cookieStr += '; domain=' + cfg.cookie_domain;
		}
		if (maxAgeSeconds) {
			cookieStr += '; max-age=' + parseInt(maxAgeSeconds, 10);
		}
		document.cookie = cookieStr;
	}

	function lsGet(key) {
		try {
			return window.localStorage.getItem(key);
		} catch (e) {
			return null;
		}
	}

	function lsSet(key, value) {
		try {
			window.localStorage.setItem(key, value);
		} catch (e) {
			// Ignore quota / privacy mode errors.
		}
	}

	function lsRemove(key) {
		try {
			window.localStorage.removeItem(key);
		} catch (e) {
			// Ignore.
		}
	}

	function migrateLegacyCookies() {
		var sessionCookie = readCookie('wpd_ai_session_id');
		if (sessionCookie && !lsGet(KEYS.sessionId)) {
			lsSet(KEYS.sessionId, sessionCookie);
			lsSet(KEYS.sessionActivity, String(Date.now()));
		}

		var landingCookie = readCookie('wpd_ai_landing_page');
		if (landingCookie && !lsGet(KEYS.landingPage)) {
			lsSet(KEYS.landingPage, landingCookie);
			lsSet(KEYS.landingSetAt, String(Date.now()));
		}

		if (readCookie('wpd_ai_referral_source') !== null && !lsGet(KEYS.referralSetAt)) {
			lsSet(KEYS.referralSource, readCookie('wpd_ai_referral_source') || '');
			lsSet(KEYS.referralSetAt, String(Date.now()));
		}
	}

	function getReferrerValue() {
		var ref = document.referrer || '';
		if (!ref) {
			return '';
		}
		try {
			if (new URL(ref).hostname === window.location.hostname) {
				return '';
			}
		} catch (e) {
			return '';
		}
		return ref;
	}

	function hasTrackingParams(url) {
		if (!url) {
			return false;
		}

		try {
			var params = new URL(url).searchParams;
			for (var i = 0; i < TRACKING_PARAMS.length; i++) {
				if (params.get(TRACKING_PARAMS[i])) {
					return true;
				}
			}
		} catch (e) {
			return false;
		}

		return false;
	}

	function isCheckoutLikePage() {
		var cfg = getConfig();
		if (cfg.is_cart || cfg.is_checkout) {
			return true;
		}

		try {
			var path = (window.location.pathname || '').toLowerCase();
			var search = (window.location.search || '').toLowerCase();
			if (search.indexOf('wc-ajax=') !== -1) {
				return true;
			}
			if (/\/(checkout|cart)(\/|$)/.test(path)) {
				return true;
			}
			if (path.indexOf('order-received') !== -1 || path.indexOf('order-pay') !== -1) {
				return true;
			}
		} catch (e) {
			return false;
		}

		return false;
	}

	function existingSessionId() {
		return lsGet(KEYS.sessionId) || readCookie('wpd_ai_session_id') || '';
	}

	function isNewSession() {
		var sessionId = existingSessionId();

		// Never rotate the session on cart/checkout — cookies may be written
		// for the first time here, and inactivity should not start a new one.
		if (isCheckoutLikePage() && sessionId) {
			return false;
		}

		var now = Date.now();
		var timeoutMs = getSessionTimeoutMs();
		var lastActivity = parseInt(lsGet(KEYS.sessionActivity) || '0', 10);

		return !sessionId || !lastActivity || (now - lastActivity) > timeoutMs;
	}

	function resetAttribution(now) {
		if (isCheckoutLikePage() && !hasTrackingParams(document.location.href)) {
			return;
		}

		var timestamp = now || Date.now();
		lsSet(KEYS.landingPage, document.location.href);
		lsSet(KEYS.landingSetAt, String(timestamp));
		lsSet(KEYS.referralSource, getReferrerValue());
		lsSet(KEYS.referralSetAt, String(timestamp));
	}

	function ensureAttribution(isNewSessionVisit) {
		var now = Date.now();
		var attributionMs = getAttributionTimeoutMs();
		var landingSetAt = parseInt(lsGet(KEYS.landingSetAt) || '0', 10);
		var newSession = (isNewSessionVisit === true) ? true : isNewSession();
		var attributionExpired = landingSetAt && (now - landingSetAt) > attributionMs;
		var hasTracking = hasTrackingParams(document.location.href);
		var onCheckout = isCheckoutLikePage();

		if (onCheckout && lsGet(KEYS.landingPage) && !hasTracking) {
			// Keep the original landing page through checkout.
		} else if (!lsGet(KEYS.landingPage) || attributionExpired) {
			resetAttribution(now);
		} else if (isSessionOnlyAttribution() && newSession) {
			resetAttribution(now);
		} else if (shouldOverrideAttributionOnNewUtm() && newSession && hasTracking) {
			// Optional last-touch: a new session with UTM/tracking params overrides the attribution window.
			resetAttribution(now);
		} else if (hasTracking && !hasTrackingParams(lsGet(KEYS.landingPage))) {
			// First hit was untagged (Safari / prefetch / same-tab ad click).
			// Promote landing only; keep an existing external referrer.
			lsSet(KEYS.landingPage, document.location.href);
			lsSet(KEYS.landingSetAt, String(now));
			if (!lsGet(KEYS.referralSource)) {
				lsSet(KEYS.referralSource, getReferrerValue());
				lsSet(KEYS.referralSetAt, String(now));
			}
		} else if (!lsGet(KEYS.referralSetAt)) {
			lsSet(KEYS.referralSource, getReferrerValue());
			lsSet(KEYS.referralSetAt, String(now));
		}

		if (getCookieStorageMode() === 'immediate') {
			syncCookiesForCheckout();
		}
	}

	function generateSessionId() {
		return 'wpd' + Date.now() + Math.random().toString(36).substring(2, 12);
	}

	function getSessionId() {
		migrateLegacyCookies();

		var now = Date.now();
		var newSession = isNewSession();
		ensureAttribution(newSession);

		var sessionId = existingSessionId();
		if (sessionId && !lsGet(KEYS.sessionId)) {
			lsSet(KEYS.sessionId, sessionId);
		}

		if (newSession || !sessionId) {
			sessionId = generateSessionId();
			lsSet(KEYS.sessionId, sessionId);
		}

		lsSet(KEYS.sessionActivity, String(now));
		return sessionId;
	}

	function getLandingPage() {
		ensureAttribution();
		return lsGet(KEYS.landingPage) || '';
	}

	function getReferralSource() {
		ensureAttribution();
		var val = lsGet(KEYS.referralSource);
		return (val === null || val === undefined) ? '' : val;
	}

	function isEngagedSession() {
		return readCookie('wpd_ai_engaged_session') === '1';
	}

	function markEngaged() {
		var cfg = getConfig();
		var maxAge = cfg.session_timeout_seconds ? parseInt(cfg.session_timeout_seconds, 10) : (30 * 60);
		writeCookie('wpd_ai_engaged_session', '1', maxAge);

		if (getCookieStorageMode() === 'checkout_only') {
			syncCookiesForCheckout();
		}
	}

	function syncCookiesForCheckout() {
		var cfg = getConfig();
		var attributionSeconds = isSessionOnlyAttribution()
			? getSessionTimeoutMs() / 1000
			: (cfg.attribution_timeout_seconds ? parseInt(cfg.attribution_timeout_seconds, 10) : (3 * 86400));
		var sessionSeconds = cfg.session_timeout_seconds ? parseInt(cfg.session_timeout_seconds, 10) : (30 * 60);

		writeCookie('wpd_ai_session_id', getSessionId(), sessionSeconds);
		writeCookie('wpd_ai_landing_page', getLandingPage(), attributionSeconds);
		writeCookie('wpd_ai_referral_source', getReferralSource(), attributionSeconds);
	}

	function getTrackingContext() {
		return {
			session_id: getSessionId(),
			landing_page: getLandingPage(),
			referral_source: getReferralSource(),
			page_href: document.location.href
		};
	}

	function init() {
		migrateLegacyCookies();
		ensureAttribution(isNewSession());
		bindServerSideCookieSync();

		var cfg = getConfig();
		if (cfg.is_cart || cfg.is_checkout) {
			syncCookiesForCheckout();
		}
	}

	function bindServerSideCookieSync() {
		if (typeof jQuery !== 'undefined') {
			jQuery(document.body).on(
				'adding_to_cart added_to_cart updated_checkout update_checkout init_checkout cfw_updated_checkout checkout_error',
				syncCookiesForCheckout
			);
		}

		document.addEventListener('submit', function(event) {
			var target = event.target;
			if (!target || !target.classList) {
				return;
			}

			if (target.classList.contains('cart') || target.classList.contains('variations_form') || target.classList.contains('checkout') || 'checkout' === target.getAttribute('name')) {
				syncCookiesForCheckout();
			}
		}, true);

		bindWcAjaxCookieSync();
	}

	function isWcAjaxUrl(url) {
		return !!url && String(url).indexOf('wc-ajax') !== -1;
	}

	function bindWcAjaxCookieSync() {
		if (typeof XMLHttpRequest !== 'undefined' && XMLHttpRequest.prototype && XMLHttpRequest.prototype.open) {
			var originalOpen = XMLHttpRequest.prototype.open;
			XMLHttpRequest.prototype.open = function(method, url) {
				if (isWcAjaxUrl(url)) {
					syncCookiesForCheckout();
				}
				return originalOpen.apply(this, arguments);
			};
		}

		if (typeof window.fetch === 'function') {
			var originalFetch = window.fetch;
			window.fetch = function(input, init) {
				var url = (typeof input === 'string') ? input : (input && input.url);
				if (isWcAjaxUrl(url)) {
					syncCookiesForCheckout();
				}
				return originalFetch.apply(this, arguments);
			};
		}
	}

	window.WpdAiClient = {
		init: init,
		getSessionId: getSessionId,
		getLandingPage: getLandingPage,
		getReferralSource: getReferralSource,
		isEngagedSession: isEngagedSession,
		markEngaged: markEngaged,
		syncCookiesForCheckout: syncCookiesForCheckout,
		getTrackingContext: getTrackingContext
	};

	init();
})();

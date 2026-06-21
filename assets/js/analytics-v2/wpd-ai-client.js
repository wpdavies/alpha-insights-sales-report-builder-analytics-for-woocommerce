/**
 * Alpha Insights cache-safe analytics client (v2 beta).
 * Stores session identity in localStorage; mirrors to cookies on cart/checkout only.
 */
(function() {
	'use strict';

	var STORAGE_PREFIX = 'wpd_ai_v2_';
	var KEYS = {
		sessionId: STORAGE_PREFIX + 'session_id',
		sessionActivity: STORAGE_PREFIX + 'session_activity',
		landingPage: STORAGE_PREFIX + 'landing_page',
		landingSetAt: STORAGE_PREFIX + 'landing_set_at',
		referralSource: STORAGE_PREFIX + 'referral_source',
		referralSetAt: STORAGE_PREFIX + 'referral_set_at'
	};

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
		var seconds = cfg.attribution_timeout_seconds ? parseInt(cfg.attribution_timeout_seconds, 10) : (3 * 86400);
		return Math.max(86400, seconds) * 1000;
	}

	function getCookieStorageMode() {
		var cfg = getConfig();
		return cfg.cookie_storage_mode === 'immediate' ? 'immediate' : 'checkout_only';
	}

	function readCookie(name) {
		var value = '; ' + document.cookie;
		var parts = value.split('; ' + name + '=');
		if (parts.length === 2) {
			return parts.pop().split(';').shift();
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

	function ensureAttribution() {
		var now = Date.now();
		var attributionMs = getAttributionTimeoutMs();
		var landingSetAt = parseInt(lsGet(KEYS.landingSetAt) || '0', 10);

		if (!lsGet(KEYS.landingPage) || (landingSetAt && (now - landingSetAt) > attributionMs)) {
			lsSet(KEYS.landingPage, document.location.href);
			lsSet(KEYS.landingSetAt, String(now));
			lsSet(KEYS.referralSource, getReferrerValue());
			lsSet(KEYS.referralSetAt, String(now));
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
		ensureAttribution();

		var now = Date.now();
		var timeoutMs = getSessionTimeoutMs();
		var sessionId = lsGet(KEYS.sessionId);
		var lastActivity = parseInt(lsGet(KEYS.sessionActivity) || '0', 10);

		if (!sessionId || !lastActivity || (now - lastActivity) > timeoutMs) {
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
		var attributionSeconds = cfg.attribution_timeout_seconds ? parseInt(cfg.attribution_timeout_seconds, 10) : (3 * 86400);
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
		ensureAttribution();
		bindServerSideCookieSync();

		var cfg = getConfig();
		if (cfg.is_cart || cfg.is_checkout) {
			syncCookiesForCheckout();
		}
	}

	function bindServerSideCookieSync() {
		if (typeof jQuery !== 'undefined') {
			jQuery(document.body).on('adding_to_cart', syncCookiesForCheckout);
			jQuery(document.body).on('added_to_cart', syncCookiesForCheckout);
		}

		document.addEventListener('submit', function(event) {
			var target = event.target;
			if (!target || !target.classList) {
				return;
			}

			if (target.classList.contains('cart') || target.classList.contains('variations_form')) {
				syncCookiesForCheckout();
			}
		}, true);
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

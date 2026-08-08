jQuery(document).ready(function($) {
	function setCookie(name, value, maxAgeSeconds) {
	    var cookieStr = name + "=" + (value || "") + "; path=/; SameSite=Lax";
	    if (typeof wpd_ai_session_vars !== 'undefined' && wpd_ai_session_vars.cookie_domain) {
	        cookieStr += "; domain=" + wpd_ai_session_vars.cookie_domain;
	    }
	    if (maxAgeSeconds) {
	        cookieStr += "; max-age=" + parseInt(maxAgeSeconds, 10);
	    }
	    
	    document.cookie = cookieStr;
	}
	function getCookie(name) {
	    var nameEQ = name + "=";
	    var ca = document.cookie.split(';');
	    for(var i=0;i < ca.length;i++) {
	        var c = ca[i];
	        while (c.charAt(0)==' ') c = c.substring(1,c.length);
	        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	    }
	    return null;
	}
	function eraseCookie(name) {   
	    document.cookie = name+'=; Max-Age=-99999999;';  
	}
	// Attribution window: use backend value when localized (keeps frontend/backend in sync), else 3 days
	var attributionSeconds = (typeof wpd_ai_session_vars !== 'undefined' && wpd_ai_session_vars.attribution_timeout_seconds)
		? parseInt(wpd_ai_session_vars.attribution_timeout_seconds, 10) : (3 * 86400);
	var sessionOnlyAttribution = (typeof wpd_ai_session_vars !== 'undefined' && parseInt(wpd_ai_session_vars.attribution_session_only, 10) === 1);
	var sessionSeconds = (typeof wpd_ai_session_vars !== 'undefined' && wpd_ai_session_vars.session_timeout_seconds)
		? parseInt(wpd_ai_session_vars.session_timeout_seconds, 10) : (30 * 60);
	var attributionCookieSeconds = sessionOnlyAttribution ? sessionSeconds : attributionSeconds;
	var wpdailp = getCookie('wpd_ai_landing_page');
	var wpdairs = getCookie('wpd_ai_referral_source');

	var TRACKING_PARAMS = [
		'gclid', 'gbraid', 'wbraid', 'dclid', 'fbclid', 'msclkid', 'ttclid', 'li_fat_id', 'srsltid',
		'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
		'google_cid', 'meta_cid',
		'ref', 'source', 'referrer', 'referer'
	];

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

	function isNewSession() {
		var lastActivity = 0;
		try {
			lastActivity = parseInt(window.localStorage.getItem('wpd_ai_session_activity') || '0', 10);
		} catch (e) {
			lastActivity = 0;
		}

		return !lastActivity || (Date.now() - lastActivity) > (sessionSeconds * 1000);
	}

	function touchSessionActivity() {
		try {
			window.localStorage.setItem('wpd_ai_session_activity', String(Date.now()));
		} catch (e) {
			// Ignore quota / privacy mode errors.
		}
	}

	// Treat missing or empty referral as "not set" (empty can come from cached responses with wrong expiry)
	var referralNotSet = (wpdairs == null || wpdairs === 'undefined' || wpdairs === '');

	function getReferrerValue() {
		var ref = document.referrer || '';
		if (ref) {
			try {
				var siteHost = window.location.hostname;
				var refHost = new URL(ref).hostname;
				if (refHost === siteHost) {
					ref = '';
				}
			} catch (e) {
				ref = '';
			}
		}
		return ref;
	}

	// First load, new session (session-only mode), or a new session with UTM/tracking params.
	var newSession = isNewSession();
	var newSessionWithTracking = newSession && hasTrackingParams(document.location.href);
	if (wpdailp == null || wpdailp === 'undefined' || (sessionOnlyAttribution && newSession) || newSessionWithTracking) {
		var url = document.location.href;
		setCookie('wpd_ai_landing_page', url, attributionCookieSeconds);
		setCookie('wpd_ai_referral_source', getReferrerValue(), attributionCookieSeconds);
	} else if (referralNotSet) {
		// Landing already set but referral missing or stale (e.g. empty cookie from cached header) — set with same timeout
		setCookie('wpd_ai_referral_source', getReferrerValue(), attributionCookieSeconds);
	}

	touchSessionActivity();
});
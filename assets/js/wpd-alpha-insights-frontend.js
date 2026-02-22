jQuery(document).ready(function($) {
	function setCookie(name, value, days) {
	    var cookieStr = name + "=" + (value || "") + "; path=/";
	    if (typeof wpd_ai_session_vars !== 'undefined' && wpd_ai_session_vars.cookie_domain) {
	        cookieStr += "; domain=" + wpd_ai_session_vars.cookie_domain;
	    }
	    if (days) {
	        const date = new Date();
	        date.setTime(date.getTime() + (days*24*60*60*1000));
	        cookieStr += "; expires=" + date.toUTCString();
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
	var attributionDays = Math.max(1, Math.ceil(attributionSeconds / 86400));
	var wpdailp = getCookie('wpd_ai_landing_page');
	var wpdairs = getCookie('wpd_ai_referral_source');

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

	// First load: set both landing page and referral together so they share the same expiry (avoids cache/PHP timing issues)
	if (wpdailp == null || wpdailp === 'undefined') {
		var url = document.location.href;
		setCookie('wpd_ai_landing_page', url, attributionDays);
		setCookie('wpd_ai_referral_source', getReferrerValue(), attributionDays);
	} else if (referralNotSet) {
		// Landing already set but referral missing or stale (e.g. empty cookie from cached header) — set with same timeout
		setCookie('wpd_ai_referral_source', getReferrerValue(), attributionDays);
	}
});
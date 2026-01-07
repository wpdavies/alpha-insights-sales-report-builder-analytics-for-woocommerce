jQuery(document).ready(function($) {
	function setCookie(name, value, days) {
	    let cookieStr = name + "=" + (value || "") + "; path=/";
	    
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
	var wpdailp = getCookie('wpd_ai_landing_page');
	var wpdairs = getCookie('wpd_ai_referral_source');
	if ( wpdailp == null || wpdailp === '' ) {
		url = document.location.href;
		setCookie('wpd_ai_landing_page', url);
	}
	// Check if cookie doesn't exist (null) or is empty string - update referral URL in both cases
	if (wpdairs == null || wpdairs === '') {
		const siteHost = window.location.hostname; // make sure this is localized properly
		let ref = document.referrer;
		if (ref) {
			try {
				const refHost = new URL(ref).hostname;
				if (refHost === siteHost) {
					ref = '';
				}
			} catch (e) {
				ref = ''; // fallback if ref isn't a valid URL
			}
		}
		setCookie('wpd_ai_referral_source', ref);
	}
});
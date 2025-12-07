<?php
/**
 *
 * License functions
 *
 * @package Alpha Insights
 * @version 1.0.0
 * @since 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * 
 * 	Returns the url for an invalid license redirect / link
 * 
 **/
function wpd_invalid_license_url() {

	return wpd_admin_page_url( 'settings-license' ) . '&wpd-notice=invalid-license';

}

/**
 * 
 * 	Checks whether the license is active or not
 * 
 **/
function wpd_is_license_active() {

    // Pass checks in free version
    if ( ! WPD_AI_PRO ) {
        return true;
    }

    if ( class_exists('WPD_Authenticator') ) {
		$authenticator 	= new WPD_Authenticator();
		$license_status = $authenticator->is_license_active();

		return $license_status;

    } else {

        return false;

    }

}
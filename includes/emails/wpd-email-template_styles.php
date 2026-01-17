<?php
/**
 *
 * Styles to load into emails
 *
 * Loads CSS from external file and outputs in <style> tag for email client compatibility
 *
 * @package Alpha Insights
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * Get email template CSS
 * 
 * Reads the CSS file and returns the content for output in email templates
 * 
 * @return string CSS content for email templates
 */
function wpdai_get_email_template_styles() {
	// Get CSS file path
	$css_file = defined( 'WPD_AI_PATH' ) ? WPD_AI_PATH . 'assets/css/wpd-email-template-styles.css' : plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'assets/css/wpd-email-template-styles.css';
	
	// Check if file exists
	if ( ! file_exists( $css_file ) ) {
		// Fallback: try alternative path calculation
		$css_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/assets/css/wpd-email-template-styles.css';
	}
	
	// Read CSS file using WordPress-safe method
	if ( file_exists( $css_file ) && is_readable( $css_file ) ) {
		$css_content = file_get_contents( $css_file );
		
		// Remove any potential PHP tags that might have been injected
		$css_content = preg_replace( '/<\?php.*?\?>/is', '', $css_content );
		
		return $css_content;
	}
	
	// Fallback: return empty string if file cannot be read
	return '';
}

// Get CSS content
$email_styles = wpdai_get_email_template_styles();

/**
 * Filter email template styles
 * 
 * Allows customization of email template styles before output
 * 
 * @param string $email_styles CSS content for email templates
 */
$email_styles = apply_filters( 'wpdai_email_template_styles', $email_styles );

// Output styles in <style> tag (required for email client compatibility)
if ( ! empty( $email_styles ) ) {
	?>
	<style type="text/css">
		<?php echo $email_styles; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped - CSS content is sanitized and filtered ?>
	</style>
	<?php
}

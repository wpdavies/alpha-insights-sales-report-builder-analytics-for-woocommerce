<?php
/**
 * Abstract base class for Alpha Insights integrations
 *
 * Each integration should extend this class and implement:
 * - get_slug() - Unique identifier
 * - get_label() - Display name
 * - get_description() - Short description
 * - is_pro() - Whether this is a Pro-only integration
 * - is_enabled() - Whether the integration is configured/active
 * - render_settings() - Output settings HTML
 * - save_settings() - Handle form save (hooked via wpd_ai_save_settings)
 *
 * @package Alpha Insights
 * @version 5.2.0
 * @since 5.2.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 */

defined( 'ABSPATH' ) || exit;

abstract class WPDAI_Integration_Base {

	/**
	 * Get the unique slug for this integration
	 *
	 * @return string
	 */
	abstract public function get_slug();

	/**
	 * Get the display label for this integration
	 *
	 * @return string
	 */
	abstract public function get_label();

	/**
	 * Get the short description for this integration
	 *
	 * @return string
	 */
	abstract public function get_description();

	/**
	 * Whether this integration requires the Pro version
	 *
	 * @return bool
	 */
	abstract public function is_pro();

	/**
	 * Whether this integration is enabled/configured
	 *
	 * @return bool
	 */
	abstract public function is_enabled();

	/**
	 * Render the settings HTML for this integration
	 * Output is rendered inside the main settings form
	 *
	 * @return void
	 */
	abstract public function render_settings();

	/**
	 * Save settings for this integration
	 * Called via wpd_ai_save_settings filter
	 *
	 * @param array $saved Array of saved setting names and their status
	 * @return array Modified $saved array
	 */
	abstract public function save_settings( $saved );

	/**
	 * Constructor - calls setup_hooks
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for this integration (e.g. wpd_ai_save_settings)
	 * Override in child class. Called during init.
	 *
	 * @return void
	 */
	public function setup_hooks() {
		add_filter( 'wpd_ai_save_settings', array( $this, 'save_settings' ), 10 );
	}

	/**
	 * Get the documentation URL for this integration (optional).
	 * Override to return a non-empty string to show a Documentation button on the integration settings page.
	 *
	 * @return string Documentation URL, or empty string if none.
	 */
	public function get_docs_url() {
		return '';
	}

	/**
	 * Get the URL to view this integration's settings
	 *
	 * @return string
	 */
	public function get_settings_url() {
		return add_query_arg(
			array(
				'page'         => WPDAI_Admin_Menu::$settings_slug,
				'subpage'      => 'integrations',
				'integrations' => $this->get_slug(),
			),
			admin_url( 'admin.php' )
		);
	}
}

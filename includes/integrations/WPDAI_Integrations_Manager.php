<?php
/**
 * Integrations Manager - Core class for modular integration modules
 *
 * Outputs available integrations as a grid of cards. Each integration can be
 * clicked to show its settings. Uses ?integrations=$slug query param for routing.
 *
 * @package Alpha Insights
 * @version 5.2.0
 * @since 5.2.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 */
defined( 'ABSPATH' ) || exit;

class WPDAI_Integrations_Manager {

	/**
	 * Instance of this class
	 *
	 * @var WPDAI_Integrations_Manager
	 */
	private static $instance = null;

	/**
	 * Integration metadata (slug => array with label, description, is_pro, logo_url, category, url)
	 * Used for display - all built-in integrations registered here so free users see Pro options
	 *
	 * @var array<string, array{label: string, description: string, is_pro: bool, logo_url: string}>
	 */
	private $integration_metadata = array();

	/**
	 * Registered integration instances (slug => WPDAI_Integration_Base)
	 * Only present when the integration class is loaded (Pro integrations only in Pro version)
	 *
	 * @var array<string, WPDAI_Integration_Base>
	 */
	private $integrations = array();

	/**
	 * Get the singleton instance of this class
	 *
	 * @return WPDAI_Integrations_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Prevent cloning of the instance
	 *
	 * @return void
	 */
	private function __clone() {
		// Prevent cloning
	}

	/**
	 * Prevent unserialization of the instance
	 *
	 * @return void
	 */
	public function __wakeup() {
		// Prevent unserialization
	}

	/**
	 * Class constructor
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_default_integration_metadata' ), 4 );
		add_action( 'init', array( $this, 'register_default_integrations' ), 5 );
		add_action( 'wpd_ai_register_integration_metadata', array( $this, 'register_internal_integration_metadata' ), 10, 1 );
		add_action( 'wpd_ai_register_integrations', array( $this, 'register_core_integrations' ), 10, 1 );
	}

	/**
	 * Register integration metadata (display only - no class required)
	 * Use this for all built-in integrations so free users can see Pro options
	 *
	 * @param string $slug Unique slug for the integration
	 * @param array  $args Metadata: label (string), description (string), is_pro (bool), logo_url (string, optional), category (string, optional), url (string, optional - override link destination)
	 * @return bool True on success, false if invalid
	 */
	public function register_integration_metadata( $slug, $args ) {
		$slug = sanitize_key( $slug );
		if ( empty( $slug ) ) {
			return false;
		}
		$defaults = array(
			'label'       => '',
			'description' => '',
			'is_pro'      => false,
			'logo_url'    => '',
			'category'    => '',
			'url'         => '',
			'hidden'      => false,
		);
		$args = wp_parse_args( $args, $defaults );
		$this->integration_metadata[ $slug ] = array(
			'label'       => $args['label'],
			'description' => $args['description'],
			'is_pro'      => (bool) $args['is_pro'],
			'logo_url'    => ! empty( $args['logo_url'] ) ? esc_url_raw( $args['logo_url'] ) : '',
			'category'    => sanitize_text_field( $args['category'] ),
			'url'         => ! empty( $args['url'] ) ? esc_url_raw( $args['url'] ) : '',
			'hidden'      => ! empty( $args['hidden'] ),
		);
		return true;
	}

	/**
	 * Fires the metadata registration hook
	 *
	 * @return void
	 */
	public function register_default_integration_metadata() {
		do_action( 'wpd_ai_register_integration_metadata', $this );
	}

	/**
	 * Register metadata for all internal integrations (display only, no class required)
	 * Free users see all integrations including Pro ones - Pro integrations show as Pro badge
	 *
	 * @param WPDAI_Integrations_Manager $manager The integrations manager instance
	 */
	public function register_internal_integration_metadata( $manager ) {
		$manager->register_integration_metadata(
			'facebook-ads',
			array(
				'label'       => __( 'Facebook Ads', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'description' => __( 'Connect Facebook Ads for campaign conversion reporting and ad spend tracking', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'is_pro'      => true,
				'logo_url'    => WPD_AI_URL_PATH . 'assets/img/integrations/meta-logo.png',
				'category'    => __( 'Marketing', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'url'         => wpdai_admin_page_url( 'settings-facebook' ),
			)
		);
		$manager->register_integration_metadata(
			'google-ads',
			array(
				'label'       => __( 'Google Ads', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'description' => __( 'Connect Google Ads for campaign conversion reporting and ad spend tracking', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'is_pro'      => true,
				'logo_url'    => WPD_AI_URL_PATH . 'assets/img/integrations/google-ads-logo.png',
				'category'    => __( 'Marketing', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'url'         => wpdai_admin_page_url( 'settings-google-ads' ),
			)
		);
		$manager->register_integration_metadata(
			'webhooks',
			array(
				'label'       => __( 'Webhooks', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'description' => __( 'Send data to your webhook endpoint for external data processing', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'is_pro'      => false,
				'logo_url'    => WPD_AI_URL_PATH . 'assets/img/integrations/webhooks.png',
				'category'    => __( 'Data Management', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			)
		);
		$manager->register_integration_metadata(
			'shipstation',
			array(
				'label'       => __( 'ShipStation', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'description' => __( 'Sync shipping costs with your orders via ShipStation API', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'is_pro'      => true,
				'logo_url'    => WPD_AI_URL_PATH . 'assets/img/integrations/shipstation-logo.png',
				'category'    => __( 'Shipping', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			)
		);
		$manager->register_integration_metadata(
			'starshipit',
			array(
				'label'       => __( 'StarShipIt', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'description' => __( 'Sync shipping costs with your orders via StarShipIt API', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'is_pro'      => true,
				'logo_url'    => WPD_AI_URL_PATH . 'assets/img/integrations/starshipit.png',
				'category'    => __( 'Shipping', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			)
		);
		// Virtual slug for free version: all Pro integration links go here for a single upsell page.
		$manager->register_integration_metadata(
			'pro-integration',
			array(
				'label'       => __( 'Pro Integrations', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'description' => '',
				'is_pro'      => true,
				'logo_url'    => '',
				'category'    => '',
				'url'         => '',
				'hidden'      => true,
			)
		);
	}

	/**
	 * Register an integration instance (class must be loaded)
	 *
	 * @param string                  $slug     Unique slug for the integration
	 * @param WPDAI_Integration_Base  $instance Integration instance (must extend WPDAI_Integration_Base)
	 * @return bool True on success, false if invalid
	 */
	public function register_integration( $slug, $instance ) {
		$slug = sanitize_key( $slug );
		if ( empty( $slug ) ) {
			return false;
		}
		if ( ! $instance instanceof WPDAI_Integration_Base ) {
			return false;
		}
		$this->integrations[ $slug ] = $instance;
		return true;
	}

	/**
	 * Fires the registration hook so integrations can self-register
	 *
	 * @return void
	 */
	public function register_default_integrations() {
		do_action( 'wpd_ai_register_integrations', $this );
	}

	/**
	 * Load and register built-in integration instances via auto-discovery
	 *
	 * Loads from register/ (free) and register/pro/ (Pro). Pro files do not exist
	 * in the free version, so register/pro/ is simply skipped when absent.
	 * Third parties can hook into wpd_ai_register_integrations to add their own.
	 *
	 * @param WPDAI_Integrations_Manager $manager The integrations manager instance
	 */
	public function register_core_integrations( $manager ) {
		$base_dir = defined( 'WPD_AI_PATH' ) ? WPD_AI_PATH . 'includes/integrations/' : '';
		if ( ! $base_dir ) {
			return;
		}
		if ( is_dir( $base_dir . 'register' ) ) {
			$manager->load_and_register_from_directory( $base_dir . 'register', array( 'pro' ) );
		}
		if ( is_dir( $base_dir . 'register/pro' ) ) {
			$manager->load_and_register_from_directory( $base_dir . 'register/pro', array() );
		}
	}

	/**
	 * Load PHP files from a directory and auto-register classes extending WPDAI_Integration_Base
	 *
	 * Scans the directory recursively for .php files, requires them, detects classes
	 * that extend WPDAI_Integration_Base, instantiates and registers them by slug.
	 *
	 * @param string   $dir              Absolute path to the directory (e.g. integrations/register/)
	 * @param string[] $exclude_subdirs  Subdirectory names to skip (e.g. array('pro'))
	 * @return int Number of integrations registered
	 */
	public function load_and_register_from_directory( $dir, $exclude_subdirs = array() ) {
		$dir = realpath( $dir );
		if ( ! $dir || ! is_dir( $dir ) ) {
			return 0;
		}
		$count   = 0;
		$exclude = array_flip( array_map( 'sanitize_file_name', $exclude_subdirs ) );
		$files   = $this->get_php_files_in_directory( $dir, $exclude );
		$before  = get_declared_classes();

		foreach ( $files as $file ) {
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}

		$after = get_declared_classes();
		$new   = array_diff( $after, $before );

		foreach ( $new as $class ) {
			if ( ! is_subclass_of( $class, 'WPDAI_Integration_Base' ) ) {
				continue;
			}
			try {
				$instance = new $class();
				$slug     = $instance->get_slug();
				if ( $slug && $this->register_integration( $slug, $instance ) ) {
					++$count;
				}
			} catch ( Exception $e ) {
				// Skip integrations that fail to instantiate.
				continue;
			}
		}

		return $count;
	}

	/**
	 * Recursively collect .php file paths, excluding specified subdirectories
	 *
	 * @param string   $dir     Absolute path to directory
	 * @param string[] $exclude Subdir names to skip (keys of assoc array)
	 * @return string[] List of absolute file paths
	 */
	private function get_php_files_in_directory( $dir, $exclude = array() ) {
		$files = array();
		if ( ! is_dir( $dir ) ) {
			return $files;
		}
		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS ),
			RecursiveIteratorIterator::SELF_FIRST
		);
		$dir_len = strlen( $dir ) + 1;
		foreach ( $iter as $file ) {
			if ( ! $file->isFile() || strtolower( $file->getExtension() ) !== 'php' ) {
				continue;
			}
			$path = $file->getPathname();
			$rel  = substr( $path, $dir_len );
			$parts = preg_split( '#[/\\\\]#', $rel, 2 );
			if ( isset( $parts[0] ) && isset( $exclude[ $parts[0] ] ) ) {
				continue;
			}
			$files[] = $path;
		}
		return $files;
	}

	/**
	 * Get all registered integration instances
	 *
	 * @return array<string, WPDAI_Integration_Base>
	 */
	public function get_registered_integrations() {
		return apply_filters( 'wpd_ai_registered_integrations', $this->integrations );
	}

	/**
	 * Get categories with counts from all integrations
	 *
	 * @return array<string, int> Category name => count
	 */
	public function get_categories_with_counts() {
		$all     = $this->get_all_integrations_for_display();
		$counts  = array();
		$counts[''] = 0; // All
		foreach ( $all as $data ) {
			if ( ! empty( $data['metadata']['hidden'] ) ) {
				continue;
			}
			$cat = ! empty( $data['metadata']['category'] ) ? $data['metadata']['category'] : '';
			$counts['']++;
			if ( $cat ) {
				$counts[ $cat ] = isset( $counts[ $cat ] ) ? $counts[ $cat ] + 1 : 1;
			}
		}
		return $counts;
	}

	/**
	 * Get all integrations for display (metadata + optional instance)
	 * Merges metadata (all built-in) with instances (only when class loaded)
	 *
	 * @return array<string, array{metadata: array, instance: WPDAI_Integration_Base|null}>
	 */
	public function get_all_integrations_for_display() {
		$metadata  = apply_filters( 'wpd_ai_integration_metadata', $this->integration_metadata );
		$instances = $this->get_registered_integrations();
		$merged    = array();
		foreach ( $metadata as $slug => $meta ) {
			$merged[ $slug ] = array(
				'metadata' => $meta,
				'instance' => isset( $instances[ $slug ] ) ? $instances[ $slug ] : null,
			);
		}
		return apply_filters( 'wpd_ai_all_integrations_for_display', $merged );
	}

	/**
	 * Get a single integration by slug
	 *
	 * @param string $slug Integration slug
	 * @return WPDAI_Integration_Base|null
	 */
	public function get_integration( $slug ) {
		$integrations = $this->get_registered_integrations();
		return isset( $integrations[ $slug ] ) ? $integrations[ $slug ] : null;
	}

	/**
	 * Get the currently selected integration slug from the request
	 *
	 * @return string|null
	 */
	public function get_current_integration_slug() {
		if ( isset( $_GET['integrations'] ) ) {
			return sanitize_key( wp_unslash( $_GET['integrations'] ) );
		}
		return null;
	}

	/**
	 * Get the base URL for the integrations page
	 *
	 * @return string
	 */
	public function get_integrations_base_url() {
		return wpdai_admin_page_url( 'settings-integrations' );
	}

	/**
	 * Output the full integrations settings page
	 * Renders the grid of integration cards and, when selected, the integration's settings
	 *
	 * @return void
	 */
	public function output_integrations_page() {
		$all_integrations = $this->get_all_integrations_for_display();
		$current_slug     = $this->get_current_integration_slug();
		$current_data     = $current_slug && isset( $all_integrations[ $current_slug ] ) ? $all_integrations[ $current_slug ] : null;
		$current_instance = $current_data && $current_data['instance'] ? $current_data['instance'] : null;
		$is_viewing_integration = $current_slug && $current_data;

		?>
		<div class="wpd-integrations-manager">
			<div class="wpd-wrapper">
				<div class="wpd-section-heading wpd-inline">
					<?php esc_html_e( 'Integrations', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
					<?php if ( $is_viewing_integration ) : ?>
					<?php
					$is_pro_upsell_view = ( $current_slug === 'pro-integration' );
					$docs_url           = ( ! $is_pro_upsell_view && $current_instance && method_exists( $current_instance, 'get_docs_url' ) ) ? $current_instance->get_docs_url() : '';
					$docs_url           = is_string( $docs_url ) ? trim( $docs_url ) : '';
					?>
					<span class="wpd-integrations-header-actions pull-right">
						<a href="<?php echo esc_url( $this->get_integrations_base_url() ); ?>" class="button button-secondary"><?php esc_html_e( 'Return To Integrations', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a>
						<?php if ( $docs_url !== '' ) : ?>
						<a href="<?php echo esc_url( $docs_url ); ?>" class="button button-secondary pull-right" target="_blank" rel="noopener noreferrer" style="margin-right: 5px;"><?php esc_html_e( 'Documentation', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a>
						<?php endif; ?>
						<?php if ( ! $is_pro_upsell_view && $current_instance ) : ?>
						<?php submit_button( __( 'Save Changes', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'primary pull-right', 'submit', false ); ?>
						<?php endif; ?>
					</span>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! $is_viewing_integration ) : ?>
			<?php
			$categories = $this->get_categories_with_counts();
			?>
			<div class="wpd-integrations-browser">
				<div class="wpd-integrations-sidebar">
					<div class="wpd-integrations-search">
						<input type="search" id="wpd-integrations-search" class="wpd-integrations-search-input" placeholder="<?php esc_attr_e( 'Search...', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>" autocomplete="off">
					</div>
					<nav class="wpd-integrations-tabs" role="tablist">
						<button type="button" class="wpd-integrations-tab wpd-integrations-tab-active" data-category="" role="tab" aria-selected="true">
							<?php esc_html_e( 'All', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
							<span class="wpd-integrations-tab-count"><?php echo esc_html( (string) ( $categories[''] ?? 0 ) ); ?></span>
						</button>
						<?php
						foreach ( $categories as $cat_name => $count ) {
							if ( $cat_name === '' ) {
								continue;
							}
							?>
							<button type="button" class="wpd-integrations-tab" data-category="<?php echo esc_attr( $cat_name ); ?>" role="tab" aria-selected="false">
								<?php echo esc_html( $cat_name ); ?>
								<span class="wpd-integrations-tab-count"><?php echo esc_html( (string) $count ); ?></span>
							</button>
							<?php
						}
						?>
					</nav>
				</div>
				<div class="wpd-integrations-content">
					<div class="wpd-wrapper wpd-integrations-grid-wrapper">
						<div class="wpd-integrations-grid" id="wpd-integrations-grid">
							<?php
							foreach ( $all_integrations as $slug => $data ) {
								if ( ! empty( $data['metadata']['hidden'] ) ) {
									continue;
								}
								$this->output_integration_card( $slug, $data['metadata'], $data['instance'], $current_slug === $slug );
							}
							?>
						</div>
						<div class="wpd-integrations-no-results" id="wpd-integrations-no-results" style="display: none;">
							<p><?php esc_html_e( 'No integrations match your search.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></p>
						</div>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( $current_slug === 'pro-integration' ) : ?>
				<?php
				wpdai_render_pro_upsell_modal( array(
					'title'       => __( 'This Is A Pro Feature', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'description' => __( 'Connect your store to the tools you already use. Alpha Insights Pro brings your ad spend, campaign performance, and shipping costs straight into your profit reports—so you see true profitability without switching tabs.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'cta_url'     => wpdai_wpdavies_url( '/plugins/alpha-insights/pricing/', 'Alpha Insights Integrations Upsell', 'integrations' ),
				) );
				?>
			<?php elseif ( $current_instance ) : ?>
				<div class="wpd-wrapper wpd-integration-settings-wrapper">
					<?php $current_instance->render_settings(); ?>
				</div>
			<?php elseif ( $current_data && $current_data['metadata']['is_pro'] ) : ?>
				<div class="wpd-wrapper wpd-integration-settings-wrapper wpd-integration-pro-upsell">
					<p class="wpd-meta"><?php esc_html_e( 'This integration is available in Alpha Insights Pro. Upgrade to unlock.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></p>
				</div>
            <?php endif; ?>

			<?php if ( $is_viewing_integration && $current_instance ) : ?>
			<div class="wpd-inline">
				<?php submit_button( __( 'Save Changes', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'primary pull-right', 'submit', false ); ?>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Output a single integration card (square)
	 *
	 * @param string                 $slug     Integration slug
	 * @param array                  $metadata Metadata (label, description, is_pro)
	 * @param WPDAI_Integration_Base|null $instance Optional instance (null for Pro integrations on free version)
	 * @param bool                   $is_active Whether this is the currently selected integration
	 * @return void
	 */
	private function output_integration_card( $slug, $metadata, $instance, $is_active = false ) {
		$is_pro         = ! empty( $metadata['is_pro'] );
		$is_enabled     = $instance ? $instance->is_enabled() : false;
		$is_pro_blocked = $is_pro && ( ! defined( 'WPD_AI_PRO' ) || ! WPD_AI_PRO );
		$label          = ! empty( $metadata['label'] ) ? $metadata['label'] : $slug;
		$description    = ! empty( $metadata['description'] ) ? $metadata['description'] : '';
		$logo_url       = ! empty( $metadata['logo_url'] ) ? $metadata['logo_url'] : '';
		$category       = ! empty( $metadata['category'] ) ? $metadata['category'] : '';
		$search_text    = strtolower( $label . ' ' . $description . ' ' . $category );
		if ( $is_pro_blocked ) {
			$url = '#';
		} else {
			$url = ! empty( $metadata['url'] ) ? $metadata['url'] : add_query_arg( 'integrations', $slug, $this->get_integrations_base_url() );
		}
		$card_classes   = array( 'wpd-integration-card' );
		if ( $is_active ) {
			$card_classes[] = 'wpd-integration-card-active';
		}
		if ( $is_pro ) {
			$card_classes[] = 'wpd-integration-card-is-pro';
		}
		if ( $is_pro_blocked ) {
			$card_classes[] = 'wpd-integration-card-pro';
			$card_classes[] = 'wpd-trigger-upgrade-modal';
		}
		if ( $is_enabled ) {
			$card_classes[] = 'wpd-integration-card-enabled';
		}
		?>
		<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>" data-pro="<?php echo $is_pro ? 'true' : 'false'; ?>" data-category="<?php echo esc_attr( $category ); ?>" data-search="<?php echo esc_attr( $search_text ); ?>">
			<span class="wpd-integration-card-status">
				<?php if ( $is_pro ) : ?>
					<span class="wpd-integration-badge wpd-integration-badge-pro <?php echo $is_pro_blocked ? 'wpd-integration-badge-pro-blocked' : 'wpd-integration-badge-pro-unlocked'; ?>"><?php esc_html_e( 'Pro', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></span>
				<?php endif; ?>
				<?php if ( ! $is_pro_blocked ) : ?>
					<?php if ( $is_enabled ) : ?>
						<span class="wpd-integration-badge wpd-integration-badge-enabled"><?php esc_html_e( 'Enabled', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></span>
					<?php else : ?>
						<span class="wpd-integration-badge wpd-integration-badge-disabled"><?php esc_html_e( 'Disabled', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></span>
					<?php endif; ?>
				<?php endif; ?>
			</span>
			<span class="wpd-integration-card-logo">
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="wpd-integration-card-logo-img">
				<?php else : ?>
					<span class="dashicons dashicons-admin-generic wpd-integration-card-logo-icon"></span>
				<?php endif; ?>
			</span>
			<span class="wpd-integration-card-label"><?php echo esc_html( $label ); ?></span>
			<?php if ( $description ) : ?>
				<span class="wpd-integration-card-description"><?php echo esc_html( $description ); ?></span>
			<?php endif; ?>
			<span class="wpd-integration-card-action"><?php esc_html_e( 'Manage', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></span>
		</a>
		<?php
	}
}

WPDAI_Integrations_Manager::get_instance();
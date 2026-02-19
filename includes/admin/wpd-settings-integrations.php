<?php
/**
 * Settings Page - Integrations
 *
 * Uses WPDAI_Integrations_Manager to output the integrations grid and
 * route to individual integration settings via ?integrations=$slug
 *
 * @package Alpha Insights
 * @version 5.2.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 */
defined( 'ABSPATH' ) || exit;

$manager = WPDAI_Integrations_Manager::get_instance();
$manager->output_integrations_page();

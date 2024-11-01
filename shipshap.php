<?php
/**
 * Plugin Name: Shipshap
 * Plugin URI: https://github.com/ShipShap/woocommerce-plugin
 * Description: Plugin for using Shipshap inside WooCommerce
 * Version: 0.2.0
 * Author: Shipshap Team
 * Author URI: https://shipshap.com/
 * Developer: thiagor/Shipshap
 * Developer URI: https://shipshap.com/
 * Text Domain: shipshap
 *
 * WC requires at least: 7.0
 * WC tested up to: 8.2.1
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$plugin_path = trailingslashit(WP_PLUGIN_DIR) . 'woocommerce/woocommerce.php';

if (
    in_array($plugin_path, wp_get_active_and_valid_plugins())
    || in_array($plugin_path, wp_get_active_network_plugins())
) {

    if (!class_exists('Shipshap_Main')) {

        class Shipshap_Main
        {
            public function __construct()
            {
            }

            public function init()
            {
            }
        }

        function shipshap_add_settings($settings_tabs)
        {
            $settings_tabs['shipshap'] = __('Shipshap', 'wc-shipshap');
            return $settings_tabs;
        }

        function shipshap()
        {
            woocommerce_admin_fields(shipshap_get_settings_array());
        }

        function shipshap_update_settings()
        {
            woocommerce_update_options(shipshap_get_settings_array());
        }

        function shipshap_get_settings_array()
        {
            /* translators: label is used for the logging setting. */
            $label = __('Enable Logging', 'wc-shipshap');
            /* translators: Enable Logging description */
            $description = __('Enable the logging of errors.', 'wc-shipshap');

            if (defined('WC_LOG_DIR')) {
                $log_url = add_query_arg('tab', 'logs', add_query_arg('page', 'wc-status', admin_url('admin.php')));
                $log_key = 'shipshap-' . sanitize_file_name(wp_hash('shipshap')) . '-log';
                $log_url = add_query_arg('log_file', $log_key, $log_url);
                /* translators: %s: log url html elements */
                $label .= ' | ' . sprintf(__('%1$sView Log%2$s', 'wc-shipshap'), '<a href="' . esc_url($log_url) . '">', '</a>');
            }
            $settings = array(
                'section_title' => array(
                    'name' => __('Shipshap Options', 'wc-shipshap'),
                    'type' => 'title',
                    'desc' => '',
                    'id' => 'wc_custom_tab',
                ),
                'wc_shipshap_publishable_token' => array(
                    'title' => __('Shipshap Publishable Key', 'shipshap'),
                    'type' => 'text',
                    'id' => 'wc_shipshap_publishable_token',
                    'desc' => __('This key will be used to perform payment operations at Shipshap', 'wc-shipshap'),
                ),
                'wc_shipshap_server_token' => array(
                    'title' => __('Shipshap Server Key', 'shipshap'),
                    'type' => 'text',
                    'id' => 'wc_shipshap_server_token',
                    'desc' => __('This key will be used to perform product management operations at Shipshap', 'wc-shipshap'),
                ),
                'wc_shipshap_debug' => array(
                    'title' => __('Debug Log', 'wc-shipshap'),
                    'desc' => $label,
                    'type' => 'checkbox',
                    'id' => 'wc_shipshap_debug',
                    'default' => 'no',
                ),
                'wc_shipshap_enable_rates' => array(
                    'title' => __('Enable Rates', 'wc-shipshap'),
                    'label' => __('Enable Rates', 'wc-shipshap'),
                    'desc' => __('Enable Rates', 'wc-shipshap'),
                    'type' => 'checkbox',
                    'id' => 'wc_shipshap_enable_rates',
                    'default' => 'no',
                ),
                'wc_shipshap_default_height_on_missing' => array(
                    'title' => __('Default height', 'wc-shipshap'),
                    'label' => __('Default height', 'wc-shipshap'),
                    'desc' => __('Which height to assume in case the product doesnt have one registered. Unit is the one set up in the settings ', 'wc-shipshap'),
                    'type' => 'text',
                    'id' => 'wc_shipshap_default_height_on_missing',
                    'default' => '0',
                ),
                'wc_shipshap_default_width_on_missing' => array(
                    'title' => __('Default width.', 'wc-shipshap'),
                    'label' => __('Default width.', 'wc-shipshap'),
                    'desc' => __('Which width to assume in case the product doesnt have one registered. Unit is the one set up in the settings ', 'wc-shipshap'),
                    'type' => 'text',
                    'id' => 'wc_shipshap_default_width_on_missing',
                    'default' => '0',
                ),
                'wc_shipshap_default_length_on_missing' => array(
                    'title' => __('Default length', 'wc-shipshap'),
                    'label' => __('Default length', 'wc-shipshap'),
                    'desc' => __('Which length to assume in case the product doesnt have one registered. Unit is the one set up in the settings ', 'wc-shipshap'),
                    'type' => 'text',
                    'id' => 'wc_shipshap_default_length_on_missing',
                    'default' => '0',
                ),
                'wc_shipshap_default_weight_on_missing' => array(
                    'title' => __('Default weight', 'wc-shipshap'),
                    'label' => __('Default weight', 'wc-shipshap'),
                    'desc' => __('Which weight to assume in case the product doesnt have one registered. Unit is the one set up in the settings ', 'wc-shipshap'),
                    'type' => 'text',
                    'id' => 'wc_shipshap_default_weight_on_missing',
                    'default' => '0',
                ),
                'section_end' => array(
                    'type' => 'sectionend',
                    'id' => 'wc_section_end',
                ),
            );
            return apply_filters('wc_shipshap_settings', $settings);
        }

        add_filter('woocommerce_settings_tabs_array', 'shipshap_add_settings', 50);
        add_action('woocommerce_settings_tabs_shipshap', 'shipshap');
        add_action('woocommerce_update_options_shipshap', 'shipshap_update_settings');

        require_once dirname(__FILE__) . '/shipshap-utils.php';
        require_once dirname(__FILE__) . '/shipshap-payments.php';
        require_once dirname(__FILE__) . '/shipshap-rates.php';

    }
}

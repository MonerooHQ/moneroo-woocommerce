<?php

use Moneroo\WooCommerce\Moneroo_WC_Gateway;

/**
 * Plugin Name: Moneroo for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/moneroo
 * Description: Accept payments via Mobile Money, Credit Card, Bank transfer through single integration to many payment providers.
 * Author: Axa Zara
 * Author URI: https://axazara.com
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: __STABLE_TAG__
 * Requires at least: 4.9
 * Tested up to: 6.4
 * WC requires at least: 5.3
 * WC tested up to: 8.3
 * Text Domain: moneroo-for-woocommerce
 * Domain Path: /languages.
 */



// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (! in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true)) {
    return;
}

// Include the Composer autoload file
require_once plugin_dir_path(__DIR__) . 'moneroo/vendor/autoload.php';

/**
 * Initialize Moneroo Payment Gateway Class.
 */
function moneroo_wc_init_gateway_class()
{
    // Check if WooCommerce Payment Gateway class exists
    if (! class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Include Moneroo Gateway Class
    require_once plugin_dir_path(__FILE__) . 'src/Moneroo_WC_Gateway.php';

    // Add Moneroo Gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', 'moneroo_wc_add_gateway_class');
}

/**
 * Add Moneroo Payment Gateway to WooCommerce.
 */
function moneroo_wc_add_gateway_class($gateways)
{
    $gateways[] = Moneroo_WC_Gateway::class;
    return $gateways;
}

/**
 * Generate webhook secret upon plugin activation.
 */
function moneroo_wc_generate_webhook_secret()
{
    if (get_option('moneroo_wc_webhook_secret') === false) {
        $webhook_secret = bin2hex(random_bytes(15));
        update_option('moneroo_wc_webhook_secret', $webhook_secret);
    }
}
/**
 * Add action links for the plugin on the plugin page.
 */
function moneroo_wc_action_links($links)
{
    $links[] = '<a href="admin.php?page=wc-settings&tab=checkout&section=moneroo_wc_woocommerce_plugin">' . esc_html__('Settings', 'moneroo-woocommerce') . '</a>';
    $links[] = '<a href="https://docs.moneroo.io/integrations/woocormerce" target="_blank">' . esc_html__('Docs', 'moneroo-woocommerce') . '</a>';
    $links[] = '<a href="https://support.moneroo.io" target="_blank">' . esc_html__('Get help', 'moneroo-woocommerce') . '</a>';
    return $links;
}

/**
 * Load the plugin text domain for translations.
 */
function moneroo_wc_load_plugin_textdomain()
{
    load_plugin_textdomain('moneroo-for-woocommerce', false, basename(dirname(__FILE__)) . '/languages/');
}

// Hook in all our functions
add_action('plugins_loaded', 'moneroo_wc_init_gateway_class');
add_action('plugin_action_links_' . plugin_basename(__FILE__), 'moneroo_wc_action_links');
register_activation_hook(__FILE__, 'moneroo_wc_generate_webhook_secret');
add_action('plugins_loaded', 'moneroo_wc_load_plugin_textdomain');

<?php

namespace Moneroo\WooCommerce;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Moneroo_WC_Gateway_Blocks extends AbstractPaymentMethodType
{
    /**
     * The gateway instance.
     *
     * @var Moneroo_WC_Gateway
     */
    private $gateway;

    protected $name = 'moneroo_wc_woocommerce_plugin';

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = $gateways[$this->name];
        $this->settings = $this->gateway->settings;
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     */
    public function is_active(): bool
    {
        return $this->gateway->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     */
    public function get_payment_method_script_handles(): array
    {
        $script_url = plugins_url('build/index.js', MONEROO_WC_MAIN_FILE);
        $asset_path = plugin_dir_path(__FILE__) . '/build/index.asset.php';
        $dependencies = ['react', 'wp-html-entities'];
        $version = MONEROO_WC__VERSION;

        if (file_exists($asset_path)) {
            $asset = require $asset_path;

            $version = is_array($asset) && isset($asset['version'])
                ? $asset['version']
                : MONEROO_WC__VERSION;

            $dependencies = is_array($asset) && isset($asset['dependencies'])
                ? $asset['dependencies']
                : $dependencies;
        }

        wp_register_script(
            'moneroo_wc_woocommerce_plugin-blocks-integration',
            $script_url,
            $dependencies,
            $version,
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('moneroo_wc_woocommerce_plugin-blocks-integration', 'moneroo');
        }

        return ['moneroo_wc_woocommerce_plugin-blocks-integration'];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     */
    public function get_payment_method_data(): array
    {
        return [
            'title'       => $this->gateway->title,
            'description' => $this->gateway->description,
            'icon'        => plugins_url('/../assets/img/icon.svg', __FILE__),
        ];
    }
}

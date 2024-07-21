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
        wp_register_script(
            'moneroo_wc_woocommerce_plugin-blocks-integration',
            $script_url,
            [
                'react',
                'wp-html-entities',
            ],
            null,
            true
        );

        //         if (function_exists('wp_set_script_translations')) {
        //             wp_set_script_translations('moneroo-for-woocommerce', 'moneroo-for-woocommerce',);
        //         }

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
            'icon'        => $this->gateway->icon,
        ];
    }
}

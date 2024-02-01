<?php

namespace Moneroo\WooCommerce;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\StoreApi\Payments\PaymentContext;
use Automattic\WooCommerce\StoreApi\Payments\PaymentResult;

final class Moneroo_WC_Gateway_Blocks extends AbstractPaymentMethodType {

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
    public function initialize() {
        $gateways       = WC()->payment_gateways->payment_gateways();
        $this->gateway  = $gateways[ $this->name ];
        $this->settings = $this->gateway->settings;
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active(): bool
    {
        return $this->gateway->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles(): array
    {
        wp_register_script(
            'wc-moneroo-blocks',
            plugin_dir_url(__FILE__) . 'block/checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );

        if( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations(
                'wc-moneroo-blocks',
                'moneroo-for-woocommerce',
            );
        }

        return [ 'wc-moneroo-blocks' ];
    }

    public function get_payment_method_data(): array
    {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
            'logo_url' => $this->gateway->icon,
        ];
    }

}
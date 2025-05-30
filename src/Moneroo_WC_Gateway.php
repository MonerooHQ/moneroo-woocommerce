<?php

namespace Moneroo\WooCommerce;

use function add_action;
use function add_filter;
use function esc_html__;

use Exception;

use function get_bloginfo;
use function get_option;
use function get_woocommerce_currency;
use function home_url;
use function is_admin;

use Moneroo\WooCommerce\Handlers\Moneroo_WC_Payment_Handler;

use function plugin_dir_path;
use function plugins_url;
use function register_activation_hook;
use function sanitize_text_field;
use function update_option;
use function wc_add_notice;
use function wc_get_logger;
use function wc_get_order;
use function wp_redirect;

if (! defined('ABSPATH')) {
    exit;
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

class Moneroo_WC_Gateway extends \WC_Payment_Gateway
{
    public \Moneroo\Payment $moneroo;

    public array $moneroo_wc_moneroo_wc_config = [];

    public ?string $moneroo_wc_private_key = null;

    public function __construct()
    {
        $this->moneroo_wc_load_handlers();
        $this->moneroo_wc_initialize_gateway_details();
        $this->moneroo_wc_initialize_settings();
        $this->moneroo_wc_register_filters();
        $this->moneroo_wc_check_if_webhook_secret_is_set_or_generate();
        if ($this->moneroo_wc_keys_are_set()) {
            $this->moneroo_wc_load_moneroo();
        }
        $this->moneroo_wc_register_hooks();
    }

    private function moneroo_wc_initialize_gateway_details(): void
    {
        $this->id = 'moneroo_wc_woocommerce_plugin';
        $this->icon = plugins_url('/../assets/img/icon.svg', __FILE__);
        $this->has_fields = false;
        $this->method_title = 'Moneroo for WooCommerce';
        $this->method_description = esc_html__('Accept payments via Mobile Money, Credit Card, Bank transfer through single integration to many payment gateways.', 'moneroo');
    }

    private function moneroo_wc_register_filters(): void
    {
        add_filter("woocommerce_settings_api_sanitized_fields_{$this->id}", function ($settings) {
            if (isset($settings['webhook_url'])) {
                unset($settings['webhook_url']);
            }
            if (isset($settings['webhook_secret'])) {
                unset($settings['webhook_secret']);
            }
            return $settings;
        });
    }

    private function moneroo_wc_initialize_settings(): void
    {
        $this->init_form_fields();
        $this->init_settings();
        foreach ($this->settings as $settingKey => $value) {
            $this->{$settingKey} = $value;
            $this->moneroo_wc_moneroo_wc_config[$settingKey] = $value;
        }
        if (empty($this->title)) {
            $this->title = esc_html__('Mobile Money, Credit Card, Bank transfer and more', 'moneroo');
        }
        if (empty($this->description)) {
            $this->description = esc_html__('Pay securely with your Mobile Money account, credit card, bank account or other payment methods.', 'moneroo');
        }
    }

    private function moneroo_wc_register_hooks(): void
    {
        register_activation_hook(__FILE__, 'moneroo_wc_generate_webhook_secret');
        add_action('admin_notices', [$this, 'moneroo_wc_do_ssl_check']);
        add_action('admin_notices', [$this, 'moneroo_wc_require_keys']);

        //Moneroo Return Handler
        add_action('woocommerce_api_moneroo_wc_payment_return', [$this, 'moneroo_wc_handle_return']);

        //Moneroo Webhook Handler
        add_action('woocommerce_api_moneroo_wc_webhook', [$this, 'moneroo_wc_handle_webhook']);

        if (is_admin()) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        }
    }

    public function init_form_fields(): void
    {
        $this->form_fields = include plugin_dir_path(__DIR__) . 'src/Settings/moneroo-settings.php';
    }

    public function moneroo_wc_load_moneroo(): void
    {
        $this->moneroo = new \Moneroo\Payment(
            $this->moneroo_wc_private_key,
        );
    }

    /**
     * Process the payment.
     *
     * @param int $order_id
     */
    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);

        if (! $this->moneroo_wc_check_if_gateway_is_available()) {
            wc_add_notice(
                esc_html__('Moneroo is not available at the moment. Please try again later. If you are the site owner, please check your Moneroo settings.', 'moneroo'),
                'error'
            );

            return [
                'result'   => 'fail',
                'redirect' => $order->get_checkout_payment_url(true),
            ];
        }

        $payload = [
            'amount'      => $order->get_total(),
            'currency'    => get_woocommerce_currency(),
            'description' => 'Order #' . $order->get_order_number(),
            'return_url'  => $this->get_payment_return_url($order_id),
            'customer'    => [
                'email'      => $order->get_billing_email(),
                'first_name' => $order->get_billing_first_name(),
                'last_name'  => $order->get_billing_last_name(),
                'phone'      => empty($order->get_billing_phone()) ? null : (int) $order->get_billing_phone(),
                'address'    => empty($order->get_billing_address_1()) ? null : $order->get_billing_address_1(),
                'city'       => empty($order->get_billing_city()) ? null : $order->get_billing_city(),
                'state'      => empty($order->get_billing_state()) ? null : $order->get_billing_state(),
                'country'    => empty($order->get_billing_country()) ? null : $order->get_billing_country(),
                'zip'        => empty($order->get_billing_postcode()) ? null : $order->get_billing_postcode(),
            ],
            'metadata' => [
                'order_id'    => (string) $order->get_id(),
                'customer_id' => (string) $order->get_customer_id(),
                'shop_name'   => get_bloginfo('name'),
                'shop_url'    => get_bloginfo('url'),
                'via'         => 'woocommerce_integration',
            ],
        ];

        try {
            $payment = $this->moneroo->init($payload);
        } catch (Exception $e) {
            wc_add_notice(wp_kses_post($e->getMessage()), 'error');

            wc_get_logger()->error('Moneroo Payment Init Exception: ' . $e->getMessage(), ['source' => 'moneroo']);

            return [
                'result'   => 'fail',
                'redirect' => $order->get_checkout_payment_url(true),
            ];
        }

        $order->add_order_note(
            sprintf(
                wp_kses_post(__('Payment initiated on Moneroo. ID: %s', 'moneroo')),
                esc_html($payment->id)
            )
        );

        $order->update_meta_data('_moneroo_payment_id', $payment->id);

        return [
            'result'   => 'success',
            'redirect' => $payment->checkout_url,
        ];
    }

    /**
     * Get the payment return URL.
     */
    public function get_payment_return_url(string $order_id): string
    {
        $baseURL = esc_url(add_query_arg('wc-api', 'moneroo_wc_payment_return', home_url('/')));
        $baseURL = add_query_arg('order_id', $order_id, $baseURL);

        return str_replace('http:', 'https:', $baseURL);
    }

    /**
     * Handle the payment return from Moneroo.
     */
    public function moneroo_wc_handle_return(): void
    {
        global $woocommerce;

        if (! isset($_GET['order_id'], $_GET['paymentId'])) {
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        $order_id = sanitize_text_field($_GET['order_id']);
        $paymentTransactionId = sanitize_text_field($_GET['paymentId']);

        $order = wc_get_order($order_id);

        if (! $order) {
            wp_redirect(wc_get_checkout_url());
        }

        $monerooHandler = new Moneroo_WC_Payment_Handler(
            $paymentTransactionId,
            $this->moneroo,
            $woocommerce,
        );

        $monerooHandler->handle_return($order, $this->get_return_url($order));
    }

    /**
     * Handle the webhook from Moneroo.
     */
    public function moneroo_wc_handle_webhook(): void
    {
        global $woocommerce;
        $payload = file_get_contents('php://input');

        if (! $this->moneroo_wc_check_if_gateway_is_available()) {
            wc_get_logger()->error('Moneroo Webhook Exception: Gateway is not available', ['source' => 'moneroo']);
            return;
        }

        try {
            $webhook_data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

            if (! isset($webhook_data['data']['id'])) {
                return;
            }

            $transactionId = $webhook_data['data']['id'];

            $monerooHandler = new Moneroo_WC_Payment_Handler(
                $transactionId,
                $this->moneroo,
                $woocommerce,
            );

            $monerooHandler->handle_webhook();
        } catch (Exception $e) {
            wc_get_logger()->error('Moneroo Webhook Exception: ' . $e->getMessage(), ['source' => 'moneroo']);
            return;
        }
    }

    /**
     * Check for SSL certificate.
     */
    public function moneroo_wc_do_ssl_check(): void
    {
        $destination = WC_VERSION >= 3.4
            ? 'wc-settings&tab=advanced'
            : 'wc-settings&tab=checkout';

        if (($this->enabled === 'yes') && get_option('woocommerce_force_ssl_checkout') == 'no') {
            echo wp_kses_post('<div class="error"><p>' . sprintf(__('<strong>%1$s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href="%2$s">forcing the checkout pages to be secured.</a>', 'moneroo'), esc_html($this->method_title), esc_url(admin_url("admin.php?page=$destination"))) . '</p></div>');
        }
    }

    /**
     * Check if API keys are set.
     */
    private function moneroo_wc_keys_are_set(): bool
    {
        return ! empty($this->moneroo_wc_private_key);
    }

    /**
     * Load Moneroo handlers.
     */
    private function moneroo_wc_load_handlers(): void
    {
        require_once plugin_dir_path(__FILE__) . '/Handlers/Moneroo_WC_Payment_Handler.php';
    }

    /**
     * Load plugin text domain.
     */
    public function moneroo_wc_require_keys(): bool
    {
        if ($this->enabled === 'no') {
            return false;
        }
        if (! $this->moneroo_wc_keys_are_set()) {
            echo wp_kses_post('<div class="error"><p>'
                . sprintf(__('<strong>%1$s</strong> is enabled, but you have not entered your API keys. Please enter them <a href="%2$s">here</a>, to be able to accept payments with Moneroo.', 'moneroo'), esc_html($this->method_title), esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=moneroo_woocommerce_plugin'))) . '</p></div>');
            return false;
        }

        return true;
    }

    /**
     * Check if webhook secret is set or generate.
     */
    public function moneroo_wc_check_if_webhook_secret_is_set_or_generate(): void
    {
        if (get_option('moneroo_wc_webhook_secret') === false) {
            $webhook_secret = bin2hex(random_bytes(15));
            update_option('moneroo_wc_webhook_secret', $webhook_secret);
        }
    }

    /**
     * Check if the gateway is available for use.
     */
    public function moneroo_wc_check_if_gateway_is_available(): bool
    {
        if ($this->enabled === 'no') {
            return false;
        }

        return $this->moneroo_wc_keys_are_set();
    }

    public static function moneroo_wc_get_webhook_url(): string
    {
        return esc_url(add_query_arg('wc-api', 'moneroo_wc_webhook', home_url('/')));
    }
}

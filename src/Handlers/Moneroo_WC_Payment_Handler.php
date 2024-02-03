<?php

namespace Moneroo\WooCommerce\Handlers;

use function esc_html__;
use function esc_url_raw;

use Exception;
use Moneroo\Payment;

use function wc_add_notice;
use function wc_get_logger;
use function wc_get_order;
use function wc_reduce_stock_levels;
use function wp_kses;
use function wp_safe_redirect;

class Moneroo_WC_Payment_Handler
{
    private object $woocommerce;

    private Payment $moneroo;

    private string $transactionId;

    private string $returnUrl;

    private bool $is_webhook = false;

    public function __construct(string $transactionId, Payment $moneroo, object $woocommerce)
    {
        $this->transactionId = $transactionId;
        $this->woocommerce = $woocommerce;
        $this->moneroo = $moneroo;
    }

    public function handle_return(object $order, string $returnUrl): void
    {
        $this->returnUrl = esc_url_raw($returnUrl);

        try {
            $response = $this->moneroo->get($this->transactionId);

            $payment_order_id = isset($response->metadata->order_id) ? (int) $response->metadata->order_id : null;

            if (! $payment_order_id) {
                wc_get_logger()->info('Moneroo Payment Exception: Order ID not found in metadata', ['source' => 'moneroo-woocommerce-plugin']);

                $this->redirect_to_checkout_url();
                return;
            }

            if ($order->get_id() !== $payment_order_id) {
                wc_get_logger()->info('Moneroo Payment Exception: Order ID mismatch', ['source' => 'moneroo-woocommerce-plugin']);

                $this->redirect_to_checkout_url();
                return;
            }

            $this->process_payment_response($response, $order);
        } catch (Exception $e) {
            wc_get_logger()->error('Moneroo Payment Exception: ' . wp_kses($e->getMessage(), false), ['source' => 'moneroo-woocommerce-plugin']);

            $this->redirect_to_checkout_url();
        }
    }

    public function handle_webhook(): void
    {
        $this->is_webhook = true;

        try {
            $response = $this->moneroo->get($this->transactionId);
            $payment_order_id = isset($response->metadata->order_id) ? (int) $response->metadata->order_id : null;

            $order = wc_get_order($payment_order_id);
            if (! $order) {
                return;
            }
            $this->process_payment_response($response, $order);
        } catch (Exception $e) {
            wc_get_logger()->error('MPG Moneroo Webhook Exception: ' . wp_kses($e->getMessage(), false), ['source' => 'moneroo-woocommerce']);
            return;
        }
    }

    private function process_payment_response($response, object $order): void
    {
        if ($response->status === \Moneroo\Payment\Status::SUCCESS) {
            $this->handle_payment_success($response, $order);
        } elseif ($response->status === \Moneroo\Payment\Status::PENDING) {
            $this->handle_payment_pending($response, $order);
        } elseif ($response->amount < $order->get_total()) {
            $this->handle_insufficient_payment($response, $order);
        } else {
            $this->handle_payment_failed($order, $response);
        }
    }

    private function handle_payment_success($response, object $order): void
    {
        if ($order->get_status() === 'completed') {
            $this->redirect_to_return_url();
            return;
        }

        $order->update_status('completed');

        $this->woocommerce->cart->empty_cart();

        wc_reduce_stock_levels($order->get_id());

        $order->add_order_note(esc_html__('Payment was successful on Moneroo', 'moneroo-woocommerce'));
        $order->add_order_note("<br> Moneroo Transaction ID: {$response->id}");

        $customer_note = esc_html__('Thank you for your order.<br>', 'moneroo-woocommerce');
        $customer_note .= esc_html__('Your payment was successful, we are now <strong>processing</strong> your order.', 'moneroo-woocommerce');

        $order->add_order_note($customer_note, 1);

        wc_add_notice($customer_note, 'notice');

        $this->redirect_to_return_url();
    }

    private function handle_payment_pending($response, $order): void
    {
        if ($order->get_status() === 'on-hold') {
            $this->redirect_to_return_url();
            return;
        }

        $order->update_status('on-hold');

        $admin_notice = esc_html__('Payment is pending on Moneroo', 'moneroo-woocommerce');
        $admin_notice .= "<br> Moneroo Transaction ID: {$response->id}";

        $order->add_order_note($admin_notice);

        $customer_notice = esc_html__('Thank you for your order.<br>', 'moneroo-woocommerce');
        $customer_notice .= esc_html__('Your payment has not been confirmed yet, so we have to put your order <strong>on-hold</strong>, once the payment is confirmed, we will <strong>process</strong> your order.', 'moneroo-woocommerce');
        $customer_notice .= esc_html__('If this persists, Please, contact us for information regarding this order.', 'moneroo-woocommerce');

        $order->add_order_note($customer_notice, 1);

        $this->redirect_to_return_url();
    }

    private function handle_insufficient_payment($response, $order): void
    {
        if ($order->get_status() === 'on-hold') {
            $this->redirect_to_return_url();
            return;
        }

        $order->update_status('on-hold');

        $admin_notice = sprintf(
            esc_html__('Attention: New order has been placed on hold because of incorrect payment amount. Please, look into it. <br> Moneroo Transaction ID: %s <br> Amount paid: %s %s <br> Order amount: %s %s', 'moneroo-woocommerce'),
            esc_html($response->id),
            esc_html($order->get_currency()),
            esc_html($response->amount),
            esc_html($order->get_order_currency()),
            esc_html($order->order_total)
        );

        $order->add_order_note($admin_notice);

        $customer_notice = esc_html__('Thank you for your order.<br>', 'moneroo-woocommerce');
        $customer_notice .= esc_html__('Your payment has not been confirmed yet, so we have to put your order <strong>on-hold</strong>, once the payment is confirmed, we will <strong>process</strong> your order. ', 'moneroo-woocommerce');
        $customer_notice .= esc_html__('If this persists, Please, contact us for information regarding this order.', 'moneroo-woocommerce');
        $order->add_order_note($customer_notice, 1);

        $this->redirect_to_return_url();
    }

    private function handle_payment_failed(object $order, $response): void
    {
        if ($order->get_status() === 'failed') {
            $this->redirect_to_checkout_url();
            return;
        }

        $adminNotice = esc_html__('Payment failed on Moneroo', 'moneroo-woocommerce');
        $adminNotice .= " Moneroo Transaction ID: {$response->id}";
        $order->add_order_note($adminNotice);

        $customerNotice = esc_html__('Your payment failed. ', 'moneroo-woocommerce');
        $customerNotice .= esc_html__('Please, try funding your account.', 'moneroo-woocommerce');
        $order->add_order_note($customerNotice, 1);

        wc_add_notice($customerNotice, 'error');

        $this->redirect_to_checkout_url();
    }

    private function redirect_to_return_url(): void
    {
        if ($this->is_webhook) {
            return;
        }

        wp_safe_redirect($this->returnUrl);
        exit();
    }

    private function redirect_to_checkout_url(): void
    {
        if ($this->is_webhook) {
            return;
        }

        wc_add_notice(esc_html__('An error occurred while processing your payment. Please try again.', 'moneroo-woocommerce'), 'error');

        wp_safe_redirect(wc_get_checkout_url());
        exit();
    }
}

<?php

namespace Moneroo\WooCommerce\Settings;

// Settings for Moneroo WooCommerce Plugin.
use Moneroo\WooCommerce\Moneroo_WC_Gateway;

defined('ABSPATH') || exit;

$webhook_secret_option = 'moneroo_wc_webhook_secret';

return [
    'enabled' => [
        'title'       => esc_html__('Enable/Disable', 'moneroo'),
        'label'       => esc_html__('Enable Moneroo', 'moneroo'),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'yes',
        'desc_tip'    => true,
    ],
    'title' => [
        'title'       => esc_html__('Title (Optional)', 'moneroo'),
        'type'        => 'text',
        'description' => esc_html__('This controls the title which the user sees during checkout.', 'moneroo'),
        'default'     => esc_html__('Mobile Money, Credit Card, Bank transfer and more', 'moneroo'),
        'desc_tip'    => true,
    ],
    'description' => [
        'title'       => esc_html__('Description (Optional)', 'moneroo'),
        'type'        => 'textarea',
        'maxlength'   => '150',
        'description' => esc_html__('This controls the description which the user sees during checkout.', 'moneroo'),
        'default'     => esc_html__('Pay securely with your Mobile Money account, credit card, bank account or other payment methods.', 'moneroo'),
        'desc_tip'    => true,
    ],
    'moneroo_wc_private_key' => [
        'title'       => esc_html__('Private KEY', 'moneroo'),
        'type'        => 'password',
        'description' => esc_html__('Get your API keys from your Moneroo dashboard', 'moneroo'),
    ],
    'webhook_url' => [
        'title'             => esc_html__('Webhook URL', 'moneroo'),
        'type'              => 'text',
        'desc_tip'          => true,
        'default'           => Moneroo_WC_Gateway::moneroo_wc_get_webhook_url(),
        'description'       => esc_html__('This is the Webhook URL you should add to your Moneroo dashboard for webhook notifications.', 'moneroo'),
        'custom_attributes' => [
            'readonly' => 'readonly',
        ],
    ],
    'webhook_secret' => [
        'title'             => esc_html__('Webhook Secret', 'moneroo'),
        'type'              => 'text',
        'desc_tip'          => true,
        'default'           => get_option($webhook_secret_option),
        'description'       => esc_html__('This is the Webhook Secret you should add to your Moneroo dashboard for webhook notifications.', 'moneroo'),
        'custom_attributes' => [
            'readonly' => 'readonly',
        ],
    ],
];

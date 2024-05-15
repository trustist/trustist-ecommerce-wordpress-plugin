<?php

defined('ABSPATH') || exit;

final class TrustistPaymentsWooCommerce
{
    public static function attach()
    {        
        add_filter('woocommerce_payment_gateways', function ($methods) {
            $methods[] = 'WC_TrustistEcommerce';
            return $methods;
        });

        add_action('plugins_loaded', function () {
            if (class_exists('WC_Payment_Gateway')) {
                // load_plugin_textdomain('wc-trustistecommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
                require_once __DIR__ . '/WCTrustistPayments.php';
            }
        });

        // add_action('before_woocommerce_init', function() {
        //     if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        //         \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        //     }
        // });

        add_action('woocommerce_blocks_loaded', function () {
            require_once __DIR__ . '/TrustistBlocksSupport.php';
            add_action('woocommerce_blocks_payment_method_type_registration', function ($payment_method_registry) {
                $payment_method_registry->register(new WC_TrustistEcommerce_Blocks_Support());
            });
        });
    }
}
?>
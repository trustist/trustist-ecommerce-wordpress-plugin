<?php

defined('ABSPATH') || exit;

final class TrustistPaymentsWooCommerce
{
    public static function attach()
    {        
        add_filter('woocommerce_payment_gateways', function ($methods) {
            $methods[] = 'TrustistEcommerce_WC';

            if (class_exists('WC_Subscriptions_Order')) {
                $methods[] = 'TrustistSubscriptions_WC';
            }

            return $methods;
        });

        add_action('plugins_loaded', function () {
            if (class_exists('WC_Payment_Gateway')) {
                // load_plugin_textdomain('wc-trustistecommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
                require_once __DIR__ . '/TrustistPaymentsWC.php';
            }
            
            if (class_exists('WC_Subscriptions_Order')) {
                require_once __DIR__ . '/TrustistSubscriptionsWC.php';
            }
        });

        add_action('woocommerce_blocks_loaded', function () {

            if (class_exists('WC_Subscriptions_Order')) {
                require_once __DIR__ . '/WCTrustistBlocksSubscriptionSupport.php';
                add_action('woocommerce_blocks_payment_method_type_registration', function ($payment_method_registry) {
                    $payment_method_registry->register(new TrustistEcommerce_WC_Blocks_Subscriptions_Support());
                }, 30);
            }
            require_once __DIR__ . '/WCTrustistBlocksSupport.php';
            add_action('woocommerce_blocks_payment_method_type_registration', function ($payment_method_registry) {
                $payment_method_registry->register(new TrustistEcommerce_WC_Blocks_Support());
            }, 20);
        });
    }
}
?>
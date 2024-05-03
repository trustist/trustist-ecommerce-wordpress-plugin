<?php
defined( 'ABSPATH' ) or die();

final class TrustistEcommerce_WPForms
{
    public static function register_addon_hooks()
    {
        add_filter('wpforms_entry_details_payment_gateway', function ($gateway, $entry_meta) {
            if (!empty($entry_meta['payment_type']) && 'trustist_payment' === $entry_meta['payment_type']) {
                $gateway = 'TrustistEcommerce';
            }

            return $gateway;
        }, 10, 2);

        add_filter('wpforms_entry_details_payment_transaction', function ($transaction, $entry_meta) {
            if (!empty($entry_meta['payment_type']) && 'trustist_payment' === $entry_meta['payment_type'] && !empty($entry_meta['payment_transaction'])) {
                $transaction = $entry_meta['payment_transaction'];
            }

            return $transaction;
        }, 10, 2);

        add_action('wpforms_loaded', function () {
            require_once plugin_dir_path( __FILE__ ) . 'trustist_wpforms.php';
            //new WPForms_Trustist_Payment();
        });
    }

    public static function attach()
    {
        self::register_addon_hooks();
    }
}

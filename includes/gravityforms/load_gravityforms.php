<?php
defined('ABSPATH') || exit;

final class TrustistPaymentsGFM
{
    public static function register_admin_hooks()
    {
        add_action('gform_loaded', function () {
            if (class_exists('GFFormDisplay')) {
                GFForms::include_payment_addon_framework();
                require_once __DIR__ . '/TrustistGFPayments.php';
                GFAddOn::register('TrustistGFPayments');
            }
        });
    }

    public static function activate()
    {
        return true;
    }

    public static function deactivate()
    {
        return true;
    }

    public static function uninstall()
    {
        return true;
    }

    public static function attach()
    {
        self::register_admin_hooks();
    }
}
?>
<?php 
defined('ABSPATH') || exit;

final class TrustistPaymentsGFM
{
    public static function register_admin_hooks()
    {
        add_action('gform_loaded', function () {
            GFForms::include_payment_addon_framework();
            require_once __DIR__.'/GFTrustistPayments.php';
            GFAddOn::register('GFTrustistPayments');
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
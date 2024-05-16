<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_TrustistEcommerce_Blocks_Support extends AbstractPaymentMethodType
{
    protected $name = 'trustistecommerce_payment_gateway';

    public function initialize()
    {
        $this->settings = get_option("woocommerce_{$this->name}_settings", []);

        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway  = $gateways[$this->name];
    }

    public function is_active()
    {
        return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
    }

    public function get_payment_method_script_handles()
    {
        // wp_register_script(
        //     'wc-trustistecommerce-blocks-integration',
        //     plugin_dir_url(__DIR__) . 'woocommerce/build/index.js',
        // 	array(
        // 		'wc-blocks-registry',
        // 		'wc-settings',
        // 		'wp-element',
        // 		'wp-html-entities',
        // 	),
        //     filemtime(plugin_dir_path(__DIR__) . 'woocommerce/build/index.js'),
        //     true
        // );
        // return ['wc-trustistecommerce-blocks-integration'];

        $asset_path   = plugin_dir_path(__DIR__) . 'woocommerce/build/index.asset.php';
        $version      = null;
        $dependencies = array();
        if (file_exists($asset_path)) {
            $asset        = require $asset_path;
            $version      = isset($asset['version']) ? $asset['version'] : $version;
            $dependencies = isset($asset['dependencies']) ? $asset['dependencies'] : $dependencies;
        }

        wp_register_script(
            'wc-misha-blocks-integration',
            plugin_dir_url(__DIR__) . 'woocommerce/build/index.js',
            $dependencies,
            $version,
            true
        );

        return array('wc-misha-blocks-integration');
    }

    public function get_payment_method_data()
    {
        $testmode = 'yes' === $this->settings['testmode'];
        $cards_enabled = $testmode ? get_option("trustist_payments_sandbox_cards_enabled") : get_option("trustist_payments_cards_enabled");
        $icon = TRUSTISTPLUGIN_URL . ($cards_enabled ? 'img/Trustist-all-payment-methods.png' : 'img/Trustist-star-icon-150x150.png');

        return [
            'title'       => $this->settings['title'],
            'description' => $this->settings['description'],
            'supports'  => array_filter($this->gateway->supports, [$this->gateway, 'supports']),
            'icon'         => apply_filters('woocommerce_gateway_icon', $icon),
        ];
    }
}
?>
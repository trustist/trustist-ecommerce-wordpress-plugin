<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class TrustistEcommerce_WC_Blocks_Support extends AbstractPaymentMethodType
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
        $asset_path   = plugin_dir_path(__DIR__) . 'woocommerce/build/products/index.asset.php';
        $version      = null;
        $dependencies = array();
        if (file_exists($asset_path)) {
            $asset        = require $asset_path;
            $version      = isset($asset['version']) ? $asset['version'] : $version;
            $dependencies = isset($asset['dependencies']) ? $asset['dependencies'] : $dependencies;
        }

        wp_register_script(
            'wc-' . $this->name . '-blocks-integration',
            plugin_dir_url(__DIR__) . 'woocommerce/build/products/index.js',
            $dependencies,
            $version,
            true
        );

        return array('wc-' . $this->name . '-blocks-integration');
    }

    public function get_payment_method_data()
    {
        $testmode = 'yes' === $this->settings['testmode'];
        $cards_enabled = TrustistPaymentsSettings::get(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_CARDS_ENABLED_KEY, $testmode);
        $icon = TRUSTISTPLUGIN_URL . ($cards_enabled ? 'img/Trustist-all-payment-methods_full.png' : 'img/Trustist-star-icon-150x150.png');

        return [
            'title'       => $this->settings['title'],
            'description' => $this->settings['description'],
            'supports'  => array_filter($this->gateway->supports, [$this->gateway, 'supports']),
            'icon'         => apply_filters('woocommerce_gateway_icon', $icon),
        ];
    }
}
?>
<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class TrustistEcommerce_WC_Blocks_Subscriptions_Support extends AbstractPaymentMethodType
{
    protected $name = 'trustistecommerce_payment_gateway_subscriptions';

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
        $asset_path   = plugin_dir_path(__DIR__) . 'woocommerce/build/subscriptions/index.asset.php';
        $version      = null;
        $dependencies = array();
        if (file_exists($asset_path)) {
            $asset        = require $asset_path;
            $version      = isset($asset['version']) ? $asset['version'] : $version;
            $dependencies = isset($asset['dependencies']) ? $asset['dependencies'] : $dependencies;
        }

        wp_register_script(
            'wc-' . $this->name . '-blocks-integration',
            plugin_dir_url(__DIR__) . 'woocommerce/build/subscriptions/index.js',
            $dependencies,
            $version,
            true
        );

        return array('wc-' . $this->name . '-blocks-integration');
    }

    public function get_payment_method_data()
    {
        return [
            'title'       => $this->settings['title'],
            'description' => $this->settings['description'],
            'supports'  => array_filter($this->gateway->supports, [$this->gateway, 'supports']),
            'icon'         => TRUSTISTPLUGIN_URL . 'img/Trustist-star-icon-150x150.png',
        ];
    }
}
?>
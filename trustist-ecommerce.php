<?php
/*
Plugin Name: TrustistEcommerce
Plugin URI: https://www.trustistecommerce.com
Description: Take Open Banking or credit card payments in the UK using TrustistEcommerce.
Version: 0.3.1
Author: Trustist
Author URI: https://www.trustist.com
*/
defined( 'ABSPATH' ) or die();

\define('TRUSTISTPLUGIN_VERSION', '0.3.1');
\define('TRUSTISTPLUGIN_SLUG', 'trustistecommerce');
\define('TRUSTISTPLUGIN_NAME', 'TrustistEcommerce');
\define('TRUSTISTPLUGIN_FILE', __FILE__);
\define('TRUSTISTPLUGIN_HOOK', plugin_basename(TRUSTISTPLUGIN_FILE));
\define('TRUSTISTPLUGIN_PATH', realpath(plugin_dir_path(TRUSTISTPLUGIN_FILE)).'/');
\define('TRUSTISTPLUGIN_URL', trailingslashit(plugin_dir_url(TRUSTISTPLUGIN_FILE)));

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/settings/load_settings.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/payment-functions.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/shortcodes/load_shortcodes.php';

// require_once plugin_dir_path( __FILE__ ) . 'includes/wpforms/load_wpforms.php';
// TrustistEcommerce_WPForms::attach();

require_once plugin_dir_path( __FILE__ ) . 'includes/gravityforms/load_gravityforms.php';
TrustistPaymentsGFM::attach();

require_once plugin_dir_path( __FILE__ ) . 'includes/woocommerce/load_woocommerce.php';
TrustistPaymentsWooCommerce::attach();

?>
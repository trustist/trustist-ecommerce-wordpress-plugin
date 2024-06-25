<?php

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'TrustistPaymentsSettings.php';

// Define a function to register the settings
function trustist_payments_register_settings()
{
    // Register a new setting
    register_setting('trustist_payments_settings', 'trustist_payments_public_key');
    register_setting('trustist_payments_settings', 'trustist_payments_private_key');

    register_setting('trustist_payments_settings', 'trustist_payments_sandbox_public_key');
    register_setting('trustist_payments_settings', 'trustist_payments_sandbox_private_key');

    // // Add a section to the settings page
    add_settings_section('trustist_payments_header', 'TrustistEcommerce Settings', 'trustist_payments_header_callback', 'trustist_payments');

    add_settings_section('trustist_payments_section', 'Live API Keys', function () {
    }, 'trustist_payments');

    add_settings_section('trustist_payments_test', '', 'trustist_payments_test_callback', 'trustist_payments');

    add_settings_section('trustist_payments_sandbox_section', 'Sandbox API Keys', function () {
    }, 'trustist_payments');

    add_settings_section('trustist_payments_sandbox_test', '', 'trustist_payments_sandbox_test_callback', 'trustist_payments');

    // Add fields to the section
    add_settings_field('trustist_payments_public_key_field', 'Live Public Key', 'trustist_payments_public_key_field_callback', 'trustist_payments', 'trustist_payments_section');
    add_settings_field('trustist_payments_private_key_field', 'Live Private Key', 'trustist_payments_private_key_field_callback', 'trustist_payments', 'trustist_payments_section');

    add_settings_field('trustist_payments_sandbox_public_key_field', 'Sandbox Public Key', 'trustist_payments_sandbox_public_key_field_callback', 'trustist_payments', 'trustist_payments_sandbox_section');
    add_settings_field('trustist_payments_sandbox_private_key_field', 'Sandbox Private Key', 'trustist_payments_sandbox_private_key_field_callback', 'trustist_payments', 'trustist_payments_sandbox_section');
}

// Callback function for the section
function trustist_payments_header_callback()
{ ?>
    <div style="max-width: 1024px;">
        <p style="font-size: 1.2em;">Welcome to the TrustistEcommerce payments plugin! Thank you for choosing Trustist as your payment gateway.</p>
        <p style="font-size: 1.2em;">This plugin will act as an add-on for Gravity Forms, allowing you to take payments from your visitors using Open Banking (UK only), credit/debit cards, Apple Pay and Google Pay.</p>
        <p style="font-size: 1.2em;">For help on using this plugin please consult our documentation at <a href="https://trustisttransfer.com/docs/">https://trustisttransfer.com/docs/</a>.</p>
        <p style="font-size: 1.2em;">To configure the plugin, you will need to provide your API keys for the live system in the text inputs below. If you are a sandbox user, you can also input your sandbox keys which will allow you to test the setup while building your forms.</p>
        <p style="font-size: 1.2em;">To get access to your API keys, or if you have any other queries, please email us at <a href="mailto:customerservice@trustisttransfer.com">customerservice@trustisttransfer.com</a>.</p>
    </div>
<?php
}

function trustist_payments_test_callback()
{
    $connection_success = TrustistPaymentsSettings::get(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_CONNECTION_SUCCESS_KEY, false);
    $cards_enabled = TrustistPaymentsSettings::get(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_CARDS_ENABLED_KEY, false);
    $merchant_name = TrustistPaymentsSettings::get(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_MERCHANT_NAME_KEY, false);
    $last_updated = TrustistPaymentsSettings::get(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_LAST_UPDATED_KEY, false);
    $standing_orders_enabled = TrustistPaymentsSettings::get(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_STANDING_ORDERS_ENABLED_KEY, false);
?>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row">Connection status</th>
                <td><?php echo $connection_success ? '<span style="color: green;">Success</span>' : '<span style="color: red;">Failed</span>' ?></td>
            </tr>
            <tr>
                <th scope="row">Last updated</th>
                <td><?php echo $last_updated ? esc_html(gmdate('H:i:s d/m/Y', $last_updated)) : ''; ?></td>
            </tr>
            <?php if ($connection_success) { ?>
                <tr>
                    <th scope="row">Merchant name</th>
                    <td><?php echo esc_html($merchant_name) ?></td>
                </tr>
                <tr>
                    <th scope="row">Cards enabled</th>
                    <td><?php echo $cards_enabled ? '<span style="color: green;">Yes</span>' : '<span style="color: red;">No</span>' ?></td>
                </tr>
                <tr>
                    <th scope="row">Standing orders enabled</th>
                    <td><?php echo $standing_orders_enabled ? '<span style="color: green;">Yes</span>' : '<span style="color: red;">No</span>' ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
<?php
}

function trustist_payments_sandbox_test_callback()
{
    $sandbox_connection_success = TrustistPaymentsSettings::get(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_CONNECTION_SUCCESS_KEY, true);
    $sandbox_cards_enabled = TrustistPaymentsSettings::get(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_CARDS_ENABLED_KEY, true);
    $sandbox_merchant_name = TrustistPaymentsSettings::get(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_MERCHANT_NAME_KEY, true);
    $sandbox_last_updated = TrustistPaymentsSettings::get(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_LAST_UPDATED_KEY, true);
    $standing_orders_enabled = TrustistPaymentsSettings::get(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_STANDING_ORDERS_ENABLED_KEY, true);
?>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row">Connection status</th>
                <td><?php echo $sandbox_connection_success ? '<span style="color: green;">Success</span>' : '<span style="color: red;">Failed</span>' ?></td>
            </tr>
            <tr>
                <th scope="row">Last updated</th>
                <td><?php echo $sandbox_last_updated ? esc_html(gmdate('H:i:s d/m/Y', $sandbox_last_updated)) : ''; ?></td>
            </tr>
            <?php if ($sandbox_connection_success) { ?>
                <tr>
                    <th scope="row">Merchant name</th>
                    <td>
                        <?php echo esc_html($sandbox_merchant_name); ?></td>
                </tr>
                <tr>
                    <th scope="row">Cards enabled</th>
                    <td><?php echo $sandbox_cards_enabled ? '<span style="color: green;">Yes</span>' : '<span style="color: red;">No</span>' ?></td>
                </tr>
                <tr>
                    <th scope="row">Standing orders enabled</th>
                    <td><?php echo $standing_orders_enabled ? '<span style="color: green;">Yes</span>' : '<span style="color: red;">No</span>' ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
<?php
}

// Callback function for the sandbox public key field
function trustist_payments_sandbox_public_key_field_callback()
{
    $sandbox_public_key = get_option('trustist_payments_sandbox_public_key');
    echo '<input type="text" name="trustist_payments_sandbox_public_key" value="' . esc_attr($sandbox_public_key) . '" />';
}

// Callback function for the sandbox private key field
function trustist_payments_sandbox_private_key_field_callback()
{
    $sandbox_private_key = get_option('trustist_payments_sandbox_private_key');
    echo '<input type="text" name="trustist_payments_sandbox_private_key" value="' . esc_attr($sandbox_private_key) . '" />';
}
// Callback function for the public key field
function trustist_payments_public_key_field_callback()
{
    $public_key = get_option('trustist_payments_public_key');
    echo '<input type="text" name="trustist_payments_public_key" value="' . esc_attr($public_key) . '" />';
}

// Callback function for the private key field
function trustist_payments_private_key_field_callback()
{
    $private_key = get_option('trustist_payments_private_key');
    echo '<input type="text" name="trustist_payments_private_key" value="' . esc_attr($private_key) . '" />';
}

// Hook into the admin menu to add the settings page
function trustist_payments_add_settings_page()
{
    add_options_page('TrustistEcommerce', 'TrustistEcommerce', 'manage_options', 'trustist_payments', 'trustist_payments_settings_page');
}

// Callback function for the settings page
function trustist_payments_settings_page()
{
?>
    <div class="wrap">
        <img src="<?php echo TRUSTISTPLUGIN_URL ?>/img/Trustist-E-Commerce-01-2048x1059.png" width="220">
        <form method="post" action="options.php" id="frmTrustistPaymentSettings">
            <?php
            settings_fields('trustist_payments_settings');
            do_settings_sections('trustist_payments');
            //submit_button();
            ?>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary tr-button-primary" value="Save Changes & Test Connection">
            </p>
        </form>
    </div>
<?php
}

// Hook into the appropriate actions to register the settings and add the settings page
add_action('admin_init', 'trustist_payments_register_settings');
add_action('admin_menu', 'trustist_payments_add_settings_page');

function trustist_payments_api_keys_updated($option)
{
    trustist_payment_write_log($option . ' updated');

    if (
        $option === TrustistPaymentsSettings::fullyQualifiedKey(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_PUBLIC_API_KEY_KEY, false) ||
        $option === TrustistPaymentsSettings::fullyQualifiedKey(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_PRIVATE_API_KEY_KEY, false)
    ) {
        TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_LAST_UPDATED_KEY, time(), false);

        try {
            $merchant = trustist_payment_get_merchant();
        } catch (Exception $e) {
            trustist_payment_write_log('Error loading merchant: ' . $e);

            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_CONNECTION_SUCCESS_KEY, false, false);
            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_CARDS_ENABLED_KEY, false, false);
            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_MERCHANT_NAME_KEY, '', false);
            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_STANDING_ORDERS_ENABLED_KEY, false, false);

            return;
        }

        trustist_payment_write_log($merchant);

        if ($merchant) {
            $merchant_name = $merchant['name'];
            $cards_enabled = $merchant['paymentMethods']['ryft']['enabled'];
            $cards_available = $merchant['paymentMethods']['ryft']['available'];
            $cards_setting = $cards_enabled === true && $cards_available === true;
            $ob_enabled = $merchant['paymentMethods']['tokenIo']['enabled'];
            $so_monitored = $merchant['paymentMethods']['tokenIo']['standingOrdersMonitored'];
            $so_setting = $ob_enabled === true && $so_monitored === true;

            trustist_payment_write_log('Merchant found: ' . $merchant_name);

            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_MERCHANT_NAME_KEY, $merchant_name, false);
            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_CONNECTION_SUCCESS_KEY, true, false);
            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_CARDS_ENABLED_KEY, $cards_setting, false);
            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_STANDING_ORDERS_ENABLED_KEY, $so_setting, false);
        } else {
            trustist_payment_write_log('Merchant not found');

            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_CONNECTION_SUCCESS_KEY, false, false);
            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_CARDS_ENABLED_KEY, false, false);
            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_MERCHANT_NAME_KEY, '', false);
            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_STANDING_ORDERS_ENABLED_KEY, false, false);
        }
    }

    if (
        $option === TrustistPaymentsSettings::fullyQualifiedKey(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_PUBLIC_API_KEY_KEY, true) ||
        $option === TrustistPaymentsSettings::fullyQualifiedKey(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_PRIVATE_API_KEY_KEY, true)
    ) {
        TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_LAST_UPDATED_KEY, time(), true);

        try {
            $merchant = trustist_payment_get_merchant(true);
        } catch (Exception $e) {
            trustist_payment_write_log('Error loading merchant: ' . $e);

            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_CONNECTION_SUCCESS_KEY, false, true);
            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_CARDS_ENABLED_KEY, false, true);
            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_MERCHANT_NAME_KEY, '', true);
            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_STANDING_ORDERS_ENABLED_KEY, false, true);
            return;
        }

        trustist_payment_write_log($merchant);

        if (isset($merchant)) {
            $merchant_name = $merchant['name'];
            $cards_enabled = $merchant['paymentMethods']['ryft']['enabled'];
            $cards_available = $merchant['paymentMethods']['ryft']['available'];
            $cards_setting = $cards_enabled === true && $cards_available === true;
            $ob_enabled = $merchant['paymentMethods']['tokenIo']['enabled'];
            $so_monitored = $merchant['paymentMethods']['tokenIo']['standingOrdersMonitored'];
            $so_setting = $ob_enabled === true && $so_monitored === true;

            trustist_payment_write_log('Merchant found: ' . $merchant_name);

            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_MERCHANT_NAME_KEY, $merchant_name, true);
            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_CONNECTION_SUCCESS_KEY, true, true);
            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_CARDS_ENABLED_KEY, $cards_setting, true);
            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_STANDING_ORDERS_ENABLED_KEY, $so_setting, true);
        } else {
            trustist_payment_write_log('Merchant not found');

            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_CONNECTION_SUCCESS_KEY, false, true);
            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_CARDS_ENABLED_KEY, false, true);
            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_MERCHANT_NAME_KEY, '', true);
            TrustistPaymentsSettings::set(TrustistPaymentsSettings::TRUSTIST_PAYMENTS_STANDING_ORDERS_ENABLED_KEY, false, true);
        }
    }
}

add_action('added_option', 'trustist_payments_api_keys_updated', 10, 3);
add_action('updated_option', 'trustist_payments_api_keys_updated', 10, 3);

function trustist_payments_enqueue_admin_styles()
{
    $screen = get_current_screen();

    // Only add styles on the Trustist payments settings page
    if ($screen->id === 'settings_page_trustist_payments') {
        wp_register_style('trustist-payments-settings-styles', false);
        wp_enqueue_style('trustist-payments-settings-styles');

        $custom_css = '
            #frmTrustistPaymentSettings input[type=text] {
                width: 420px !important;
                padding: 4px 8px !important;
            }
            #frmTrustistPaymentSettings th {
                width: 140px !important;
                vertical-align: middle !important;
            }
            #frmTrustistPaymentSettings h2:first-of-type {
                font-size: 1.5em !important;
            }
            #frmTrustistPaymentSettings .tr-button-primary {
                width: 580px !important;
                height: 40px !important;
                border-radius: 10px 10px 10px 10px !important;
                background-color: #FF7100 !important;
                color: #0f1111 !important;
                border: none !important;
                font-weight: 500 !important;
                font-size: 1.2em !important;
            }
        ';

        wp_add_inline_style('trustist-payments-settings-styles', $custom_css);

        // Debugging: Confirm the function executed
        error_log('Custom admin styles enqueued');
    }
}
add_action('admin_enqueue_scripts', 'trustist_payments_enqueue_admin_styles');

?>
<?php

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
    $connection_success = get_option('trustist_payments_connection_success');
    $cards_enabled = get_option('trustist_payments_cards_enabled');
    $merchant_name = get_option('trustist_payments_merchant_name');
    $last_updated = get_option('trustist_payments_last_updated');
?>
    <p style="font-size: 1.2em;">Connection status: <?= $connection_success ? '<span style="color: green;">Success</span>' : '<span style="color: red;">Failed</span>' ?></p>

    <?php if ($connection_success) { ?>
        <p style="font-size: 1.2em;">Merchant name: <?= $merchant_name ?></p>
        <p style="font-size: 1.2em;">Last updated: <?= $last_updated ? date('H:i:s d/m/Y', $last_updated) : '' ?></p>
        <p style="font-size: 1.2em;">Cards enabled: <?= $cards_enabled ? '<span style="color: green;">Yes</span>' : '<span style="color: red;">No</span>' ?></p>
    <?php
    }
}

function trustist_payments_sandbox_test_callback()
{
    $sandbox_connection_success = get_option('trustist_payments_sandbox_connection_success');
    $sandbox_cards_enabled = get_option('trustist_payments_sandbox_cards_enabled');
    $sandbox_merchant_name = get_option('trustist_payments_sandbox_merchant_name');
    $sandbox_last_updated = get_option('trustist_payments_sandbox_last_updated');
    ?>
    <p style="font-size: 1.2em;">Connection status: <?= $sandbox_connection_success ? '<span style="color: green;">Success</span>' : '<span style="color: red;">Failed</span>' ?></p>

    <?php if ($sandbox_connection_success) { ?>
        <p style="font-size: 1.2em;">Merchant name: <?= $sandbox_merchant_name ?></p>
        <p style="font-size: 1.2em;">Last updated: <?= $sandbox_last_updated ? date('H:i:s d/m/Y', $sandbox_last_updated) : '' ?></p>
        <p style="font-size: 1.2em;">Cards enabled: <?= $sandbox_cards_enabled ? '<span style="color: green;">Yes</span>' : '<span style="color: red;">No</span>' ?></p>
    <?php
    }
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
        <form method="post" action="options.php">
            <?php
            settings_fields('trustist_payments_settings');
            do_settings_sections('trustist_payments');
            //submit_button();
            ?>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary tr-button-primary" value="Save Changes">
            </p>
        </form>
    </div>
    <style>
        input[type=text] {
            width: 420px !important;
            padding: 4px 8px !important;
        }

        th {
            width: 140px !important;
            vertical-align: middle !important;
        }

        h2:first-of-type {
            font-size: 1.5em !important;
        }

        .tr-button-primary {
            width: 580px !important;
            height: 40px !important;
            border-radius: 10px 10px 10px 10px !important;
            background-color: #FF7100 !important;
            color: #0f1111 !important;
            border: none !important;
            font-weight: 500 !important;
            font-size: 1.2em !important;
        }
    </style>
<?php
}

// Hook into the appropriate actions to register the settings and add the settings page
add_action('admin_init', 'trustist_payments_register_settings');
add_action('admin_menu', 'trustist_payments_add_settings_page');

function my_updated_option_function($option, $old_value, $new_value)
{
    trustist_payment_write_log($option . ' updated');

    if ($option === 'trustist_payments_public_key' || $option === 'trustist_payments_private_key') {
        update_option('trustist_payments_last_updated', time());

        try {
            $merchant = trustist_payment_get_merchant();
        } catch (Exception $e) {
            trustist_payment_write_log('Error loading merchant: ' . $e);

            update_option('trustist_payments_connection_success', false);
            update_option('trustist_payments_cards_enabled', false);
            update_option('trustist_payments_merchant_name', '');
            return;
        }

        trustist_payment_write_log($merchant);

        if ($merchant) {
            $merchant_name = $merchant['name'];
            $cards_enabled = $merchant['paymentMethods']['ryft']['enabled'];
            $cards_available = $merchant['paymentMethods']['ryft']['available'];
            $cards_setting = $cards_enabled === true && $cards_available === true;

            trustist_payment_write_log('Merchant found: ' . $merchant_name);

            update_option('trustist_payments_merchant_name', $merchant_name);
            update_option('trustist_payments_connection_success', true);
            update_option('trustist_payments_cards_enabled', $cards_setting);
        } else {
            trustist_payment_write_log('Merchant not found');

            update_option('trustist_payments_connection_success', false);
            update_option('trustist_payments_cards_enabled', false);
            update_option('trustist_payments_merchant_name', '');
        }
    }

    if ($option === 'trustist_payments_sandbox_public_key' || $option === 'trustist_payments_sandbox_private_key') {
        update_option('trustist_payments_sandbox_last_updated', time());

        try {
            $merchant = trustist_payment_get_merchant(true);
        } catch (Exception $e) {
            trustist_payment_write_log('Error loading merchant: ' . $e);

            update_option('trustist_payments_sandbox_connection_success', false);
            update_option('trustist_payments_sandbox_cards_enabled', false);
            update_option('trustist_payments_sandbox_merchant_name', '');
            return;
        }

        trustist_payment_write_log($merchant);

        if (isset($merchant)) {
            $merchant_name = $merchant['name'];
            $cards_enabled = $merchant['paymentMethods']['ryft']['enabled'];
            $cards_available = $merchant['paymentMethods']['ryft']['available'];
            $cards_setting = $cards_enabled === true && $cards_available === true;

            trustist_payment_write_log('Merchant found: ' . $merchant_name);

            update_option('trustist_payments_sandbox_merchant_name', $merchant_name);
            update_option('trustist_payments_sandbox_connection_success', true);
            update_option('trustist_payments_sandbox_cards_enabled', $cards_setting);
        } else {
            trustist_payment_write_log('Merchant not found');

            update_option('trustist_payments_sandbox_connection_success', false);
            update_option('trustist_payments_sandbox_cards_enabled', false);
            update_option('trustist_payments_sandbox_merchant_name', '');
        }
    }
}

add_action('updated_option', 'my_updated_option_function', 10, 3);
?>
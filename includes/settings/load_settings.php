<?php

// Define a function to register the settings
function trustist_payments_register_settings() {
    // Register a new setting
    register_setting('trustist_payments_settings', 'trustist_payments_public_key');
    register_setting('trustist_payments_settings', 'trustist_payments_private_key');
    register_setting('trustist_payments_settings', 'trustist_payments_sandbox_public_key');
    register_setting('trustist_payments_settings', 'trustist_payments_sandbox_private_key');
    
    // Add a section to the settings page
    add_settings_section('trustist_payments_section', 'TrustistEcommerce Settings', 'trustist_payments_section_callback', 'trustist_payments');
    
    // Add fields to the section
    add_settings_field('trustist_payments_public_key_field', 'Public Key', 'trustist_payments_public_key_field_callback', 'trustist_payments', 'trustist_payments_section');
    add_settings_field('trustist_payments_private_key_field', 'Private Key', 'trustist_payments_private_key_field_callback', 'trustist_payments', 'trustist_payments_section');
    add_settings_field('trustist_payments_sandbox_public_key_field', 'Sandbox Public Key', 'trustist_payments_sandbox_public_key_field_callback', 'trustist_payments', 'trustist_payments_section');
    add_settings_field('trustist_payments_sandbox_private_key_field', 'Sandbox Private Key', 'trustist_payments_sandbox_private_key_field_callback', 'trustist_payments', 'trustist_payments_section');
}

// Callback function for the section
function trustist_payments_section_callback() {
    echo 'Configure your payment gateway settings here.';
}
// Callback function for the sandbox public key field
function trustist_payments_sandbox_public_key_field_callback() {
    $sandbox_public_key = get_option('trustist_payments_sandbox_public_key');
    echo '<input type="text" name="trustist_payments_sandbox_public_key" value="' . esc_attr($sandbox_public_key) . '" />';
}

// Callback function for the sandbox private key field
function trustist_payments_sandbox_private_key_field_callback() {
    $sandbox_private_key = get_option('trustist_payments_sandbox_private_key');
    echo '<input type="text" name="trustist_payments_sandbox_private_key" value="' . esc_attr($sandbox_private_key) . '" />';
}
// Callback function for the public key field
function trustist_payments_public_key_field_callback() {
    $public_key = get_option('trustist_payments_public_key');
    echo '<input type="text" name="trustist_payments_public_key" value="' . esc_attr($public_key) . '" />';
}

// Callback function for the private key field
function trustist_payments_private_key_field_callback() {
    $private_key = get_option('trustist_payments_private_key');
    echo '<input type="text" name="trustist_payments_private_key" value="' . esc_attr($private_key) . '" />';
}

// Hook into the admin menu to add the settings page
function trustist_payments_add_settings_page() {
    add_options_page('TrustistEcommerce', 'TrustistEcommerce', 'manage_options', 'trustist_payments', 'trustist_payments_settings_page');
}

// Callback function for the settings page
function trustist_payments_settings_page() {
    ?>
    <div class="wrap">
        <h1>TrustistEcommerce Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('trustist_payments_settings');
            do_settings_sections('trustist_payments');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Hook into the appropriate actions to register the settings and add the settings page
add_action('admin_init', 'trustist_payments_register_settings');
add_action('admin_menu', 'trustist_payments_add_settings_page');
?>
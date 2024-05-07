<?php
defined( 'ABSPATH' ) or die();

function trustist_payment_button_shortcode($atts) {
    // Set default attributes
    $atts = shortcode_atts(array(
        'price' => '',
        'return_url' => '', 
        'order_number' => '',
        'test' => 'false'
    ), $atts, 'trustist_payment_button');

    // Check if the price attribute is provided and is numeric
    if (empty($atts['price']) || !is_numeric($atts['price'])) {
        return '<p>Error: No price provided</p>'; 
    }
    
    // Generate the button HTML
    $html = '<button class="trustist-payment-button" ' .
                'data-price="' . esc_attr($atts['price']) . '" ' .
                'data-order-number="' . esc_attr($atts['order_number']) . '" ' .
                'data-test="' . esc_attr($atts['test']) . '" ' .
                'data-return-url="' . esc_url($atts['return_url']) . '">' .
                'Pay Now - £' . esc_html($atts['price']) . 
        '</button>';

    // Return the button, not echo
    return $html;
}

function trustist_payment_result_shortcode($atts) {
    $atts = shortcode_atts(array(
        'test' => 'false'
    ), $atts, 'trustist_payment_result');

    $isTest = isset($atts['test']) ? true : false;
    
    $status = trustist_payment_plugin_get_payment_status($isTest);

    if (isset($status) && $status === 'COMPLETE') {
        return '<p>Payment was successful!</p>';
    } elseif (isset($status) && $status !== 'COMPLETE') {
        return '<p>Payment failed.</p>';
    }
    return '<p>Unknown payment status.</p>';
}

// Register the shortcodes with WordPress
add_shortcode('trustist_payment_button', 'trustist_payment_button_shortcode');
add_shortcode('trustist_payment_result', 'trustist_payment_result_shortcode');

// Register AJAX actions
add_action('wp_ajax_process_payment', 'trustist_payment_plugin_process_payment');
add_action('wp_ajax_nopriv_process_payment', 'trustist_payment_plugin_process_payment');

// Function to handle the payment processing
function trustist_payment_plugin_process_payment() {
    // Validate request, interact with payment API, etc.
    $price = isset($_POST['price']) ? sanitize_text_field($_POST['price']) : '';
    $returnUrl = isset($_POST['returnUrl']) ? sanitize_text_field($_POST['returnUrl']) : '';
    $orderNumber = isset($_POST['orderNumber']) ? sanitize_text_field($_POST['orderNumber']) : '';
    $isTest = isset($_POST['test']) ? true : false;

    // create the payment
    $request = new PaymentRequest($price, $orderNumber, null, null, null, $returnUrl);
    trustist_payment_write_log($request);
    $payment = trustist_payment_create_payment($request, $isTest);

    // todo: persist the payment ID rather than rely on the return URL querystring, which is not secure

    // Send a JSON response
    wp_send_json_success(array('paylink' => $payment['payLink']));
}

// Function to return the payment result
function trustist_payment_plugin_get_payment_status($isTest = false) {
    // currently getting the payment ID from the result URL, this is not wise!
    $transactionId = isset($_GET['tr-payment-id']) ? sanitize_text_field($_GET['tr-payment-id']) : '';

    if ($transactionId === '') {
        return 'unknown';
    }

    $payment = trustist_payment_get_payment($transactionId, $isTest);

    // should this be an array?
    return $payment['status'];
}

// Enqueue and localize your JavaScript file
function trustist_payment_plugin_enqueue_scripts() {
    wp_enqueue_script('trustist-plugin-script', TRUSTISTPLUGIN_URL . 'js/trustist-payment-plugin-script.js', array('jquery'), TRUSTISTPLUGIN_VERSION, true);
    wp_localize_script('trustist-plugin-script', 'trustistPluginAjax', array('ajaxurl' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'trustist_payment_plugin_enqueue_scripts');

function trustist_payment_plugin_enqueue_styles() {
    // Register the style like this for a plugin:
    wp_enqueue_style('trustist-plugin-style', TRUSTISTPLUGIN_URL . 'css/trustist-payments.css');
}
add_action('wp_enqueue_scripts', 'trustist_payment_plugin_enqueue_styles');

?>
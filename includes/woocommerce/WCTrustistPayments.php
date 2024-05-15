<?php

defined('ABSPATH') || die();

class WC_TrustistEcommerce extends WC_Payment_Gateway
{
    private $log;

    // Constructor for initializing the payment gateway
    public function __construct()
    {
        $this->id = 'trustistecommerce_payment_gateway';
        $this->method_title = 'TrustistEcommerce Payment Gateway';
        $this->method_description = 'Take Open Banking or credit card payments in the UK using TrustistEcommerce.';
        $this->has_fields = true;
        $this->supports = array(
            'products',
            //'subscriptions',
        );
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode');

        $testmode = 'yes' === $this->settings['testmode'];
        $cards_enabled = $testmode ? get_option("trustist_payments_sandbox_cards_enabled") : get_option("trustist_payments_cards_enabled");
        $icon = TRUSTISTPLUGIN_URL . ($cards_enabled ? '\img\Trustist-all-payment-methods-24h.png' : '\img\Trustist-star-icon-150x150.png');
        $this->icon = apply_filters('woocommerce_gateway_icon', $icon);

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        //add_action('woocommerce_wc_gateway_trustistecommerce_process_response', [&$this, 'process_response']);
        add_action('woocommerce_api_' . $this->id, array($this, 'process_response'));
    }

    // Initialize settings fields
    public function init_form_fields()
    {
        $this->form_fields = array(
            'title' => array(
                'title' => 'Title',
                'type' => 'text',
                'description' => 'This controls the title displayed during checkout.',
                'default' => 'TrustistEcommerce',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'textarea',
                'description' => 'This controls the description displayed during checkout.',
                'default' => 'Pay using TrustistEcommerce',
            ),
            'testmode' => array(
                'title'       => 'Test mode',
                'label'       => 'Enable Test Mode',
                'type'        => 'checkbox',
                'description' => 'Place the payment gateway in test mode using sandbox API keys.',
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'enabled' => array(
                'title'       => 'Enable/Disable',
                'label'       => 'Enable TrustistEcommerce Gateway',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
        );
    }

    /**
     * Check if this gateway is enabled and available in the user's country
     *
     * @access public
     * @return bool
     */
    function is_valid_for_use()
    {
        if (!in_array(get_woocommerce_currency(), apply_filters('woocommerce_trustistecommerce_supported_currencies', array('GBP')))) {
            return false;
        }

        // return true;
    }

    // Process payment
    public function process_payment($order_id)
    {
        global $woocommerce;

        //$order = new WC_Order($order_id);
        $order = wc_get_order($order_id);

        // Do nothing if the cart total is not greater than zero
        if (!($woocommerce->cart->total > 0)) return null;

        $total = wc_format_decimal($order->get_total(), 2);
        $description = 'Order #' . $order_id;

        // if the payment has already been created, return the paylink
        $payment_id = $order->get_meta('payment_id');
        $paylink = $order->get_meta('paylink');
        if ($payment_id && $paylink) {
            $payment = trustist_payment_get_payment($payment_id, $this->is_testmode());

            if ($payment['amount'] === $total) {
                return array(
                    'result'          => 'success',
                    'redirect'     => $paylink,
                );
            }
        }

        $order->set_payment_method_title(TRUSTISTPLUGIN_NAME);
        $order->set_payment_method(TRUSTISTPLUGIN_SLUG);
        $order->save();

        $order_data = $order->get_data();
        $buyer_name = $order_data['billing']['first_name'] . ' ' . $order_data['billing']['last_name'];
        $buyer_email = $order_data['billing']['email'];

        if ('' == get_option('permalink_structure')) {
            $redirect_url = get_site_url() . '/?wc-api=trustistecommerce_payment_gateway&order_id=' . $order_id;
        } else {
            $redirect_url = get_site_url() . '/wc-api/trustistecommerce_payment_gateway/?order_id=' . $order_id;
        }

        $paymentRequest = new PaymentRequest((float) $total, (string) $order_id, $description, $buyer_name, $buyer_email, $redirect_url);
        wc_get_logger()->debug(print_r($paymentRequest, true));

        try {
            $payment = trustist_payment_create_payment($paymentRequest, $this->is_testmode());
        } catch (GuzzleHttp\Exception\ClientException $e) {
            wc_get_logger()->error(
                'Error paying for order ' . $order_id,
                array(
                    'correlation_id' => $e->getResponse()->getHeaderLine('CorrelationId'),
                    'message' => $e->getMessage()
                )
            );
        }

        wc_get_logger()->debug(print_r($payment, true));

        $order->update_meta_data('payment_id', $payment['id']);
        $order->update_meta_data('paylink', $payment['payLink']);
        $order->save();

        return array(
            'result'          => 'success',
            'redirect'     => $payment['payLink'],
        );
    }

    public function process_response()
    {
        global $woocommerce;

        $order_id = $_GET['order_id'];

        if ($order_id == 0 || $order_id == '') {
            return;
        }

        $order = wc_get_order($order_id);

        if ($order->has_status('completed') || $order->has_status('processing')) {
            return;
        }

        $payment_id = $order->get_meta('payment_id');
        $payment = trustist_payment_get_payment($payment_id, $this->is_testmode());

        if ($payment['status'] === 'COMPLETE') {
            $order->payment_complete();
            $order->add_order_note(
                'Payment completed successfully. Payment ID: ' . $payment["id"]
            );

            // Remove cart
            $woocommerce->cart->empty_cart();
            $redirect_checkout = $this->get_return_url($order);
        } else {
            // $order->update_status('failed');
            // $order->add_order_note(
            //     'Payment failed. Payment ID: ' . $payment["id"]
            // );
            $redirect_checkout = $this->checkout_url();
        }

        header('HTTP/1.1 200 OK');
        echo '<script>location.replace("' . $redirect_checkout . '");</script>';
        echo '<noscript><meta http-equiv="refresh" content="2; url=' . $redirect_checkout . '">Redirecting..</noscript>';
        exit;
    }

    // // Display payment fields during checkout
    // public function payment_fields()
    // {
    //     // Display payment fields such as credit card info or other required info
    //     // ...
    // }

    // // Validate payment fields
    // public function validate_fields()
    // {
    //     // Validate payment fields submitted by the customer
    //     // ...
    // }

    private function is_testmode()
    {
        return 'yes' === (string) $this->get_option('testmode') ? true : false;
    }
    
    private function checkout_url()
    {
        return \function_exists('wc_get_checkout_url') ? esc_url(wc_get_checkout_url()) : esc_url($GLOBALS['woocommerce']->cart->get_checkout_url());
    }
}
?>
<?php

defined('ABSPATH') || die();

class WC_TrustistSubscriptions extends WC_Payment_Gateway
{
    // Constructor for initializing the payment gateway
    public function __construct()
    {
        $this->id = 'trustistecommerce_payment_gateway_subscriptions';
        $this->method_title = 'TrustistEcommerce Payment Gateway Subscriptions Support';
        $this->method_description = 'Use TrustistEcommerce and Open Banking to use standing orders for WooCommerce Subscriptions.';
        $this->has_fields = true;
        $this->supports = array(
            'products',
            'subscriptions',
        );
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->icon = apply_filters('woocommerce_gateway_icon', TRUSTISTPLUGIN_URL . 'img/Trustist-star-icon-150x150.png');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
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

        return true;
    }

    // Process payment
    public function process_payment($order_id)
    {
        global $woocommerce;

        $order = wc_get_order($order_id);

        $order_items = $order->get_items();

        $is_subscription = ($this->subscr_is_active()) ? WC_Subscriptions_Order::order_contains_subscription($order) : FALSE;

        // can only handle standard products or one subscription on its own per order
        if (!$is_subscription || count($order_items) != 1) {
            // Do nothing if the cart total is not greater than zero
            wc_get_logger()->debug('Cannot pay for non-single subscription order ' . $order_id . ', count: ' . count($order_items));

            return array(
                'result'          => 'failure',
                'redirect'     => $this->checkout_url(),
            );
        }

        $product = $order->get_product_from_item($order_items[array_key_first($order_items)]);

        $description = $product->get_title();

        $billing_period = WC_Subscriptions_Order::get_subscription_period($order);

        switch ($billing_period) {
            case 'month':
                $billing_period = 'Monthly';
                break;
            case 'day':
                $billing_period = 'Daily';
                break;
            case 'year':
                $billing_period = 'Annually';
                break;
        }

        $subscriptions = wcs_get_subscriptions_for_order($order_id);
        $subscription = $subscriptions[array_key_first($subscriptions)];

        $price_per_period = WC_Subscriptions_Order::get_recurring_total($order);

        // if the payment has already been created, return the paylink
        $payment_id = $order->get_meta('payment_id');
        $paylink = $order->get_meta('paylink');

        if ($payment_id && $paylink) {
            $payment = trustist_payment_get_subscription($payment_id, $this->is_testmode());

            if ($payment['amount'] === $price_per_period) {
                return array(
                    'result'          => 'success',
                    'redirect'     => $paylink,
                );
            }
        }

        $subscription_interval = WC_Subscriptions_Order::get_subscription_interval($order, $product->product_id);
        $subscription_length = WC_Subscriptions_Order::get_subscription_length($order, $product->product_id);

        // WC Subscriptions 2.0 deprecated the subscription length property
        if (!$subscription_length) {
            if ( WC_Subscriptions_Synchroniser::subscription_contains_synced_product( $subscription->id ) ) {
                $length_from_timestamp = $subscription->get_time( 'next_payment' );
            } elseif ( $trial_end_timestamp > 0 ) {
                $length_from_timestamp = $subscription->get_time( 'trial_end' );
            } else {
                $length_from_timestamp = $subscription->get_time( 'start' );
            }
            
            $subscription_length = wcs_estimate_periods_between( $length_from_timestamp, $subscription->get_time( 'end' ), $subscription->get_billing_period() );
        }

        $subscription_installments = $subscription_length / $subscription_interval;
        $startDate = $subscription->get_date('start'); 

        $order->set_payment_method_title(TRUSTISTPLUGIN_NAME);
        $order->set_payment_method(TRUSTISTPLUGIN_NAME);
        $order->save();

        $order_data = $order->get_data();
        $buyer_name = $order_data['billing']['first_name'] . ' ' . $order_data['billing']['last_name'];
        $buyer_email = $order_data['billing']['email'];

        if ('' == get_option('permalink_structure')) {
            $redirect_url = get_site_url() . '/?wc-api=' . $this->id . '&order_id=' . $order_id;
        } else {
            $redirect_url = get_site_url() . '/wc-api/' . $this->id . '/?order_id=' . $order_id;
        }

        try {
            $standingOrderRequest = new StandingOrderRequest(
                (float) $price_per_period,
                (string) $order_id,
                $description,
                $billing_period,
                gmdate('Y-m-d', strtotime($startDate)),
                $subscription_installments,
                $buyer_name,
                null,
                $redirect_url
            );

            wc_get_logger()->debug(print_r($standingOrderRequest, true));

            $payment = trustist_payment_create_subscription($standingOrderRequest, $this->is_testmode());
        } catch (GuzzleHttp\Exception\ClientException $e) {
            wc_get_logger()->error(
                'Error paying for order ' . $order_id,
                array(
                    'correlation_id' => $e->getResponse()->getHeaderLine('CorrelationId'),
                    'message' => $e->getMessage()
                )
            );

            return array(
                'result'          => 'failure',
                'redirect'     => $this->checkout_url(),
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
        $is_subscription = ($this->subscr_is_active()) ? WC_Subscriptions_Order::order_contains_subscription($order) : FALSE;

        if (!$is_subscription) {
            // Do nothing if the cart total is not greater than zero
            wc_get_logger()->debug('Cannot pay for non-single subscription order ' . $order_id);
            //wc_get_logger()->debug(print_r($woocommerce->cart, true));

            return array(
                'result'          => 'failure',
                'redirect'     => $this->checkout_url(),
            );
        }

        if ($order->has_status('completed') || $order->has_status('processing')) {
            $redirect_checkout = $this->get_return_url($order);
        }
        if (empty($redirect_checkout)) {
            $payment_id = $order->get_meta('payment_id');

            $payment = trustist_payment_get_subscription($payment_id, $this->is_testmode());

            if ($payment['status'] === 'COMPLETE' || $payment['status'] === 'ACTIVE') {
            $order->payment_complete();
            $order->add_order_note(
                'Payment completed successfully. Payment ID: ' . $payment["id"]
            );

            // Remove cart
            $woocommerce->cart->empty_cart();

            $redirect_checkout = $this->get_return_url($order);
            } else {
            $redirect_checkout = $this->checkout_url();
            }
        }

        wp_redirect($redirect_checkout);
        exit;
    }

    private function subscr_is_active()
    {
        $testmode = $this->get_option('testmode') ? true : false;
        $standingOrdersEnabled = TrustistPaymentsSettings::get(TrustistPaymentsSettings::STANDING_ORDERS_ENABLED_KEY, $testmode, false);
        $subscriptionsInstalled = class_exists('WC_Subscriptions_Order');

        return $subscriptionsInstalled && $standingOrdersEnabled;
    }

    private function is_testmode()
    {
        return 'yes' === (string) $this->get_option('testmode') ? true : false;
    }

    private function checkout_url()
    {
        return \function_exists('wc_get_checkout_url') ? esc_url(wc_get_checkout_url()) : esc_url($GLOBALS['woocommerce']->cart->get_checkout_url());
    }
}

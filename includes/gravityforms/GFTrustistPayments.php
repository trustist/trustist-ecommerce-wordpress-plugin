<?php

defined('ABSPATH') || die();

class GFTrustistPayments extends GFPaymentAddOn
{
    protected $_version = TRUSTISTPLUGIN_VERSION;
    protected $_slug = 'trustist-for-gravityforms';
    protected $_full_path = TRUSTISTPLUGIN_FILE;
    protected $_url = 'https://www.trustistecommerce.com';
    protected $_title = 'TrustistEcommerce for GravityForms';
    protected $_short_title = 'TrustistEcommerce';
    protected $_supports_callbacks = true;
    protected $_capabilities = ['gravityforms_trustist', 'gravityforms_trustist_uninstall'];
    protected $_capabilities_settings_page = 'gravityforms_trustist';
    protected $_capabilities_form_settings = 'gravityforms_trustist';
    protected $_capabilities_uninstall = 'gravityforms_trustist_uninstall';
    protected $_enable_rg_autoupgrade = false;
    private static $_instance = null;

    public function get_path()
    {
        return TRUSTISTPLUGIN_HOOK;
    }

    public function pre_init()
    {
        // For form confirmation redirection, this must be called in `wp`,
        // or confirmation redirect to a page would throw PHP fatal error.
        // Run before calling parent method. We don't want to run anything else before displaying thank you page.
        add_action('wp', array($this, 'maybe_thankyou_page'), 5);

        parent::pre_init();
    }

    public static function get_instance()
    {
        if (null == self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function note_avatar()
    {
        return TRUSTISTPLUGIN_URL . 'img/Trustist-star-icon-150x150.png';
    }

    /* ADMIN FUNCTIONS */
    public function init_admin()
    {
        parent::init_admin();

        add_action('gform_payment_status', [$this, 'admin_edit_payment_status'], 3, 3);
        add_action('gform_payment_date', [$this, 'admin_edit_payment_date'], 3, 3);
        add_action('gform_payment_transaction_id', [$this, 'admin_edit_payment_transaction_id'], 3, 3);
        add_action('gform_payment_amount', [$this, 'admin_edit_payment_amount'], 3, 3);
        add_action('gform_after_update_entry', [$this, 'admin_update_payment'], 4, 2);
    }

    public function admin_edit_payment_status($payment_status, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $payment_status;
        }

        $payment_string = gform_tooltip('trustist_edit_payment_status', '', true);
        $payment_string .= '<select id="payment_status" name="payment_status">';
        $payment_string .= '<option value="' . $payment_status . '" selected>' . $payment_status . '</option>';
        $payment_string .= '<option value="Paid">Paid</option>';
        $payment_string .= '</select>';

        return $payment_string;
    }

    public function admin_edit_payment_date($payment_date, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $payment_date;
        }

        $payment_date = $entry['payment_date'];
        if (empty($payment_date)) {
            $payment_date = get_the_date('y-m-d H:i:s');
        }

        $input = '<input type="text" id="payment_date" name="payment_date" value="' . $payment_date . '">';

        return $input;
    }

    public function admin_edit_payment_transaction_id($transaction_id, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $transaction_id;
        }

        $input = '<input type="text" id="trustist_transaction_id" name="trustist_transaction_id" value="' . $transaction_id . '">';

        return $input;
    }

    public function admin_edit_payment_amount($payment_amount, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $payment_amount;
        }

        if (empty($payment_amount)) {
            $payment_amount = GFCommon::get_order_total($form, $entry);
        }

        $input = '<input type="text" id="payment_amount" name="payment_amount" class="gform_currency" value="' . $payment_amount . '">';

        return $input;
    }

    public function admin_update_payment($form, $entry_id)
    {
        check_admin_referer('gforms_save_entry', 'gforms_save_entry');

        $entry = GFFormsModel::get_lead($entry_id);
        if ($this->payment_details_editing_disabled($entry, 'update')) {
            return;
        }

        $payment_status = rgpost('payment_status');
        if (empty($payment_status)) {
            $payment_status = $entry['payment_status'];
        }

        $payment_amount = GFCommon::to_number(rgpost('payment_amount'));
        $payment_transaction = rgpost('trustist_transaction_id');
        $payment_date = rgpost('payment_date');

        $status_unchanged = $entry['payment_status'] == $payment_status;
        $amount_unchanged = $entry['payment_amount'] == $payment_amount;
        $id_unchanged = $entry['transaction_id'] == $payment_transaction;
        $date_unchanged = $entry['payment_date'] == $payment_date;

        if ($status_unchanged && $amount_unchanged && $id_unchanged && $date_unchanged) {
            return;
        }

        if (empty($payment_date)) {
            $payment_date = get_the_date('y-m-d H:i:s');
        } else {
            $payment_date = gmdate('Y-m-d H:i:s', strtotime($payment_date));
        }

        global $current_user;
        $user_id = 0;
        $user_name = 'Trustist';
        if ($current_user && $user_data = get_userdata($current_user->ID)) {
            $user_id = $current_user->ID;
            $user_name = $user_data->display_name;
        }

        $entry['payment_status'] = $payment_status;
        $entry['payment_amount'] = $payment_amount;
        $entry['payment_date'] = $payment_date;
        $entry['transaction_id'] = $payment_transaction;

        if (('Paid' === $payment_status || 'Approved' === $payment_status) && !$entry['is_fulfilled']) {
            $action['id'] = $payment_transaction;
            $action['type'] = 'complete_payment';
            $action['transaction_id'] = $payment_transaction;
            $action['amount'] = $payment_amount;
            $action['entry_id'] = $entry['id'];

            $this->complete_payment($entry, $action);
            $this->fulfill_order($entry, $payment_transaction, $payment_amount);
        }

        GFAPI::update_entry($entry);		
        GFFormsModel::add_note( $entry['id'], $user_id, $user_name, sprintf( 
            esc_html__( 'Payment information was manually updated. Status: %s. Amount: %s. Transaction ID: %s. Date: %s', 'gravityformspaypal' ), 
            $entry['payment_status'], 
            GFCommon::to_money( $entry['payment_amount'], $entry['currency'] ), 
            $payment_transaction, 
            $entry['payment_date'] ) 
        );
    }

    public function payment_details_editing_disabled($entry, $action = 'edit')
    {
        if (!$this->is_payment_gateway($entry['id'])) {
            return true;
        }

        $payment_status = rgar($entry, 'payment_status');
        if ('Approved' === $payment_status || 'Paid' === $payment_status || 2 === rgar($entry, 'transaction_type')) {
            return true;
        }

        if ('edit' === $action && 'edit' === rgpost('screen_mode')) {
            return false;
        }

        if ('update' === $action && 'view' === rgpost('screen_mode') && 'update' === rgpost('action')) {
            return false;
        }

        return true;
    }

    public function fulfill_order(&$entry, $transaction_id, $amount, $feed = null)
    {
        if (!$feed) {
            $feed = $this->get_payment_feed($entry);
        }

        $form = GFFormsModel::get_form_meta($entry['form_id']);

        if (rgars($feed, 'meta/delayPost')) {
            $this->log_debug(__METHOD__ . '(): Creating post.');
            $entry['post_id'] = GFFormsModel::create_post($form, $entry);
            $this->log_debug(__METHOD__ . '(): Post created.');
        }

        if (rgars($feed, 'meta/delayNotification')) {
            $notifications = $this->get_notifications_to_send($form, $feed);
            GFCommon::send_notifications($notifications, $form, $entry, true, 'form_submission');
        }

        do_action('gform_trustist_fulfillment', $entry, $feed, $transaction_id, $amount);
        if (has_filter('gform_trustist_fulfillment')) {
            $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_trustist_fulfillment.');
        }

        $this->log_debug(__METHOD__ . "(): Order fulfilled. ");
    }
    /* END ADMIN FUNCTIONS */

    /* FEED SETTINGS */
    public function feed_settings_fields()
    {
        $default_settings = parent::feed_settings_fields();

        $fields = [
            [
                'name' => 'test_mode',
                'label' => esc_html__('Test Mode', 'trustistgfm'),
                'type' => 'checkbox',
                'required' => false,
                'choices' => [
                    [
                        'label' => esc_html__('Enable Test mode', 'trustistgfm'),
                        'name' => 'test_mode',
                    ],
                ],
                'tooltip' => '<h6>' . esc_html__('Test Mode', 'trustistgfm') . '</h6>' . esc_html__('Enable this option to test using the sandbox.', 'trustistgfm'),
            ],
        ];

        $default_settings = parent::add_field_after('feedName', $fields, $default_settings);

        $fields = [
            [
                'name' => 'cancel_url',
                'label' => esc_html__('Cancel URL', 'trustistgfm'),
                'type' => 'text',
                'class' => 'medium',
                'required' => false,
                'tooltip' => '<h6>' . esc_html__('Cancel URL', 'trustistgfm') . '</h6>' . esc_html__('Return to this URL if payment failed. Leave blank for default.', 'trustistgfm'),
            ],
        ];

        // if ($this->get_setting('delayNotification') || !$this->is_gravityforms_supported('1.9.12')) {
        //     $fields[] = [
        //         'name' => 'notifications',
        //         'label' => esc_html__('Notifications', 'trustistgfm'),
        //         'type' => 'notifications',
        //         'tooltip' => '<h6>' . esc_html__('Notifications', 'trustistgfm') . '</h6>' . esc_html__("Enable this option if you would like to only send out this form's notifications for the 'Form is submitted' event after payment has been received. Leaving this option disabled will send these notifications immediately after the form is submitted. Notifications which are configured for other events will not be affected by this option.", 'trustistgfm'),
        //     ];
        // }

        $form = $this->get_current_form();
        // if (GFCommon::has_post_field($form['fields'])) {
        //     $post_settings = [
        //         'name' => 'post_checkboxes',
        //         'label' => esc_html__('Posts', 'trustistgfm'),
        //         'type' => 'checkbox',
        //         'tooltip' => '<h6>' . esc_html__('Posts', 'trustistgfm') . '</h6>' . esc_html__('Enable this option if you would like to only create the post after payment has been received.', 'trustistgfm'),
        //         'choices' => [
        //             [
        //                 'label' => esc_html__('Create post only when payment is received.', 'trustistgfm'),
        //                 'name' => 'delayPost',
        //             ],
        //         ],
        //     ];

        //     $fields[] = $post_settings;
        // }

        // // gform_trustist_add_option_group
        // $fields[] = [
        //     'name' => 'custom_options',
        //     'label' => '',
        //     'type' => 'custom',
        // ];

        // $default_settings = $this->add_field_after('billingInformation', $fields, $default_settings);
        $billing_info = parent::get_field('billingInformation', $default_settings);
        $dt = $billing_info['field_map'];

        foreach ($dt as $n => $k) {
            switch ($k['name']) {
                case 'name':
                case 'mobile':
                case 'address':
                case 'address2':
                case 'city':
                case 'state':
                case 'zip':
                case 'country':
                case 'email':
                    unset($billing_info['field_map'][$n]);
                    break;
            }
        }
        unset($dt);

        array_unshift(
            $billing_info['field_map'],
            [
                'name' => 'name',
                'label' => esc_html__('Name', 'trustistgfm'),
                'required' => false,
            ],
            [
                'name' => 'email',
                'label' => esc_html__('Email', 'trustistgfm'),
                'required' => false,
            ],
        );

        $default_settings = parent::replace_field('billingInformation', $billing_info, $default_settings);

        // remove unsupported billing fields
        $default_settings = parent::remove_field('setupFee', $default_settings);
        $default_settings = parent::remove_field('trial', $default_settings);

        $dt = $default_settings[3]['fields'];
        foreach ($dt as $n => $arr) {
            if ($n > 0) {
                if (!\in_array($default_settings[3]['fields'][$n]['name'], ['cancel_url', 'conditionalLogic'])) {
                    unset($default_settings[3]['fields'][$n]);
                }
            }
        }

        return apply_filters('gform_trustist_feed_settings_fields', $default_settings, $form);
    }


    public function field_map_title()
    {
        return esc_html__('Trustist Field', 'trustistgfm');
    }

    public function settings_options($field, $echo = true)
    {
        $html = $this->settings_checkbox($field, false);

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    public function settings_custom($field, $echo = true)
    {
        ob_start(); ?>
        <div id='gf_trustist_custom_settings'>
            <?php
            do_action('gform_trustist_add_option_group', $this->get_current_feed(), $this->get_current_form()); ?>
        </div>

        <script type='text/javascript'>
            jQuery(document).ready(function() {
                jQuery('#gf_trustist_custom_settings label.left_header').css('margin-left', '-200px');
            });
        </script>

        <?php

        $html = ob_get_clean();

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    public function settings_notifications($field, $echo = true)
    {
        $checkboxes = [
            'name' => 'delay_notification',
            'type' => 'checkboxes',
            'onclick' => 'ToggleNotifications();',
            'choices' => [
                [
                    'label' => esc_html__("Send notifications for the 'Form is submitted' event only when payment is received.", 'trustistgfm'),
                    'name' => 'delayNotification',
                ],
            ],
        ];

        $html = $this->settings_checkbox($checkboxes, false);

        $html .= $this->settings_hidden(
            [
                'name' => 'selectedNotifications',
                'id' => 'selectedNotifications',
            ],
            false
        );

        $form = $this->get_current_form();
        $has_delayed_notifications = $this->get_setting('delayNotification');
        ob_start(); ?>
        <ul id="gf_trustist_notification_container" style="padding-left:20px; margin-top:10px; <?php echo $has_delayed_notifications ? '' : 'display:none;'; ?>">
            <?php
            if (!empty($form) && \is_array($form['notifications'])) {
                $selected_notifications = $this->get_setting('selectedNotifications');
                if (!\is_array($selected_notifications)) {
                    $selected_notifications = [];
                }

                $notifications = GFCommon::get_notifications('form_submission', $form);

                foreach ($notifications as $notification) {
            ?>
                    <li class="gf_trustist_notification">
                        <input type="checkbox" class="notification_checkbox" value="<?php echo $notification['id']; ?>" onclick="SaveNotifications();" <?php checked(true, \in_array($notification['id'], $selected_notifications)); ?> />
                        <label class="inline" for="gf_trustist_selected_notifications"><?php echo $notification['name']; ?></label>
                    </li>
            <?php
                }
            } ?>
        </ul>
        <script type='text/javascript'>
            function SaveNotifications() {
                var notifications = [];
                jQuery('.notification_checkbox').each(function() {
                    if (jQuery(this).is(':checked')) {
                        notifications.push(jQuery(this).val());
                    }
                });
                jQuery('#selectedNotifications').val(jQuery.toJSON(notifications));
            }

            function ToggleNotifications() {

                var container = jQuery('#gf_trustist_notification_container');
                var isChecked = jQuery('#delaynotification').is(':checked');

                if (isChecked) {
                    container.slideDown();
                    jQuery('.gf_trustist_notification input').prop('checked', true);
                } else {
                    container.slideUp();
                    jQuery('.gf_trustist_notification input').prop('checked', false);
                }

                SaveNotifications();
            }
        </script>
<?php

        $html .= ob_get_clean();

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    public function checkbox_input_change_post_status($choice, $attributes, $value, $tooltip)
    {
        $markup = $this->checkbox_input($choice, $attributes, $value, $tooltip);

        $dropdown_field = [
            'name' => 'update_post_action',
            'choices' => [
                ['label' => ''],
                [
                    'label' => esc_html__('Mark Post as Draft', 'trustistgfm'),
                    'value' => 'draft',
                ],
                [
                    'label' => esc_html__('Delete Post', 'trustistgfm'),
                    'value' => 'delete',
                ],
            ],
            'onChange' => "var checked = jQuery(this).val() ? 'checked' : false; jQuery('#change_post_status').attr('checked', checked);",
        ];
        $markup .= '&nbsp;&nbsp;' . $this->settings_select($dropdown_field, false);

        return $markup;
    }

    public function option_choices()
    {
        return false;
    }

    public function save_feed_settings($feed_id, $form_id, $settings)
    {
        $feed = $this->get_feed($feed_id);
        $settings['type'] = $settings['transactionType'];

        $feed['meta'] = $settings;
        $feed = apply_filters('gform_trustist_save_config', $feed);

        if (!empty($feed['meta']['test_mode']) && 1 === (int) $feed['meta']['test_mode']) {
            unset($feed['meta']['sandbox_mode']);
        }

        $is_validation_error = apply_filters('gform_trustist_config_validation', false, $feed);
        if ($is_validation_error) {
            return false;
        }

        $settings = $feed['meta'];

        return parent::save_feed_settings($feed_id, $form_id, $settings);
    }
    /* END FEED SETTINGS */

    /* ENTRY CREATION */
    public function init_frontend()
    {
        parent::init_frontend();

        add_filter('gform_disable_post_creation', [$this, 'frontend_disable_post_creation'], 10, 3);
        add_filter('gform_disable_notification', [$this, 'frontend_disable_notification'], 10, 4);
        // add_filter('gform_submit_button', [$this, 'frontend_submit_button'], 10, 2);

        add_filter(
            'gform_form_args',
            function ($args) {
                $args['ajax'] = false;

                return $args;
            },
            \PHP_INT_MAX
        );
    }

    public function frontend_disable_post_creation($is_disabled, $form, $entry)
    {
        $feed = $this->get_payment_feed($entry);
        $submission_data = $this->get_submission_data($feed, $form, $entry);

        if (!$feed || empty($submission_data['payment_amount'])) {
            return $is_disabled;
        }

        return !rgempty('delayPost', $feed['meta']);
    }

    public function frontend_disable_notification($is_disabled, $notification, $form, $entry)
    {
        if ('form_submission' != rgar($notification, 'event')) {
            return $is_disabled;
        }

        $feed = $this->get_payment_feed($entry);
        $submission_data = $this->get_submission_data($feed, $form, $entry);

        if (!$feed || empty($submission_data['payment_amount'])) {
            return $is_disabled;
        }

        $selected_notifications = \is_array(rgar($feed['meta'], 'selectedNotifications')) ? rgar($feed['meta'], 'selectedNotifications') : [];

        return isset($feed['meta']['delayNotification']) && \in_array($notification['id'], $selected_notifications) ? true : $is_disabled;
    }

    // public function frontend_submit_button($button, $form)
    // {
    //     $html = '';

    //     $title = !empty($feed_meta['payment_header']) ? $feed_meta['payment_header'] : esc_html__('Pay with Trustist', 'trustistgfm');
    //     $html = '<div class="gform_body spgfmbody">';
    //     $html .= '<label class="gfield_label" for="buyer_bank_code">' . $title . '</label>';

    //     $html .= '</div>';

    //     return $html . $button;
    // }

    // Override this method to specify a URL to the third party payment processor
    public function redirect_url($feed, $submission_data, $form, $entry)
    {
        // Don't process redirect url if request is a return
        if (!rgempty('gf_paystack_return', $_GET)) {
            return false;
        }

        $this->log_debug(__METHOD__ . '(): Submission data => ' . print_r($submission_data, true));

        GFAPI::update_entry_property($entry['id'], 'payment_status', 'Processing');

        $feed_meta = $feed['meta'];
        $is_testmode = !empty($feed_meta['test_mode']) && 1 === (int) $feed_meta['test_mode'] ? true : false;

        $redirect_url = $this->return_url($form['id'], $entry['id']);
        $cancel_url = !empty($feed_meta['cancel_url']) && $feed_meta['cancel_url'] ? $feed_meta['cancel_url'] : $redirect_url;

        $orderid = $entry['id'];
        $total = (string) rgar($submission_data, 'payment_amount');
        $line_items = rgar($submission_data, 'line_items');
        $discounts = rgar($submission_data, 'discounts');
        $item_name = $this->get_item_name($line_items, $discounts);

        $int_name = isset($feed_meta['billingInformation_name']) ? $feed_meta['billingInformation_name'] : '';
        $int_email = isset($feed_meta['billingInformation_email']) ? $feed_meta['billingInformation_email'] : '';
        $buyer_email = isset($entry[$int_email]) ? $entry[$int_email] : '';
        $buyer_name = $this->extractAndConcatenate($entry, $int_name);

        $this->log_debug(__METHOD__ . '(): Entry is being converted => ' . print_r($entry, true));
        $this->log_debug(__METHOD__ . '(): Feed being used => ' . print_r($feed, true));

        // create the payment        
        try {
            switch ($feed['meta']['transactionType']) {
                case 'product':
                    $paymentRequest = new PaymentRequest($total, $orderid, $item_name, $buyer_name, $buyer_email, $redirect_url, $cancel_url);

                    $this->log_debug(__METHOD__ . '(): Payment request => ' . print_r($paymentRequest, true));

                    $payment = trustist_payment_create_payment($paymentRequest, $is_testmode);
                    break;

                case 'subscription':
                    $payment = $this->create_trustist_subscription(
                        $feed,
                        $submission_data,
                        $entry['id'],
                        $item_name,
                        $buyer_name,
                        $buyer_email,
                        $redirect_url,
                        $cancel_url,
                        $is_testmode
                    );
                    break;
            }

            $this->log_debug(__METHOD__ . "(): Payment created. " . print_r($payment, 1));
        } catch (\Exception $e) {
            $this->log_error(__METHOD__ . "(): Payment could not be created. Reason: " . $e->getMessage());

            return false;
        }

        // if the payment was not created log an error and return false
        if (empty($payment)) {
            $this->log_error(__METHOD__ . '(): Unable to create payment for entry ' . $entry['id']);
            return false;
        }

        // persist the payment ID against the entry        
        gform_update_meta($entry['id'], 'trustist_payment_id', $payment['id']);

        return $payment['payLink'];
    }

    function extractAndConcatenate($array, $key_prefix = '')
    {
        $result = '';

        foreach ($array as $key => $value) {
            // Check if the key starts with '7.' or is exactly '7'
            if (strpos($key, $key_prefix . '.') === 0 || $key === $key_prefix) {
                // Concatenate the values separated by a space
                if (!empty($value)) { // Only add non-empty values
                    $result .= $value . ' ';
                }
            }
        }

        // Trim the final string to remove the last space
        return trim($result);
    }

    private function return_url($form_id, $entry_id)
    {
        $pageURL = GFCommon::is_ssl() ? 'https://' : 'http://';

        $server_port = apply_filters('gform_trustist_return_url_port', $_SERVER['SERVER_PORT']);

        if ($server_port != '80') {
            $pageURL .= $_SERVER['SERVER_NAME'] . ':' . $server_port . $_SERVER['REQUEST_URI'];
        } else {
            $pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
        }

        $ids_query = "ids={$form_id}|{$entry_id}";
        $ids_query .= '&hash=' . wp_hash($ids_query);

        $url = add_query_arg('gf_tr_return', base64_encode($ids_query), $pageURL);

        $query = 'gf_tr_return=' . base64_encode($ids_query);

        return apply_filters('gform_trustist_return_url', $url, $form_id, $entry_id, $query);
    }

    public function get_payment_feed($entry, $form = false)
    {
        $feed = parent::get_payment_feed($entry, $form);
        $fid = isset($entry['form_id']) ? GFAPI::get_form($entry['form_id']) : '';
        $feed = apply_filters('gform_trustist_get_payment_feed', $feed, $entry, $form ?: $fid);

        return $feed;
    }

    public function supported_notification_events($form)
    {
        if (!$this->has_feed($form['id'])) {
            return false;
        }

        return [
            'complete_payment' => esc_html__('Payment Completed', 'trustistgfm'),
            'fail_payment' => esc_html__('Payment Failed', 'trustistgfm'),
            'create_subscription'       => esc_html__('Subscription Created', 'trustistgfm'),
            'cancel_subscription'       => esc_html__('Subscription Canceled', 'trustistgfm'),
            'add_subscription_payment'  => esc_html__('Subscription Payment Added', 'trustistgfm'),
            'fail_subscription_payment' => esc_html__('Subscription Payment Failed', 'trustistgfm'),
        ];
    }

    private static function get_item_name($line_items, $discounts)
    {
        $item_name = '';
        $name_without_options = '';

        //work on products
        if (is_array($line_items)) {
            foreach ($line_items as $item) {
                $product_id     = $item['id'];
                $product_name   = $item['name'];
                $quantity       = $item['quantity'];
                $quantity_label = $quantity > 1 ? $quantity . ' ' : '';

                $unit_price  = $item['unit_price'];
                $options     = rgar($item, 'options');
                $product_id  = $item['id'];
                $is_shipping = rgar($item, 'is_shipping');

                $product_options = '';
                if (!$is_shipping) {
                    //add options

                    if (!empty($options) && is_array($options)) {
                        $product_options = ' (';
                        foreach ($options as $option) {
                            $product_options .= $option['option_name'] . ', ';
                        }
                        $product_options = substr($product_options, 0, strlen($product_options) - 2) . ')';
                    }

                    $item_name .= $quantity_label . $product_name . $product_options . ', ';
                    $name_without_options .= $product_name . ', ';
                }
            }

            //look for discounts to pass in the item_name
            if (is_array($discounts)) {
                foreach ($discounts as $discount) {
                    $product_name   = $discount['name'];
                    $quantity       = $discount['quantity'];
                    $quantity_label = $quantity > 1 ? $quantity . ' ' : '';
                    $item_name .= $quantity_label . $product_name . ' (), ';
                    $name_without_options .= $product_name . ', ';
                }
            }

            if (!empty($item_name)) {
                $item_name = substr($item_name, 0, strlen($item_name) - 2);
            }

            //if name is larger than max, remove options from it.
            if (strlen($item_name) > 127) {
                $item_name = substr($name_without_options, 0, strlen($name_without_options) - 2);

                //truncating name to maximum allowed size
                if (strlen($item_name) > 127) {
                    $item_name = substr($item_name, 0, 124) . '...';
                }
            }

            $item_name = urlencode($item_name);
        }

        return $item_name;
    }
    /* END ENTRY CREATION */

    /* SUBSCRIPTIONS */
    public function supported_billing_intervals()
    {
        return array(
            'weekly'   => array('label' => esc_html__('Weekly', 'trustistgfm'), 'min' => 1, 'max' => 1),
            'monthly'  => array('label' => esc_html__('Monthly', 'trustistgfm'), 'min' => 1, 'max' => 1),
            'annually'   => array('label' => esc_html__('Annually', 'trustistgfm'), 'min' => 1, 'max' => 1),
        );
    }

    public function create_trustist_subscription(
        $feed,
        $submission_data,
        $entry_id,
        $item_name,
        $buyer_name,
        $buyer_email,
        $return_url,
        $cancel_url = null,
        $test = false
    ) {
        if (empty($submission_data)) {
            return false;
        }

        //$query_string         = '';
        $payment_amount       = rgar($submission_data, 'payment_amount');
        //$setup_fee            = rgar($submission_data, 'setup_fee');
        $trial_enabled        = rgar($feed['meta'], 'trial_enabled');
        // $recurring_field      = rgar($submission_data, 'payment_amount'); //will be field id or the text 'form_total'
        // $product_index        = 1;
        // $shipping             = '';
        // $discount_amt         = 0;
        // $cmd                  = '_xclick-subscriptions';
        // $extra_qs             = '';
        $name_without_options = '';

        $trial = '';
        //see if a trial exists
        if ($trial_enabled) {
            $trial_amount        = rgar($submission_data, 'trial') ? rgar($submission_data, 'trial') : 0;
            $trial_period_number = rgar($feed['meta'], 'trialPeriod_length');
            $trial_period_type   = rgar($feed['meta'], 'trialPeriod_unit');
        }

        //check for recurring times
        $recurring_times = rgar($feed['meta'], 'recurringTimes') ? rgar($feed['meta'], 'recurringTimes') : '';

        //$billing_cycle_number = rgar($feed['meta'], 'billingCycle_length');
        $billing_cycle_type   = rgar($feed['meta'], 'billingCycle_unit');

        //save payment amount to lead meta
        gform_update_meta($entry_id, 'payment_amount', $payment_amount);

        $standingOrderRequest = new StandingOrderRequest(
            $payment_amount,
            $entry_id,
            $item_name,
            $billing_cycle_type,
            gmdate('Y-m-d'),
            $recurring_times,
            $buyer_name,
            null,
            $return_url,
            $cancel_url
        );

        $this->log_debug(__METHOD__ . '(): Standing order request => ' . print_r($standingOrderRequest, true));

        // create the subscription
        $payment = trustist_payment_create_subscription($standingOrderRequest, $test);

        return $payment_amount > 0 ? $payment : null;
    }
    /* END SUBSCRIPTIONS */

    /* RETURN FROM TRUSTIST PAYMENT PAGES */
    public function maybe_thankyou_page()
    {
        if (!$this->is_gravityforms_supported()) {
            return;
        }

        if ($str = rgget('gf_tr_return')) {
            $str = base64_decode($str);

            parse_str($str, $query);
            if (wp_hash('ids=' . $query['ids']) == $query['hash']) {
                list($form_id, $entry_id) = explode('|', $query['ids']);

                $form = GFAPI::get_form($form_id);
                $entry = GFAPI::get_entry($entry_id);

                if (is_wp_error($entry) || !$entry) {
                    $this->log_error(__METHOD__ . '(): Entry could not be found. Aborting.');

                    return false;
                }

                $this->log_debug(__METHOD__ . '(): Entry has been found => ' . print_r($entry, true));

                if ($entry['payment_status'] !== 'Processing') {
                    $this->log_error(__METHOD__ . '(): Entry is already processed. Aborting.');

                    $this->handle_confirmation($form, $entry);

                    return false;
                }

                $feed = $this->get_payment_feed($entry);

                $payment_id = gform_get_meta($entry_id, 'trustist_payment_id');

                // get the payment details from the Trustist server
                try {
                    $is_testmode = !empty($feed['meta']['test_mode']) && 1 === (int) $feed['meta']['test_mode'] ? true : false;

                    switch ($feed['meta']['transactionType']) {
                        case 'product':
                            $payment = trustist_payment_get_payment($payment_id, $is_testmode);
                            $paymentComplete = $payment && $payment['status'] === 'COMPLETE';
                            break;

                        case 'subscription':
                            $payment = trustist_payment_get_subscription($payment_id, $is_testmode);
                            $paymentComplete = $payment && $payment['status'] === 'ACTIVE';
                            break;
                    }

                    $this->log_debug(__METHOD__ . "(): Transaction verified. " . print_r($payment, 1));
                } catch (\Exception $e) {
                    $this->log_error(__METHOD__ . "(): Transaction could not be verified. Reason: " . $e->getMessage());

                    return new WP_Error('transaction_verification', $e->getMessage());
                }

                // update the entry with the details of the payment
                if (!$paymentComplete) {
                    // Charge Failed
                    $this->log_error(__METHOD__ . "(): Transaction verification failed Reason: " . $payment->message);

                    GFAPI::update_entry_property($entry['id'], 'payment_status', 'Failed');

                    $note = "Trustist payment failed\n";
                    $note .= 'Transaction ID: ' . $payment['id'] . "\n";

                    if (!empty($retry_link)) {
                        $note .= 'Retry link: ' . $retry_link . "\n";
                    }

                    if (!empty($status_link)) {
                        $note .= 'Status link: ' . $status_link . "\n";
                    }

                    $this->add_note($entry['id'], $note, 'error');

                    return false;
                }

                // payment success
                GFAPI::update_entry_property($entry['id'], 'payment_status', 'Paid');

                $note = "Trustist payment successful\n";
                $note .= 'Transaction ID: ' . $payment['id'] . "\n";

                if (!empty($receipt_link)) {
                    $note .= 'Receipt link: ' . $receipt_link . "\n";
                }

                if (!empty($status_link)) {
                    $note .= 'Status link: ' . $status_link . "\n";
                }

                $this->add_note($entry['id'], $note);

                // $this->fulfill_order($entry, $payment['id'], $payment['amount']);

                if (!class_exists('GFFormDisplay')) {
                    require_once(GFCommon::get_base_path() . '/form_display.php');
                }

                // handle the confirmation
                $this->handle_confirmation($form, $entry);
            }
        }
    }

    private function handle_confirmation($form, $entry)
    {
        $confirmation = GFFormDisplay::handle_confirmation($form, $entry, false);

        $this->log_debug(__METHOD__ . "(): Confirmation created. " . print_r($confirmation, 1));

        if (is_array($confirmation) && isset($confirmation['redirect'])) {
            header("Location: {$confirmation['redirect']}");
            exit;
        }

        GFFormDisplay::$submission[$form['id']] = array('is_confirmation' => true, 'confirmation_message' => $confirmation, 'form' => $form, 'lead' => $entry);
    }
    /* END OF RETURN FROM TRUSTIST PAYMENT PAGES */



    // this function is intended for processing webhook messages
    // it returns an object with the transaction details back to the core payment processor
    // public function callback()
    // {
    //     if (!$this->is_gravityforms_supported()) {
    //         return false;
    //     }

    //     $entry = GFAPI::get_entry(rgget('entry_id'));

    //     if (is_wp_error($entry)) {
    //         $this->log_error(__METHOD__ . '(): Entry could not be found. Aborting.');

    //         return false;
    //     }

    //     if ('spam' === rgar($entry, 'status')) {
    //         $this->log_error(__METHOD__ . '(): Entry is marked as spam. Aborting.');

    //         return false;
    //     }

    //     $feed = $this->get_payment_feed($entry);

    //     if (!$feed || !rgar($feed, 'is_active')) {
    //         $this->log_error(__METHOD__ . "(): Form is no longer configured with Trustist. Form ID: {$entry['form_id']}. Aborting.");

    //         return false;
    //     }

    //     // payment status names may be different
    //     $is_paid = 'Paid' === rgar($entry, 'payment_status') || 'Approved' === rgar($entry, 'payment_status');

    //     $response_params = $this->sanitize_post();

    //     if (!empty($response_params) && isset($response_params['tr-status'])) {
    //         $success = $this->response_status($response_params);

    //         $is_callback = $this->is_response_callback($response_params);
    //         $callback = $is_callback ? 'Callback' : 'Redirect';
    //         $note = '';
    //         $retn = false;

    //         $receipt_link = !empty($response_params['receipt_url']) ? $response_params['receipt_url'] : '';
    //         $status_link = !empty($response_params['status_url']) ? $response_params['status_url'] : '';
    //         $retry_link = !empty($response_params['retry_url']) ? $response_params['retry_url'] : '';

    //         if ($success) {
    //             $note = "Trustist payment successful\n";
    //             $note .= 'Response from: ' . $callback . "\n";
    //             $note .= 'Transaction ID: ' . $response_params['tr-payment-id'] . "\n";

    //             if (!empty($receipt_link)) {
    //                 $note .= 'Receipt link: ' . $receipt_link . "\n";
    //             }

    //             if (!empty($status_link)) {
    //                 $note .= 'Status link: ' . $status_link . "\n";
    //             }

    //             if ($is_paid) {
    //                 if (!$is_callback) {
    //                     $this->add_note($response_params['tr-payment-id'], $note);
    //                     $this->redirect_spcallback();
    //                     exit;
    //                 }

    //                 echo sprintf(esc_html__('This webhook has already been processed (Event Id: %s)', 'gravityforms'), $response_params['tr-payment-id']);
    //                 exit;
    //             }

    //             return [
    //                 'id' => $response_params['tr-payment-id'],
    //                 'transaction_id' => $response_params['tr-payment-id'],
    //                 'amount' => (string) $response_params['tr-amount'],
    //                 'entry_id' => $entry['id'],
    //                 'payment_date' => get_the_date('y-m-d H:i:s'),
    //                 'type' => 'complete_payment',
    //                 'payment_method' => 'trustist',
    //                 'ready_to_fulfill' => !$entry['is_fulfilled'] ? true : false,
    //                 'note' => $note,
    //             ];
    //         }

    //         $note = "Trustist payment failed\n";
    //         $note .= 'Response from: ' . $callback . "\n";
    //         $note .= 'Transaction ID: ' . $response_params['tr-payment-id'] . "\n";

    //         if (!empty($retry_link)) {
    //             $note .= 'Retry link: ' . $retry_link . "\n";
    //         }

    //         if (!empty($status_link)) {
    //             $note .= 'Status link: ' . $status_link . "\n";
    //         }

    //         $this->add_note($entry['id'], $note, 'error');

    //         return true;
    //     }

    //     return false;
    // }

    // // this function operates on the results of the callback function
    // public function post_callback($callback_action, $callback_result)
    // {
    //     if (is_wp_error($callback_action) || !$callback_action) {
    //         return false;
    //     }

    //     $this->log_debug(__METHOD__ . '(): Executing post_callback.');

    //     $entry = GFAPI::get_entry($callback_action['entry_id']);
    //     $feed = $this->get_payment_feed($entry);
    //     $transaction_id = rgar($callback_action, 'transaction_id'); // may be a different field name
    //     $amount = rgar($callback_action, 'amount');

    //     $this->fulfill_order($entry, $transaction_id, $amount, $feed);

    //     do_action('gform_trustist_post_payment_status', $feed, $entry, $transaction_id, $amount);

    //     if (has_filter('gform_trustist_post_payment_status')) {
    //         $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_trustist_post_payment_status.');
    //     }
    // }

    // public function is_callback_valid()
    // {
    //     if ('gf_trustist' !== rgget('page')) {
    //         return false;
    //     }

    //     return true;
    // }

    // private function is_response_callback($response_params)
    // {
    //     if (isset($response_params['tr-status'])) {
    //         return true;
    //     }

    //     return false;
    // }

    // private function response_status($response_params)
    // {
    //     if (isset($response_params['tr-status']) && 'COMPLETE' === $response_params['tr-status']) {
    //         return true;
    //     }

    //     return false;
    // }

    // private function sanitize_post()
    // {
    //     $params = [
    //         'amount',
    //         'id',
    //         'status',
    //         'reference',
    //         'page',
    //         'entry_id',
    //         'spref',
    //         'sprec',
    //         'tr-payment-id',
    //         'tr-status',
    //         'tr-amount',
    //     ];

    //     $response_params = [];
    //     if (isset($_POST)) {
    //         foreach ($params as $k) {
    //             if (isset($_POST[$k])) {
    //                 $response_params[$k] = sanitize_text_field($_POST[$k]);
    //             }
    //         }
    //     }

    //     if (isset($_GET)) {
    //         foreach ($params as $k) {
    //             if (isset($_GET[$k])) {
    //                 $response_params[$k] = sanitize_text_field($_GET[$k]);
    //             }
    //         }
    //     }

    //     return $response_params;
    // }

    // private function redirect_spcallback_js($url)
    // {
    //     if (!headers_sent()) {
    //         wp_redirect($url);
    //         exit;
    //     }

    //     $html = "<script>window.location.replace('" . $url . "');</script>";
    //     $html .= '<noscript><meta http-equiv="refresh" content="1; url=' . $url . '">Redirecting..</noscript>';

    //     echo wp_kses(
    //         $html,
    //         [
    //             'script' => [],
    //             'noscript' => [],
    //             'meta' => [
    //                 'http-equiv' => [],
    //                 'content' => [],
    //             ],
    //         ]
    //     );
    //     exit;
    // }

    // private function redirect_spcallback($is_cancel = false)
    // {
    //     $ru = $is_cancel ? rgget('sprec') : rgget('spref');
    //     $url = !empty($ru) ? $ru : get_home_url();

    //     if (!preg_match('@^(https?|//):@', $url)) {
    //         $url = base64_decode($url);
    //     }

    //     if (!preg_match('@^(https?|//):@', $url)) {
    //         $url = site_url($url);
    //     }

    //     if (false !== strpos($url, 'spref')) {
    //         $url = str_replace('spref', '_spref', $url);
    //     }

    //     if (false !== strpos($url, 'sprec')) {
    //         $url = str_replace('sprec', '_sprec', $url);
    //     }

    //     $this->redirect_spcallback_js($url);
    // }

    public function uninstall()
    {
        parent::uninstall();
    }
}

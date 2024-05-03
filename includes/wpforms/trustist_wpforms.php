<?php

class WPForms_Trustist_Payment extends WPForms_Payment
{
    public function init()
    {
        $this->version = '1.0';
        $this->name = 'Trustist Payment';
        $this->slug = 'trustist_payment';
        $this->priority = 10;
        $this->icon = plugin_dir_url(__FILE__).'includes/admin/addon-icon-trustist.png';

        add_action('wpforms_process_complete', [$this, 'process_entry'], 20, 4);
        add_action('init', [$this, 'process_callback']);

        add_filter(
            'wpforms_frontend_form_data',
            function ($form_data) {
                $form_data['settings']['ajax_submit'] = false;

                return $form_data;
            }
        );
    }

    public function builder_content()
    {
        wpforms_panel_field(
            'checkbox',
            $this->slug,
            'trustist_enabletestmode',
            $this->form_data,
            esc_html__('Enable Trustist test mode', 'trustistwpforms'),
            [
                'parent' => 'payments',
                'default' => '0',
                'tooltip' => esc_html__('Enable this option to test without credentials', 'trustistwpforms'),
            ],
            true
        );
    }

    private function get_customer($form_data, $entry)
    {
        $name = '';
        $email = '';
        $phone = '';
        if (!empty($form_data) && !empty($entry)) {
            foreach ($form_data['fields'] as $num => $arr) {
                switch ($arr['type']) {
                    case 'name':
                        if ('simple' === $arr['format']) {
                            $name = $entry['fields'][$arr['id']];
                        } elseif ('first-last' === $arr['format']) {
                            $name = '';
                            if (isset($entry['fields'][$arr['id']]['first'])) {
                                $name = $entry['fields'][$arr['id']]['first'];
                            }

                            if (isset($entry['fields'][$arr['id']]['last'])) {
                                $name .= ' '.$entry['fields'][$arr['id']]['last'];
                            }
                        } elseif ('first-middle-last' === $arr['format']) {
                            $name = '';
                            if (isset($entry['fields'][$arr['id']]['first'])) {
                                $name = $entry['fields'][$arr['id']]['first'];
                            }

                            if (isset($entry['fields'][$arr['id']]['middle'])) {
                                $name .= ' '.$entry['fields'][$arr['id']]['middle'];
                            }

                            if (isset($entry['fields'][$arr['id']]['last'])) {
                                $name .= ' '.$entry['fields'][$arr['id']]['last'];
                            }
                        }
                        break;
                    case 'email':
                        $email = $entry['fields'][$arr['id']];
                        break;
                    case 'phone':
                        $phone = $entry['fields'][$arr['id']];
                        break;
                }
            }
        }

        return [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ];
    }

    private function get_url()
    {
        // fix response from api
        $req = $_SERVER['REQUEST_URI'];
        if (false !== strpos($req, 'wpforms_return')) {
            $req = str_replace('&amp;', '&', $req);
            $req = str_replace('%26amp%3B', '&', $req);
            $req = str_replace('amp%3B', '&', $req);
            $req = str_replace('?&wpforms_return', '?wpforms_return', $req);

            parse_str($req, $dataq);
            if (!empty($dataq)) {
                foreach ($dataq as $k => $v) {
                    $_REQUEST[$k] = $v;
                }
            }
        }

        $req = preg_replace('@(\?)?(\&)?(trustistcancel|trustisttimeout)=.*@', '', $req);
        $url = get_bloginfo('url').$req;

        return $url;
    }

    public function process_entry($fields, $entry, $form_data, $entry_id)
    {
        $error = false;

        if (empty($entry_id)) {
            return;
        }

        if (empty($form_data['payments'][$this->slug])) {
            return;
        }

        $customer_data = $this->get_customer($form_data, $entry);

        $form_has_payments = wpforms_has_payment('form', $form_data);
        $entry_has_paymemts = wpforms_has_payment('entry', $fields);
        if (!$form_has_payments || !$entry_has_paymemts) {
            $error = 'Trustist Payment stopped, missing payment fields';
        }

        // Check total charge amount.
        $amount = wpforms_get_total_payment($fields);
        if (empty($amount) || $amount == wpforms_sanitize_amount(0)) {
            $error = 'Trustist Payment stopped, invalid/empty amount';
        }

        if ($error) {
            wpforms_log(
                esc_html__('Trustist Payment Error', 'trustistwpforms'),
                $remote_post,
                [
                    'parent' => $entry_id,
                    'type' => ['error', 'payment'],
                    'form_id' => $form_data['id'],
                ]
            );

            return;
        }

        // Update entry to include payment details.
        $entry_data = [
            'status' => 'pending',
            'type' => 'payment',
            'meta' => wp_json_encode(
                [
                    'payment_type' => $this->slug,
                    'payment_total' => $amount,
                    'payment_currency' => 'GBP',
                ]
            ),
        ];
        wpforms()->entry->update($entry_id, $entry_data, '', '', ['cap' => false]);

        $query_args = 'form_id='.$form_data['id'].'&entry_id='.$entry_id.'&hash='.wp_hash($form_data['id'].','.$entry_id);
        $query_hash = base64_encode($query_args);

        $redirect_url = $this->get_url();
        $redirect_url = esc_url_raw(
            add_query_arg(
                [
                    'wpforms_return' => $query_hash,
                ],
                apply_filters('wpforms_trustist_return_url', $redirect_url, $form_data)
            )
        );

        // if (empty($customer_data['email']) && empty($customer_data['phone'])) {
        //     $customer_email = 'noreply@trustist.com';
        // } else {
        //     $customer_email = $customer_data['email'];
        // }

        // $customer_name = !empty($customer_data['name']) ? $customer_data['name'] : '';
        // $customer_phone = !empty($customer_data['phone']) ? $customer_data['phone'] : '';

        // $callback_url = $redirect_url;
        // $cancel_url = $this->get_url();

        // if (false !== strpos($cancel_url, '?')) {
        //     $cancel_url = $cancel_url.'&trustistcancel='.$query_hash;
        // } else {
        //     $cancel_url = $cancel_url.'?trustistcancel='.$query_hash;
        // }
        // $cancel_url = str_replace('?&', '?', $cancel_url);

        // $description = $form_data['settings']['form_title'].' (Order No: '.$entry_id.')';

        // $trustist_args['order_number'] = esc_attr($entry_id);
        // $trustist_args['buyer_name'] = esc_attr($customer_name);
        // $trustist_args['buyer_email'] = esc_attr($customer_email);
        // $trustist_args['buyer_phone'] = esc_attr($customer_phone);
        // $trustist_args['product_description'] = esc_attr($description);
        // $trustist_args['transaction_amount'] = esc_attr($amount);
        // $trustist_args['redirect_url'] = esc_url_raw($redirect_url);
        // $trustist_args['callback_url'] = esc_url_raw($callback_url);
        // $trustist_args['cancel_url'] = esc_url_raw($cancel_url);
        // $trustist_args['timeout_url'] = esc_url_raw($timeout_url);
        // $trustist_args['token'] = esc_attr($trustist_token);
        // $trustist_args['partner_uid'] = esc_attr($trustist_partner_uid);
        // $trustist_args['checksum'] = esc_attr($trustist_sign);
        // $trustist_args['payment_source'] = 'wpforms';

        // create the payment
        $payment = trustist_payment_create_payment($esc_attr($amount), esc_attr($entry_id), $esc_url_raw($redirect_url));

        // update the entry with the new data


        // redirect the user to pay

    }

    private function sanitize_response()
    {
        $params = [
             'amount',
             'bank',
             'buyer_email',
             'buyer_name',
             'buyer_phone',
             'checksum',
             'client_ip',
             'created_at',
             'created_at_unixtime',
             'currency',
             'exchange_number',
             'fpx_status',
             'fpx_status_message',
             'fpx_transaction_id',
             'fpx_transaction_time',
             'id',
             'interface_name',
             'interface_uid',
             'merchant_reference_number',
             'name',
             'order_number',
             'payment_id',
             'payment_method',
             'payment_status',
             'receipt_url',
             'retry_url',
             'source',
             'status_url',
             'transaction_amount',
             'transaction_amount_received',
             'uid',
             'trustistcancel',
             'trustisttimeout',
             'wpforms_return',
         ];

        $response_params = [];
        if (isset($_REQUEST)) {
            foreach ($params as $k) {
                if (isset($_REQUEST[$k])) {
                    $response_params[$k] = sanitize_text_field($_REQUEST[$k]);
                }
            }
        }

        return $response_params;
    }

    private function response_status($response_params)
    {
        if ((isset($response_params['payment_status']) && 'true' === $response_params['payment_status']) || (isset($response_params['fpx_status']) && 'true' === $response_params['fpx_status'])) {
            return true;
        }

        return false;
    }

    private function is_response_callback($response_params)
    {
        if (isset($response_params['fpx_status'])) {
            return true;
        }

        return false;
    }

    private function redirect($redirect)
    {
        if (!headers_sent()) {
            wp_redirect($redirect);
            exit;
        }

        $html = "<script>window.location.replace('".$redirect."');</script>";
        $html .= '<noscript><meta http-equiv="refresh" content="1; url='.$redirect.'">Redirecting..</noscript>';

        echo wp_kses(
            $html,
            [
                'script' => [],
                'noscript' => [],
                'meta' => [
                    'http-equiv' => [],
                    'content' => [],
                ],
            ]
        );
        exit;
    }

    public function process_callback()
    {
        // $response_params = $this->sanitize_response();

        // if (!empty($response_params) && isset($response_params['order_number'])) {
        //     $success = $this->response_status($response_params);

        //     $callback = $this->is_response_callback($response_params) ? 'Callback' : 'Redirect';
        //     $receipt_link = !empty($response_params['receipt_url']) ? $response_params['receipt_url'] : '';
        //     $status_link = !empty($response_params['status_url']) ? $response_params['status_url'] : '';
        //     $retry_link = !empty($response_params['retry_url']) ? $response_params['retry_url'] : '';

        //     $payment_id = absint($response_params['order_number']);
        //     $payment = wpforms()->entry->get(absint($payment_id));

        //     if (!isset($payment->form_id)) {
        //         return;
        //     }

        //     $form_data = wpforms()->form->get(
        //         $payment->form_id,
        //         [
        //             'content_only' => true,
        //         ]
        //     );

        //     if (empty($payment) || empty($form_data)) {
        //         return;
        //     }

        //     $payment_meta = json_decode($payment->meta, true);

        //     if ($success) {
        //         $note = 'Trustist payment successful<br>';
        //         $note .= 'Response from: '.$callback.'<br>';
        //         $note .= 'Transaction ID: '.$response_params['merchant_reference_number'].'<br>';

        //         if (!empty($receipt_link)) {
        //             $note .= 'Receipt link: <a href="'.$receipt_link.'" target=new rel="noopener">'.$receipt_link.'</a><br>';
        //         }

        //         if (!empty($status_link)) {
        //             $note .= 'Status link: <a href="'.$status_link.'" target=new rel="noopener">'.$status_link.'</a><br>';
        //         }

        //         wpforms()->entry_meta->add(
        //             [
        //                 'entry_id' => $payment_id,
        //                 'form_id' => $payment->form_id,
        //                 'user_id' => 1,
        //                 'type' => 'note',
        //                 'data' => $note,
        //             ],
        //             'entry_meta'
        //         );

        //         $payment_meta['payment_transaction'] = $response_params['merchant_reference_number'];
        //         wpforms()->entry->update(
        //             $payment_id,
        //             [
        //                 'status' => 'completed',
        //                 'meta' => wp_json_encode($payment_meta),
        //             ],
        //             '',
        //             '',
        //             ['cap' => false]
        //         );
        //     } else {
        //         $note = 'Trustist payment failed<br>';
        //         $note .= 'Response from: '.$callback.'<br>';
        //         $note .= 'Transaction ID: '.$response_params['merchant_reference_number'].'<br>';

        //         if (!empty($retry_link)) {
        //             $note .= 'Retry link: <a href="'.$retry_link.'" target=new rel="noopener">'.$retry_link.'</a><br>';
        //         }

        //         if (!empty($status_link)) {
        //             $note .= 'Status link: <a href="'.$status_link.'" target=new rel="noopener">'.$status_link.'</a><br>';
        //         }

        //         wpforms()->entry_meta->add(
        //             [
        //                 'entry_id' => $payment_id,
        //                 'form_id' => $payment->form_id,
        //                 'user_id' => 1,
        //                 'type' => 'note',
        //                 'data' => $note,
        //             ],
        //             'entry_meta'
        //         );

        //         $payment_meta['payment_transaction'] = $response_params['merchant_reference_number'];
        //         wpforms()->entry->update(
        //             $payment_id,
        //             [
        //                 'status' => 'failed',
        //                 'meta' => wp_json_encode($payment_meta),
        //             ],
        //             '',
        //             '',
        //             ['cap' => false]
        //         );
        //     }

        //     do_action('trustist_wpforms_process_complete', wpforms_decode($payment->fields), $form_data, $payment_id, $response_params);

        //     if (!empty($_GET['wpforms_return'])) {
        //         $str = base64_decode($_GET['wpforms_return']);
        //         if (false !== $str) {
        //             parse_str($str, $data);
        //             if (!empty($data) && \is_array($data)) {
        //                 $payment_id = absint($data['entry_id']);
        //                 $payment = wpforms()->entry->get(absint($payment_id));
        //                 if (!empty($payment) && 'failed' === $payment->status) {
        //                     $form_data = wpforms()->form->get(
        //                         $payment->form_id,
        //                         [
        //                             'content_only' => true,
        //                         ]
        //                     );
        //                     $payment_settings = $form_data['payments'][$this->slug];
        //                     if (!empty($payment_settings['trustist_failed_redirect'])) {
        //                         $this->redirect($payment_settings['trustist_failed_redirect']);
        //                         exit;
        //                     }
        //                 }
        //             }
        //         }

        //         $this->redirect($this->get_url());
        //     }
        //     exit;
        // }

        // if (!empty($response_params) && (!empty($response_params['trustistcancel']) || !empty($response_params['trustisttimeout']))) {
        //     $status = !empty($response_params['trustistcancel']) ? 'cancelled' : 'timeout';
        //     $query_hash = !empty($response_params['trustistcancel']) ? $response_params['trustistcancel'] : $response_params['trustisttimeout'];

        //     $str = base64_decode($query_hash);
        //     if (false !== $str) {
        //         parse_str($str, $data);
        //         if (!empty($data) && \is_array($data)) {
        //             $payment_id = absint($data['entry_id']);
        //             $payment = wpforms()->entry->get(absint($payment_id));
        //             if (!empty($payment) && 'pending' === $payment->status) {
        //                 $note = 'Trustist payment '.$status.'<br>';
        //                 wpforms()->entry_meta->add(
        //                     [
        //                         'entry_id' => $payment_id,
        //                         'form_id' => $payment->form_id,
        //                         'user_id' => 1,
        //                         'type' => 'note',
        //                         'data' => $note,
        //                     ],
        //                     'entry_meta'
        //                 );

        //                 $payment_meta = json_decode($payment->meta, true);
        //                 wpforms()->entry->update(
        //                     $payment_id,
        //                     [
        //                         'status' => $status,
        //                         'meta' => wp_json_encode($payment_meta),
        //                     ],
        //                     '',
        //                     '',
        //                     ['cap' => false]
        //                 );
        //             }
        //         }
        //     }

        //     $this->redirect($this->get_url());
            exit;
        //}
    }
}

<?php

defined('ABSPATH') || exit;

class TrustistPaymentsSettings
{
    const TRUSTIST_PAYMENTS_MERCHANT_NAME_KEY = 'merchant_name';
    const TRUSTIST_PAYMENTS_CARDS_ENABLED_KEY = 'cards_enabled';
    const TRUSTIST_PAYMENTS_CONNECTION_SUCCESS_KEY = 'connection_success';
    const TRUSTIST_PAYMENTS_LAST_UPDATED_KEY = 'last_updated';
    const TRUSTIST_PAYMENTS_STANDING_ORDERS_ENABLED_KEY = 'standing_orders_enabled';
    const TRUSTIST_PAYMENTS_PUBLIC_API_KEY_KEY = 'public_key';
    const TRUSTIST_PAYMENTS_PRIVATE_API_KEY_KEY = 'private_key';

    public static function get($key, $isTest, $default = '')
    {
        return get_option(TrustistPaymentsSettings::fullyQualifiedKey($key, $isTest), $default);
    }

    public static function set($key, $value, $isTest)
    {
        update_option(TrustistPaymentsSettings::fullyQualifiedKey($key, $isTest), $value);
    }

    public static function fullyQualifiedKey($key, $isTest)
    {
        return $isTest ? 'trustist_payments_sandbox_' . $key : 'trustist_payments_' . $key;
    }
}

?>
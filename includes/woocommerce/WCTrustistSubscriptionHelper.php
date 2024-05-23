<?php
class WCTrustistSubscriptionHelper {
    /**
     * Get the subscription for a renewal order
     *
     * @param WC_Order $renewal_order
     *
     * @return WC_Subscription|null
     */
    public static function get_subscriptions_for_renewal_order($renewal_order)
    {
        if (function_exists('wcs_get_subscriptions_for_renewal_order')) {
            $subscriptions = wcs_get_subscriptions_for_renewal_order($renewal_order);

            return end($subscriptions);
        }

        return null;
    }
}
?>
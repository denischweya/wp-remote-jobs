<?php
/**
 * Payment settings store + admin page.
 *
 * @package Remote_Jobs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Remjobs_Payment_Settings
{
    const OPTION = 'remjobs_payment_settings';

    /** setting key => wp-config constant that overrides it */
    const CONSTANTS = array(
        'stripe_secret_key'     => 'REMJOBS_STRIPE_SECRET',
        'stripe_webhook_secret' => 'REMJOBS_STRIPE_WEBHOOK_SECRET',
        'paypal_client_id'      => 'REMJOBS_PAYPAL_CLIENT_ID',
        'paypal_secret'         => 'REMJOBS_PAYPAL_SECRET',
    );

    public static function defaults(): array
    {
        return array(
            'enabled_gateways'        => array('stripe', 'paypal'),
            'mode'                    => 'sandbox',
            'currency'                => 'USD',
            'stripe_publishable_key'  => '',
            'stripe_secret_key'       => '',
            'stripe_webhook_secret'   => '',
            'stripe_price_id_monthly' => '',
            'paypal_client_id'        => '',
            'paypal_secret'           => '',
            'paypal_webhook_id'       => '',
            'paypal_plan_id_monthly'  => '',
            'single_price'            => '49.00',
            'single_duration_days'    => 30,
            'monthly_price'           => '99.00',
            'monthly_scope'           => 'job',
            'monthly_account_cap'     => 0,
            'expiry_behavior'         => 'unfeature',
        );
    }

    public static function all(): array
    {
        $stored = get_option(self::OPTION, array());
        return array_merge(self::defaults(), is_array($stored) ? $stored : array());
    }

    /**
     * Read a single setting. Secret keys defined as wp-config constants win.
     *
     * @param string $key
     * @param mixed  $fallback
     * @return mixed
     */
    public static function get(string $key, $fallback = null)
    {
        if (isset(self::CONSTANTS[$key]) && defined(self::CONSTANTS[$key])) {
            return constant(self::CONSTANTS[$key]);
        }
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $fallback;
    }

    public static function sanitize(array $input): array
    {
        $d = self::defaults();
        $out = $d;

        $out['mode'] = (isset($input['mode']) && $input['mode'] === 'live') ? 'live' : 'sandbox';
        $out['currency'] = isset($input['currency'])
            ? strtoupper(substr(sanitize_text_field($input['currency']), 0, 3)) : $d['currency'];

        $gateways = isset($input['enabled_gateways']) && is_array($input['enabled_gateways'])
            ? array_values(array_intersect(array('stripe', 'paypal'), array_map('sanitize_text_field', $input['enabled_gateways'])))
            : array();
        $out['enabled_gateways'] = $gateways;

        foreach (array('stripe_publishable_key', 'stripe_secret_key', 'stripe_webhook_secret', 'stripe_price_id_monthly',
                       'paypal_client_id', 'paypal_secret', 'paypal_webhook_id', 'paypal_plan_id_monthly') as $k) {
            $out[$k] = isset($input[$k]) ? sanitize_text_field($input[$k]) : '';
        }

        $out['single_price']  = number_format((float) ($input['single_price'] ?? $d['single_price']), 2, '.', '');
        $out['monthly_price'] = number_format((float) ($input['monthly_price'] ?? $d['monthly_price']), 2, '.', '');
        $out['single_duration_days'] = max(1, (int) ($input['single_duration_days'] ?? $d['single_duration_days']));
        $out['monthly_account_cap']  = max(0, (int) ($input['monthly_account_cap'] ?? $d['monthly_account_cap']));
        $out['monthly_scope']    = (isset($input['monthly_scope']) && $input['monthly_scope'] === 'account') ? 'account' : 'job';
        $out['expiry_behavior']  = (isset($input['expiry_behavior']) && $input['expiry_behavior'] === 'unpublish') ? 'unpublish' : 'unfeature';

        return $out;
    }
}

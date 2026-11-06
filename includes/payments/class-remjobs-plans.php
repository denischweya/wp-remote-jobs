<?php
/**
 * Plan + pricing resolver. The plugin ships exactly two plans, configured via settings.
 *
 * @package Remote_Jobs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Remjobs_Plans
{
    const ZERO_DECIMAL = array('BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF');

    public static function ids(): array
    {
        return array('single', 'monthly');
    }

    public static function exists(string $plan): bool
    {
        return in_array($plan, self::ids(), true);
    }

    public static function get(string $plan): ?array
    {
        if (!self::exists($plan)) {
            return null;
        }
        $currency = (string) Remjobs_Payment_Settings::get('currency', 'USD');
        if ($plan === 'single') {
            return array(
                'id'            => 'single',
                'type'          => 'one_time',
                'price'         => (string) Remjobs_Payment_Settings::get('single_price', '49.00'),
                'currency'      => $currency,
                'duration_days' => (int) Remjobs_Payment_Settings::get('single_duration_days', 30),
                'scope'         => null,
                'cap'           => null,
            );
        }
        return array(
            'id'            => 'monthly',
            'type'          => 'subscription',
            'price'         => (string) Remjobs_Payment_Settings::get('monthly_price', '99.00'),
            'currency'      => $currency,
            'duration_days' => null,
            'scope'         => (string) Remjobs_Payment_Settings::get('monthly_scope', 'job'),
            'cap'           => (int) Remjobs_Payment_Settings::get('monthly_account_cap', 0),
        );
    }

    /**
     * Price in the currency's minor unit (cents), zero-decimal aware.
     */
    public static function amount_minor(string $plan): int
    {
        $p = self::get($plan);
        if (!$p) {
            return 0;
        }
        $price = (float) $p['price'];
        if (in_array(strtoupper($p['currency']), self::ZERO_DECIMAL, true)) {
            return (int) round($price);
        }
        return (int) round($price * 100);
    }
}

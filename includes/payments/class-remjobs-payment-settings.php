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
            'my_jobs_page_id'         => 0,
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
        $out['my_jobs_page_id']  = max(0, (int) ($input['my_jobs_page_id'] ?? $d['my_jobs_page_id']));

        return $out;
    }

    public function register(): void
    {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_menu(): void
    {
        add_submenu_page(
            'edit.php?post_type=remjobs_jobs',
            __('Payments', 'remote-jobs'),
            __('Payments', 'remote-jobs'),
            'manage_options',
            'remjobs-payments',
            array($this, 'render_page')
        );
    }

    public function register_settings(): void
    {
        register_setting('remjobs_payments', self::OPTION, array(
            'type'              => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize'),
            'default'           => self::defaults(),
        ));
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $s = self::all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Remote Jobs — Payments', 'remote-jobs'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('remjobs_payments'); ?>
                <table class="form-table" role="presentation">
                    <?php
                    $this->text_row('currency', __('Currency (ISO 4217)', 'remote-jobs'), $s);
                    $this->select_row('mode', __('Mode', 'remote-jobs'), $s, array('sandbox' => 'Sandbox', 'live' => 'Live'));
                    $this->text_row('single_price', __('Single plan price', 'remote-jobs'), $s);
                    $this->text_row('single_duration_days', __('Single plan featured days', 'remote-jobs'), $s);
                    $this->text_row('monthly_price', __('Monthly plan price', 'remote-jobs'), $s);
                    $this->select_row('monthly_scope', __('Monthly scope', 'remote-jobs'), $s, array('job' => 'Per job', 'account' => 'Account-wide'));
                    $this->text_row('monthly_account_cap', __('Account cap (0 = unlimited)', 'remote-jobs'), $s);
                    $this->select_row('expiry_behavior', __('On expiry', 'remote-jobs'), $s, array('unfeature' => 'Unfeature (keep published)', 'unpublish' => 'Unpublish'));
                    $this->page_row('my_jobs_page_id', __('My Jobs page', 'remote-jobs'), $s);
                    $this->text_row('stripe_publishable_key', __('Stripe publishable key', 'remote-jobs'), $s);
                    $this->text_row('stripe_secret_key', __('Stripe secret key', 'remote-jobs'), $s);
                    $this->text_row('stripe_webhook_secret', __('Stripe webhook secret', 'remote-jobs'), $s);
                    $this->text_row('paypal_client_id', __('PayPal client ID', 'remote-jobs'), $s);
                    $this->text_row('paypal_secret', __('PayPal secret', 'remote-jobs'), $s);
                    $this->text_row('paypal_webhook_id', __('PayPal webhook ID', 'remote-jobs'), $s);
                    ?>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function text_row(string $key, string $label, array $s): void
    {
        $val = isset($s[$key]) ? $s[$key] : '';
        printf(
            '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="text" id="%1$s" name="%3$s[%1$s]" value="%4$s" class="regular-text" /></td></tr>',
            esc_attr($key),
            esc_html($label),
            esc_attr(self::OPTION),
            esc_attr((string) $val)
        );
    }

    private function select_row(string $key, string $label, array $s, array $choices): void
    {
        $val = isset($s[$key]) ? $s[$key] : '';
        $opts = '';
        foreach ($choices as $ck => $cl) {
            $opts .= sprintf('<option value="%s"%s>%s</option>', esc_attr($ck), selected($val, $ck, false), esc_html($cl));
        }
        printf(
            '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><select id="%1$s" name="%3$s[%1$s]">%4$s</select></td></tr>',
            esc_attr($key),
            esc_html($label),
            esc_attr(self::OPTION),
            $opts // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_attr/esc_html above
        );
    }

    /**
     * A wp_dropdown_pages() row for choosing a page (e.g. the My Jobs page).
     */
    private function page_row(string $key, string $label, array $s): void
    {
        $val = isset($s[$key]) ? (int) $s[$key] : 0;
        echo '<tr><th scope="row"><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
        wp_dropdown_pages(array(
            'id'                => $key,
            'name'              => self::OPTION . '[' . $key . ']',
            'selected'          => $val,
            'show_option_none'  => __('— None —', 'remote-jobs'),
            'option_none_value' => '0',
        ));
        echo '</td></tr>';
    }
}

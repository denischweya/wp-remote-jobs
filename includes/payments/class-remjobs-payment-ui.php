<?php
/**
 * User-facing payment UI: admin-post handlers (start checkout, cancel
 * subscription), the My Jobs shortcode, return/cancel notices, the admin
 * Orders list, and job-list columns. Money decisions live in the payment
 * engine (Phases 1-3); this class is glue.
 *
 * @package Remote_Jobs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Remjobs_Payment_Ui
{
    /** @var Remjobs_Checkout */
    private $checkout;

    /** @var Remjobs_Payment_Gateway[] */
    private $gateways;

    /** @var Remjobs_Featured */
    private $featured;

    public function __construct(Remjobs_Checkout $checkout, array $gateways, Remjobs_Featured $featured)
    {
        $this->checkout = $checkout;
        $this->gateways = $gateways;
        $this->featured = $featured;
    }

    public function register(): void
    {
        add_action('admin_post_remjobs_start_checkout', array($this, 'handle_start_checkout'));
        add_action('admin_post_remjobs_cancel_subscription', array($this, 'handle_cancel_subscription'));
        add_shortcode('remjobs_my_jobs', array($this, 'render_my_jobs'));
        add_filter('remjobs_account_url', array($this, 'my_jobs_url'));
        add_action('admin_menu', array($this, 'add_orders_page'));
        add_filter('manage_remjobs_jobs_posts_columns', array($this, 'add_job_columns'));
        add_action('manage_remjobs_jobs_posts_custom_column', array($this, 'render_job_column'), 10, 2);
    }

    public function my_jobs_url(string $url): string
    {
        $id = (int) Remjobs_Payment_Settings::get('my_jobs_page_id', 0);
        return $id > 0 ? (string) get_permalink($id) : $url;
    }

    /** enabled AND configured gateways, as id=>label */
    public function enabled_gateway_choices(): array
    {
        $enabled = (array) Remjobs_Payment_Settings::get('enabled_gateways', array());
        $labels = array(
            'stripe' => __('Credit card (Stripe)', 'remote-jobs'),
            'paypal' => __('PayPal', 'remote-jobs'),
        );
        $out = array();
        foreach ($enabled as $id) {
            if (isset($this->gateways[$id]) && $this->gateways[$id]->is_configured()) {
                $out[$id] = isset($labels[$id]) ? $labels[$id] : $id;
            }
        }
        return $out;
    }

    public function handle_start_checkout(): void
    {
        if (!is_user_logged_in() || !check_admin_referer('remjobs_start_checkout')) {
            wp_die(esc_html__('Security check failed.', 'remote-jobs'));
        }
        $job_id  = isset($_POST['job_id']) ? absint($_POST['job_id']) : 0;
        $plan    = isset($_POST['plan']) ? sanitize_text_field(wp_unslash($_POST['plan'])) : '';
        $gateway = isset($_POST['gateway']) ? sanitize_text_field(wp_unslash($_POST['gateway'])) : '';
        try {
            $url = $this->checkout->start(get_current_user_id(), $job_id, $plan, $gateway);
            wp_redirect($url);
            exit;
        } catch (Exception $e) {
            set_transient('remjobs_ui_error_' . get_current_user_id(), $e->getMessage(), 60);
            wp_safe_redirect(wp_get_referer() ?: home_url('/'));
            exit;
        }
    }

    public function handle_cancel_subscription(): void
    {
        if (!is_user_logged_in() || !check_admin_referer('remjobs_cancel_subscription')) {
            wp_die(esc_html__('Security check failed.', 'remote-jobs'));
        }
        $sub_id = isset($_POST['subscription_id']) ? absint($_POST['subscription_id']) : 0;
        $sub = Remjobs_Subscriptions::get($sub_id);
        if ($sub && (int) $sub['user_id'] === get_current_user_id()) {
            $gid = (string) $sub['gateway'];
            if (isset($this->gateways[$gid])) {
                $this->gateways[$gid]->cancel_subscription((string) $sub['gateway_subscription_id']);
            }
            Remjobs_Subscriptions::set_status($sub_id, 'canceled');
        }
        set_transient('remjobs_ui_notice_' . get_current_user_id(), __('Subscription canceled.', 'remote-jobs'), 60);
        wp_safe_redirect(wp_get_referer() ?: home_url('/'));
        exit;
    }

    // render_my_jobs/add_orders_page/add_job_columns/render_job_column -> Tasks 3-4.
}

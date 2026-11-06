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

    /**
     * [remjobs_my_jobs] shortcode: lists the current user's jobs with status,
     * featured/expiry state, and Feature / Cancel subscription actions.
     */
    public function render_my_jobs($atts = array()): string
    {
        if (!is_user_logged_in()) {
            return '<div class="remjobs-notice remjobs-my-jobs-login">'
                . esc_html__('Please log in to see your jobs.', 'remote-jobs')
                . '</div>';
        }

        $user_id = get_current_user_id();
        $out = $this->render_notices($user_id);
        $out .= $this->render_return_cancel_notice();

        $jobs = get_posts(array(
            'post_type'      => 'remjobs_jobs',
            'post_status'    => 'any',
            'author'         => $user_id,
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));

        $out .= '<table class="remjobs-my-jobs"><thead><tr>'
            . '<th>' . esc_html__('Title', 'remote-jobs') . '</th>'
            . '<th>' . esc_html__('Status', 'remote-jobs') . '</th>'
            . '<th>' . esc_html__('Action', 'remote-jobs') . '</th>'
            . '</tr></thead><tbody>';

        foreach ($jobs as $job) {
            $out .= '<tr>';
            $out .= '<td>' . esc_html(get_the_title($job)) . '</td>';
            $out .= '<td>' . esc_html($this->job_status_label((int) $job->ID, $job->post_status)) . '</td>';
            $out .= '<td>' . $this->render_job_action((int) $job->ID, $job->post_status) . '</td>';
            $out .= '</tr>';
        }

        $out .= '</tbody></table>';

        return $out;
    }

    /**
     * Human-readable status label for a My Jobs row.
     */
    private function job_status_label(int $job_id, string $post_status): string
    {
        $payment_status = get_post_meta($job_id, '_remjobs_payment_status', true);
        if ($post_status === 'draft' && $payment_status === 'awaiting') {
            return __('Awaiting payment', 'remote-jobs');
        }
        if ($post_status === 'publish' && $this->featured->is_featured($job_id)) {
            $expires = get_post_meta($job_id, '_remjobs_featured_expires', true);
            return sprintf(
                /* translators: %s: expiry date */
                __('Featured until %s', 'remote-jobs'),
                $expires ? $expires : __('unknown', 'remote-jobs')
            );
        }
        return $post_status;
    }

    /**
     * Action cell for a My Jobs row: Feature form, or renewal date + Cancel
     * subscription form when the feature is backed by an active subscription.
     */
    private function render_job_action(int $job_id, string $post_status): string
    {
        $is_featured = $this->featured->is_featured($job_id);
        $sub_id = (int) get_post_meta($job_id, '_remjobs_feature_subscription_id', true);

        if ($is_featured && $sub_id > 0) {
            $expires = get_post_meta($job_id, '_remjobs_featured_expires', true);
            $html = '<div class="remjobs-renewal">';
            $html .= sprintf(
                /* translators: %s: renewal date */
                esc_html__('Renews %s', 'remote-jobs'),
                esc_html($expires ? $expires : '')
            );
            $html .= '</div>';
            $html .= $this->cancel_subscription_form($sub_id);
            return $html;
        }

        if (!$is_featured && in_array($post_status, array('publish', 'pending', 'draft'), true)) {
            return $this->feature_form($job_id);
        }

        return '';
    }

    /**
     * Feature form: plan + gateway choice, posting to admin-post.php.
     */
    private function feature_form(int $job_id): string
    {
        $gateway_choices = $this->enabled_gateway_choices();
        if (empty($gateway_choices)) {
            return '';
        }

        $html = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $html .= '<input type="hidden" name="action" value="remjobs_start_checkout" />';
        $html .= '<input type="hidden" name="job_id" value="' . esc_attr((string) $job_id) . '" />';
        $html .= wp_nonce_field('remjobs_start_checkout', '_wpnonce', true, false);

        $html .= '<select name="plan">';
        foreach (Remjobs_Plans::ids() as $plan_id) {
            $plan = Remjobs_Plans::get($plan_id);
            if (!$plan) {
                continue;
            }
            $html .= '<option value="' . esc_attr($plan_id) . '">'
                . esc_html($plan_id) . ' (' . esc_html($plan['price']) . ' ' . esc_html($plan['currency']) . ')'
                . '</option>';
        }
        $html .= '</select>';

        $html .= '<select name="gateway">';
        foreach ($gateway_choices as $gid => $glabel) {
            $html .= '<option value="' . esc_attr($gid) . '">' . esc_html($glabel) . '</option>';
        }
        $html .= '</select>';

        $html .= '<button type="submit">' . esc_html__('Feature', 'remote-jobs') . '</button>';
        $html .= '</form>';

        return $html;
    }

    /**
     * Cancel subscription form, posting to admin-post.php.
     */
    private function cancel_subscription_form(int $subscription_id): string
    {
        $html = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $html .= '<input type="hidden" name="action" value="remjobs_cancel_subscription" />';
        $html .= '<input type="hidden" name="subscription_id" value="' . esc_attr((string) $subscription_id) . '" />';
        $html .= wp_nonce_field('remjobs_cancel_subscription', '_wpnonce', true, false);
        $html .= '<button type="submit">' . esc_html__('Cancel subscription', 'remote-jobs') . '</button>';
        $html .= '</form>';

        return $html;
    }

    /**
     * Prepend+consume any pending error/notice transient for this user.
     */
    private function render_notices(int $user_id): string
    {
        $out = '';

        $error = get_transient('remjobs_ui_error_' . $user_id);
        if ($error) {
            delete_transient('remjobs_ui_error_' . $user_id);
            $out .= '<div class="remjobs-notice remjobs-error">' . esc_html((string) $error) . '</div>';
        }

        $notice = get_transient('remjobs_ui_notice_' . $user_id);
        if ($notice) {
            delete_transient('remjobs_ui_notice_' . $user_id);
            $out .= '<div class="remjobs-notice remjobs-success">' . esc_html((string) $notice) . '</div>';
        }

        return $out;
    }

    /**
     * Return/cancel notice from the gateway redirect: verifies the return
     * nonce and looks up the order by key before showing any status text.
     */
    private function render_return_cancel_notice(): string
    {
        if (isset($_GET['remjobs_cancel'])) {
            return '<div class="remjobs-notice remjobs-cancel">'
                . esc_html__('Checkout was canceled.', 'remote-jobs')
                . '</div>';
        }

        if (isset($_GET['remjobs_return']) && isset($_GET['_wpnonce'])) {
            $key = sanitize_text_field(wp_unslash($_GET['remjobs_return']));
            $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));

            if (wp_verify_nonce($nonce, 'remjobs_return_' . $key)) {
                $order = Remjobs_Orders::get_by_key($key);
                if ($order) {
                    if ($order['status'] === 'paid') {
                        return '<div class="remjobs-notice remjobs-success">'
                            . esc_html__('Payment received — your listing is being published.', 'remote-jobs')
                            . '</div>';
                    }
                    return '<div class="remjobs-notice remjobs-pending">'
                        . esc_html__('We are confirming your payment…', 'remote-jobs')
                        . '</div>';
                }
            }
        }

        return '';
    }

    /**
     * Register the read-only admin Orders list under the Jobs menu.
     */
    public function add_orders_page(): void
    {
        add_submenu_page(
            'edit.php?post_type=remjobs_jobs',
            __('Orders', 'remote-jobs'),
            __('Orders', 'remote-jobs'),
            'manage_options',
            'remjobs-orders',
            array($this, 'render_orders_page')
        );
    }

    /**
     * Render the read-only admin Orders list (most recent 200 orders).
     */
    public function render_orders_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $orders = $wpdb->get_results('SELECT * FROM ' . Remjobs_Orders::table() . ' ORDER BY id DESC LIMIT 200', ARRAY_A);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Remote Jobs — Orders', 'remote-jobs') . '</h1>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        foreach (array(
            __('ID', 'remote-jobs'),
            __('Date', 'remote-jobs'),
            __('User', 'remote-jobs'),
            __('Job', 'remote-jobs'),
            __('Plan', 'remote-jobs'),
            __('Gateway', 'remote-jobs'),
            __('Amount', 'remote-jobs'),
            __('Status', 'remote-jobs'),
        ) as $label) {
            echo '<th>' . esc_html($label) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ((array) $orders as $order) {
            $user = get_userdata((int) $order['user_id']);
            $job_title = $order['job_id'] ? get_the_title((int) $order['job_id']) : '';

            echo '<tr>';
            echo '<td>' . esc_html((string) $order['id']) . '</td>';
            echo '<td>' . esc_html((string) $order['created_at']) . '</td>';
            echo '<td>' . esc_html($user ? $user->user_login : (string) $order['user_id']) . '</td>';
            echo '<td>' . esc_html($job_title !== '' ? $job_title : (string) $order['job_id']) . '</td>';
            echo '<td>' . esc_html((string) $order['plan']) . '</td>';
            echo '<td>' . esc_html((string) $order['gateway']) . '</td>';
            echo '<td>' . esc_html($order['amount'] . ' ' . $order['currency']) . '</td>';
            echo '<td>' . esc_html((string) $order['status']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * Insert Featured / Feature expires / Payment columns before the Date
     * column on the remjobs_jobs admin list screen.
     */
    public function add_job_columns(array $columns): array
    {
        $new = array();
        foreach ($columns as $key => $label) {
            if ($key === 'date') {
                $new['remjobs_featured'] = __('Featured', 'remote-jobs');
                $new['remjobs_expires'] = __('Feature expires', 'remote-jobs');
                $new['remjobs_payment'] = __('Payment', 'remote-jobs');
            }
            $new[$key] = $label;
        }
        if (!isset($new['remjobs_featured'])) {
            // 'date' column wasn't present (unlikely); append at the end.
            $new['remjobs_featured'] = __('Featured', 'remote-jobs');
            $new['remjobs_expires'] = __('Feature expires', 'remote-jobs');
            $new['remjobs_payment'] = __('Payment', 'remote-jobs');
        }
        return $new;
    }

    /**
     * Render a single custom column cell on the remjobs_jobs admin list screen.
     */
    public function render_job_column(string $column, int $post_id): void
    {
        switch ($column) {
            case 'remjobs_featured':
                echo esc_html($this->featured->is_featured($post_id) ? __('Yes', 'remote-jobs') : '—');
                break;
            case 'remjobs_expires':
                $expires = get_post_meta($post_id, '_remjobs_featured_expires', true);
                echo esc_html($expires !== '' ? (string) $expires : '—');
                break;
            case 'remjobs_payment':
                $status = get_post_meta($post_id, '_remjobs_payment_status', true);
                echo esc_html($status !== '' ? (string) $status : '—');
                break;
        }
    }
}

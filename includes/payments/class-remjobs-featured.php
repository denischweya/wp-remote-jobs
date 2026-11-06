<?php
/**
 * Featured-listing lifecycle engine: activate, extend, and expire the
 * "featured" state on a job, plus the hourly expiry/cleanup cron callback.
 *
 * @package Remote_Jobs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Remjobs_Featured
{
    const CRON_HOOK = 'remjobs_feature_expiry_check';

    /**
     * Activate (or renew) the featured state on a job.
     *
     * @param int    $job_id
     * @param string $plan    'single' | 'monthly'
     * @param array  $context Optional keys: order_id (int), subscription_id (int), current_period_end (string Y-m-d H:i:s UTC).
     */
    public function activate_feature(int $job_id, string $plan, array $context = array()): void
    {
        if (get_post_status($job_id) !== 'publish') {
            wp_update_post(array('ID' => $job_id, 'post_status' => 'publish'));
        }

        update_post_meta($job_id, '_remjobs_featured', '1');
        update_post_meta($job_id, '_remjobs_is_premium', '1');
        update_post_meta($job_id, '_remjobs_feature_plan', $plan);
        update_post_meta($job_id, '_remjobs_payment_status', 'paid');
        delete_post_meta($job_id, '_remjobs_expired');

        if (isset($context['order_id'])) {
            update_post_meta($job_id, '_remjobs_feature_order_id', (int) $context['order_id']);
        }
        if (isset($context['subscription_id'])) {
            update_post_meta($job_id, '_remjobs_feature_subscription_id', (int) $context['subscription_id']);
        }

        if ($plan === 'monthly') {
            $expires = !empty($context['current_period_end'])
                ? (string) $context['current_period_end']
                : gmdate('Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS);
        } else {
            $duration_days = (int) Remjobs_Plans::get('single')['duration_days'];
            $expires = gmdate('Y-m-d H:i:s', time() + $duration_days * DAY_IN_SECONDS);
        }
        update_post_meta($job_id, '_remjobs_featured_expires', $expires);
    }

    /**
     * Update the expiry timestamp on an already-featured job (renewal).
     */
    public function extend_feature(int $job_id, string $new_expires_utc): void
    {
        update_post_meta($job_id, '_remjobs_featured_expires', $new_expires_utc);
    }

    /**
     * Apply the configured expiry behavior and clear featured state.
     */
    public function expire_feature(int $job_id): void
    {
        $behavior = Remjobs_Payment_Settings::get('expiry_behavior');

        if ($behavior === 'unpublish') {
            wp_update_post(array('ID' => $job_id, 'post_status' => 'draft'));
            update_post_meta($job_id, '_remjobs_expired', '1');
        }

        update_post_meta($job_id, '_remjobs_featured', '');
        update_post_meta($job_id, '_remjobs_is_premium', '');
        delete_post_meta($job_id, '_remjobs_featured_expires');
    }

    public function is_featured(int $job_id): bool
    {
        return get_post_meta($job_id, '_remjobs_featured', true) === '1';
    }

    /**
     * Count published jobs authored by $user_id that are currently featured.
     */
    public function count_featured_for_user(int $user_id): int
    {
        $ids = get_posts(array(
            'post_type'      => 'remjobs_jobs',
            'post_status'    => 'publish',
            'author'         => $user_id,
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'   => '_remjobs_featured',
                    'value' => '1',
                ),
            ),
        ));
        return count($ids);
    }

    /**
     * Account-scope monthly cap check. Single/per-job plans are always allowed;
     * this is only consulted for account-wide monthly scope.
     */
    public function can_feature(int $user_id): bool
    {
        $cap = (int) Remjobs_Payment_Settings::get('monthly_account_cap');
        return $cap === 0 || $this->count_featured_for_user($user_id) < $cap;
    }

    /**
     * Hourly cron callback (CRON_HOOK): expire lapsed features (skipping jobs
     * protected by an active subscription) and delete abandoned
     * awaiting-payment drafts older than 48h.
     */
    public function run_expiry_sweep(): void
    {
        require_once __DIR__ . '/class-remjobs-subscriptions.php';

        $lapsed_ids = get_posts(array(
            'post_type'      => 'remjobs_jobs',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => '_remjobs_featured',
                    'value' => '1',
                ),
                array(
                    'key'     => '_remjobs_featured_expires',
                    'value'   => gmdate('Y-m-d H:i:s'),
                    'compare' => '<',
                    'type'    => 'DATETIME',
                ),
            ),
        ));

        foreach ($lapsed_ids as $job_id) {
            if (Remjobs_Subscriptions::active_for_job((int) $job_id) !== null) {
                continue;
            }
            $this->expire_feature((int) $job_id);
        }

        $awaiting_ids = get_posts(array(
            'post_type'      => 'remjobs_jobs',
            'post_status'    => 'draft',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => '_remjobs_payment_status',
                    'value' => 'awaiting',
                ),
            ),
        ));

        $cutoff = time() - 48 * HOUR_IN_SECONDS;
        foreach ($awaiting_ids as $job_id) {
            // get_post_time('U', true, ...) relies on post_date_gmt, which
            // WordPress leaves as '0000-00-00 00:00:00' for non-published
            // statuses (drafts). Derive a real UTC timestamp from post_date.
            $post_date_gmt = get_gmt_from_date(get_post_field('post_date', (int) $job_id));
            $created_utc = strtotime($post_date_gmt . ' GMT');
            if ($created_utc !== false && $created_utc < $cutoff) {
                wp_delete_post((int) $job_id, true);
            }
        }
    }
}

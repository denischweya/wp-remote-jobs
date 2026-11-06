<?php
/**
 * Idempotent payment fulfillment: marks orders paid, upserts subscriptions,
 * and activates/extends/cancels the featured state. The only caller of
 * Remjobs_Featured::activate_feature() from the webhook path.
 *
 * @package Remote_Jobs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Remjobs_Fulfillment
{
    /** @var Remjobs_Featured */
    private $featured;

    public function __construct(Remjobs_Featured $featured)
    {
        $this->featured = $featured;
    }

    /** Idempotent. Marks order paid, upserts subscription (if any), activates the feature. */
    public function fulfill(int $order_id, array $event): void
    {
        $order = Remjobs_Orders::get($order_id);
        if (!$order || $order['status'] === 'paid') {
            return;
        } // idempotent guard

        Remjobs_Orders::mark_paid($order_id, (string) ($event['gateway_order_id'] ?? ''), (string) ($event['event_id'] ?? ''));

        $context = array('order_id' => $order_id);
        if (!empty($event['subscription'])) {
            $sub = $event['subscription'];
            $plan = Remjobs_Plans::get('monthly');
            $scope = $plan ? (string) $plan['scope'] : 'job';
            $sub_id = Remjobs_Subscriptions::upsert((string) $order['gateway'], (string) $sub['gateway_subscription_id'], array(
                'user_id'             => (int) $order['user_id'],
                'scope'               => $scope,
                'job_id'              => $scope === 'job' ? (int) $order['job_id'] : null,
                'gateway_customer_id' => (string) ($sub['gateway_customer_id'] ?? ''),
                'status'              => 'active',
                'current_period_end'  => $sub['current_period_end'] ?? null,
            ));
            Remjobs_Orders::update($order_id, array('subscription_id' => $sub_id));
            $context['subscription_id'] = $sub_id;
            $context['current_period_end'] = $sub['current_period_end'] ?? null;
        }
        if (!empty($order['job_id'])) {
            $this->featured->activate_feature((int) $order['job_id'], (string) $order['plan'], $context);
        }
    }

    /** Renewal: extend every covered job's expiry to the new period end. */
    public function renew(array $event): void
    {
        if (empty($event['subscription']['gateway_subscription_id'])) {
            return;
        }
        $sub = Remjobs_Subscriptions::get_by_gateway_id_any($event['subscription']['gateway_subscription_id']);
        if (!$sub) {
            return;
        }
        $cpe = $event['subscription']['current_period_end'] ?? null;
        if ($cpe) {
            Remjobs_Subscriptions::upsert((string) $sub['gateway'], (string) $sub['gateway_subscription_id'], array('status' => 'active', 'current_period_end' => $cpe));
            foreach ($this->covered_job_ids($sub) as $jid) {
                $this->featured->extend_feature((int) $jid, (string) $cpe);
            }
        }
    }

    public function cancel(array $event): void
    {
        if (empty($event['subscription']['gateway_subscription_id'])) {
            return;
        }
        $sub = Remjobs_Subscriptions::get_by_gateway_id_any($event['subscription']['gateway_subscription_id']);
        if ($sub) {
            Remjobs_Subscriptions::set_status((int) $sub['id'], 'canceled');
        }
        // The hourly sweep expires the now-unprotected job(s) per the admin setting.
    }

    /** Idempotent. Marks the order refunded and immediately expires its job's feature (if any). */
    public function refund(array $event): void
    {
        $order_id = isset($event['order_id']) ? (int) $event['order_id'] : 0;
        if ($order_id <= 0) {
            return;
        }
        $order = Remjobs_Orders::get($order_id);
        if (!$order || $order['status'] === 'refunded') {
            return;
        } // idempotent guard

        Remjobs_Orders::update($order_id, array('status' => 'refunded'));

        if (!empty($order['job_id'])) {
            $this->featured->expire_feature((int) $order['job_id']);
        }
    }

    /** @return int[] job ids covered by a subscription (per-job: its job; account: all the user's featured jobs) */
    private function covered_job_ids(array $sub): array
    {
        if ((string) $sub['scope'] === 'account') {
            // reuse featured engine's counter query intent — list featured job ids for the user
            return get_posts(array(
                'post_type'      => 'remjobs_jobs',
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'posts_per_page' => -1,
                'author'         => (int) $sub['user_id'],
                'meta_query'     => array(array('key' => '_remjobs_featured', 'value' => '1')),
            ));
        }
        return !empty($sub['job_id']) ? array((int) $sub['job_id']) : array();
    }
}

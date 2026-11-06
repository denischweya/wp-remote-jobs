<?php
/**
 * Checkout coordinator: validates a feature-upgrade request, creates a
 * pending order with a server-resolved amount, and asks the chosen gateway
 * for a hosted redirect URL.
 *
 * @package Remote_Jobs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Remjobs_Checkout
{
    /** @var Remjobs_Payment_Gateway[] */
    private $gateways;

    /** @var Remjobs_Featured */
    private $featured;

    public function __construct(array $gateways, Remjobs_Featured $featured)
    {
        $this->gateways = $gateways;
        $this->featured = $featured;
    }

    /** @return string redirect URL; throws on validation/gateway error */
    public function start(int $user_id, int $job_id, string $plan_id, string $gateway_id): string
    {
        $plan = Remjobs_Plans::get($plan_id);
        if (!$plan) {
            throw new Exception(__('Unknown plan.', 'remote-jobs'));
        }
        if (!isset($this->gateways[$gateway_id]) || !$this->gateways[$gateway_id]->is_configured()) {
            throw new Exception(__('Payment method unavailable.', 'remote-jobs'));
        }
        $job = get_post($job_id);
        if (!$job || $job->post_type !== 'remjobs_jobs' || (int) $job->post_author !== $user_id) {
            throw new Exception(__('You cannot pay for this job.', 'remote-jobs'));
        }
        if ($plan_id === 'monthly' && $plan['scope'] === 'account' && !$this->featured->can_feature($user_id)) {
            throw new Exception(__('You have reached your featured-listing limit.', 'remote-jobs'));
        }

        $order_id = Remjobs_Orders::create(array(
            'user_id'  => $user_id,
            'job_id'   => $job_id,
            'plan'     => $plan_id,
            'gateway'  => $gateway_id,
            'amount'   => $plan['price'],
            'currency' => $plan['currency'],
        ));
        $order = Remjobs_Orders::get($order_id);
        $return = add_query_arg(array(
            'remjobs_return' => $order['order_key'],
            '_wpnonce'       => wp_create_nonce('remjobs_return_' . $order['order_key']),
        ), $this->account_url());
        $cancel = add_query_arg(array('remjobs_cancel' => '1'), $this->account_url());

        return $this->gateways[$gateway_id]->create_checkout($order, $plan, $return, $cancel);
    }

    /**
     * Where to return the user to after checkout. Phase 4 points this at the
     * My Jobs page; for now it's the home page. Filterable for future phases.
     */
    protected function account_url(): string
    {
        return home_url('/');
    }
}

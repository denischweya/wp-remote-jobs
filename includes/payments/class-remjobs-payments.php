<?php
/**
 * Payments orchestrator: loads the payment subsystem and wires its hooks.
 *
 * @package Remote_Jobs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Remjobs_Payments
{
    /** @var Remjobs_Payment_Settings */
    private $settings;

    /** @var Remjobs_Featured */
    private $featured;

    /** @var Remjobs_Payment_Gateway[] */
    private $gateways;

    /** @var Remjobs_Fulfillment */
    private $fulfillment;

    /** @var Remjobs_Checkout */
    private $checkout;

    /** @var Remjobs_Webhooks */
    private $webhooks;

    /** @var Remjobs_Payment_Ui */
    private $ui;

    public function __construct()
    {
        $base = REMJOBS_PLUGIN_PATH . 'includes/payments/';
        require_once $base . 'class-remjobs-payment-settings.php';
        require_once $base . 'class-remjobs-plans.php';
        require_once $base . 'class-remjobs-orders.php';
        require_once $base . 'class-remjobs-subscriptions.php';
        require_once $base . 'class-remjobs-featured.php';
        require_once $base . 'interface-remjobs-gateway.php';
        require_once $base . 'class-remjobs-fulfillment.php';
        require_once $base . 'class-remjobs-checkout.php';
        require_once $base . 'class-remjobs-webhooks.php';
        require_once $base . 'class-remjobs-payment-ui.php';
        require_once $base . 'gateways/class-remjobs-gateway-stripe.php';
        require_once $base . 'gateways/class-remjobs-gateway-paypal.php';
        require_once REMJOBS_PLUGIN_PATH . 'includes/class-remjobs-activator.php';

        $this->settings = new Remjobs_Payment_Settings();
        $this->featured = new Remjobs_Featured();

        $this->gateways = array(
            'stripe' => new Remjobs_Gateway_Stripe(),
            'paypal' => new Remjobs_Gateway_PayPal(),
        );

        $this->fulfillment = new Remjobs_Fulfillment($this->featured);
        $this->checkout = new Remjobs_Checkout($this->gateways, $this->featured);
        $this->webhooks = new Remjobs_Webhooks($this->gateways, $this->fulfillment);
        $this->ui = new Remjobs_Payment_Ui($this->checkout, $this->gateways, $this->featured);
    }

    public function init(): void
    {
        $GLOBALS['remjobs_payments'] = $this;

        add_action('plugins_loaded', array($this, 'maybe_upgrade_schema'), 20);
        $this->settings->register();

        add_action(Remjobs_Featured::CRON_HOOK, array($this->featured, 'run_expiry_sweep'));
        add_action('init', array($this, 'maybe_schedule_cron'));
        add_action('init', array($this, 'maybe_migrate_featured'));
        add_action('rest_api_init', array($this->webhooks, 'register_routes'));
        $this->ui->register();
    }

    /**
     * Checkout coordinator accessor, used by Phase 4 UI (submit/My Jobs).
     */
    public function checkout(): Remjobs_Checkout
    {
        return $this->checkout;
    }

    /**
     * Payment UI accessor, used by tests and the Submit Job integration.
     */
    public function ui(): Remjobs_Payment_Ui
    {
        return $this->ui;
    }

    /**
     * Gateway choices for the Submit Job form, delegating to the UI.
     */
    public function ui_gateway_choices(): array
    {
        return $this->ui->enabled_gateway_choices();
    }

    /**
     * Recreate the payments tables when the stored schema version is behind.
     */
    public function maybe_upgrade_schema(): void
    {
        if (get_option('remjobs_db_version') !== REMJOBS_DB_VERSION) {
            Remjobs_Activator::create_tables();
        }
    }

    /**
     * Self-heal the hourly featured-expiry cron if it is missing.
     */
    public function maybe_schedule_cron(): void
    {
        if (!wp_next_scheduled(Remjobs_Featured::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', Remjobs_Featured::CRON_HOOK);
        }
    }

    /**
     * One-time migration: backfill the canonical `_remjobs_featured` flag
     * from the legacy `_remjobs_is_premium` flag. Guarded so it only runs once.
     */
    public function maybe_migrate_featured(): void
    {
        if (get_option('remjobs_featured_migrated')) {
            return;
        }

        $ids = get_posts(array(
            'post_type'      => 'remjobs_jobs',
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'   => '_remjobs_is_premium',
                    'value' => '1',
                ),
            ),
        ));

        foreach ($ids as $job_id) {
            update_post_meta((int) $job_id, '_remjobs_featured', '1');
        }

        update_option('remjobs_featured_migrated', '1');
    }
}

/**
 * Global accessor for the booted payments orchestrator. Returns null if the
 * orchestrator has not been constructed/init() yet (e.g. direct `eval` tests
 * that only load individual classes).
 */
function remjobs_payments()
{
    return isset($GLOBALS['remjobs_payments']) ? $GLOBALS['remjobs_payments'] : null;
}

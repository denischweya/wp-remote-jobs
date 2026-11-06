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

    public function __construct()
    {
        $base = REMJOBS_PLUGIN_PATH . 'includes/payments/';
        require_once $base . 'class-remjobs-payment-settings.php';
        require_once $base . 'class-remjobs-plans.php';
        require_once $base . 'class-remjobs-orders.php';
        require_once $base . 'class-remjobs-subscriptions.php';
        require_once REMJOBS_PLUGIN_PATH . 'includes/class-remjobs-activator.php';

        $this->settings = new Remjobs_Payment_Settings();
    }

    public function init(): void
    {
        add_action('plugins_loaded', array($this, 'maybe_upgrade_schema'), 20);
        $this->settings->register();
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
}

<?php
// tests/wpcli/test-bootstrap.php
require __DIR__ . '/_assert.php';

// Simulate a stale/missing schema version, then let the orchestrator self-heal.
delete_option('remjobs_db_version');
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/class-remjobs-activator.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payments.php';

$p = new Remjobs_Payments();
remjobs_t_assert(class_exists('Remjobs_Plans'), 'plans loaded');
remjobs_t_assert(class_exists('Remjobs_Orders'), 'orders loaded');
remjobs_t_assert(class_exists('Remjobs_Subscriptions'), 'subscriptions loaded');
remjobs_t_assert(class_exists('Remjobs_Payment_Settings'), 'settings loaded');

$p->maybe_upgrade_schema();
remjobs_t_assert(get_option('remjobs_db_version') === REMJOBS_DB_VERSION, 'schema self-healed to current version');

remjobs_t_done();

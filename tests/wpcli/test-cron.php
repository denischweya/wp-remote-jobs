<?php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payments.php';
$p = new Remjobs_Payments();
$p->maybe_schedule_cron();
remjobs_t_assert(wp_next_scheduled(Remjobs_Featured::CRON_HOOK) !== false, 'expiry cron scheduled');
remjobs_t_assert(class_exists('Remjobs_Featured'), 'featured class loaded by orchestrator');
remjobs_t_done();

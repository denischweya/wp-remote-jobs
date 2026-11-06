<?php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payments.php';
delete_option('remjobs_featured_migrated');
$legacy = wp_insert_post(array('post_type'=>'remjobs_jobs','post_status'=>'publish','post_title'=>'legacy premium','post_author'=>1));
update_post_meta($legacy, '_remjobs_is_premium', '1'); // old flag only
$p = new Remjobs_Payments();
$p->maybe_migrate_featured();
remjobs_t_assert(get_post_meta($legacy, '_remjobs_featured', true) === '1', 'legacy premium backfilled to featured');
remjobs_t_assert(get_option('remjobs_featured_migrated') === '1', 'migration flag set');
wp_delete_post($legacy, true);
remjobs_t_done();

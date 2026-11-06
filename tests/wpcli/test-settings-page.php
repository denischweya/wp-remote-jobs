<?php
// tests/wpcli/test-settings-page.php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payment-settings.php';

$s = new Remjobs_Payment_Settings();
remjobs_t_assert(method_exists($s, 'register'), 'has register()');
remjobs_t_assert(method_exists($s, 'render_page'), 'has render_page()');
remjobs_t_assert(method_exists($s, 'add_menu'), 'has add_menu()');
remjobs_t_assert(method_exists($s, 'register_settings'), 'has register_settings()');

$s->register();
remjobs_t_assert(has_action('admin_menu', array($s, 'add_menu')) !== false, 'admin_menu hooked');
remjobs_t_assert(has_action('admin_init', array($s, 'register_settings')) !== false, 'admin_init hooked');

// register_settings must register the option (version-stable check).
$s->register_settings();
global $wp_registered_settings;
remjobs_t_assert(isset($wp_registered_settings[Remjobs_Payment_Settings::OPTION]), 'option registered via Settings API');

remjobs_t_done();

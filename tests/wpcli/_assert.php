<?php
// tests/wpcli/_assert.php — shared assertions for `studio wp eval-file` scripts.
// Usage: require __DIR__ . '/_assert.php'; remjobs_t_assert($cond, 'label'); ... remjobs_t_done();

$GLOBALS['remjobs_t_fail'] = 0;

function remjobs_t_assert($cond, $label)
{
    if ($cond) {
        echo "PASS: {$label}\n";
    } else {
        $GLOBALS['remjobs_t_fail']++;
        echo "FAIL: {$label}\n";
    }
}

function remjobs_t_done()
{
    if ($GLOBALS['remjobs_t_fail'] > 0) {
        echo "RESULT: FAIL ({$GLOBALS['remjobs_t_fail']} failed)\n";
    } else {
        echo "RESULT: PASS\n";
    }
}

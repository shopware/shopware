<?php declare(strict_types=1);

require_once __DIR__ . '/lib/feature-flags.php';

echo shopware_major_lane_env($_SERVER['argv'][1] ?? '') . "\n";

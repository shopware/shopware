<?php declare(strict_types=1);

use Composer\Composer;

require_once __DIR__ . '/../vendor/autoload.php';

if (!extension_loaded('bcmath')) {
    echo 'The BC Checker requires the bcmath extension, please install it to run the BC Checker locally.';
    exit(1);
}

if (version_compare(Composer::getVersion(), '2.2.0', '<')) {
    echo 'The BC Checker requires at least Composer 2.2.0, please update your composer version to run the BC Checker locally.';
    exit(1);
}

<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * @deprecated tag:v6.5.0 - Script can be removed as 8.1 will be the min. version
 */
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    echo 'The BC Checker requires at least PHP 8.0, please update your PHP version to run the BC Checker locally.';
    exit(1);
}

if (!extension_loaded('bcmath')) {
    echo 'The BC Checker requires the bcmath extension, please install it to run the BC Checker locally.';
    exit(1);
}

if (version_compare(\Composer\Composer::getVersion(), '2.2.0', '<')) {
    echo 'The BC Checker requires at least Composer 2.2.0, please update your composer version to run the BC Checker locally.';
    exit(1);
}

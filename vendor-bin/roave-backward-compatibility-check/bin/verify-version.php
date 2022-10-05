<?php declare(strict_types=1);

/**
 * @deprecated tag:v6.5.0 - Script can be removed as 8.1 will be the min. version
 */
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    echo 'The BC Checker requires at least PHP 8.0, please update your PHP version to run the BC Checker locally.';
    exit(1);
}

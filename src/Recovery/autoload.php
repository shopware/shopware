<?php declare(strict_types=1);
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    $template = '%s: ';
    if (PHP_SAPI !== 'cli') {
        $template = '<h2>%s</h2>';
        header('Content-type: text/html; charset=utf-8', true, 503);
    }

    echo sprintf($template, 'Error');
    echo "Please execute \"composer install\" from the command line to install the required dependencies for Shopware 6\n";

    echo sprintf($template, 'Fehler');
    echo "Bitte führen Sie zuerst \"composer install\" aus um alle von Shopware 6 benötigten Abhängigkeiten zu installieren.\n";

    exit(1);
}
date_default_timezone_set(@date_default_timezone_get());

define('SW_PATH', dirname(__DIR__, 3));

/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require_once __DIR__ . '/vendor/autoload.php';

$autoloader->addPsr4(
    'Shopware\\Core\\', SW_PATH . '/vendor/shopware/core/'
);
$autoloader->addPsr4(
    'Shopware\\Storefront\\', SW_PATH . '/vendor/shopware/storefront/'
);
$autoloader->addPsr4(
    'Shopware\\Elasticsearch\\', SW_PATH . '/vendor/shopware/elasticsearch/'
);

return $autoloader;

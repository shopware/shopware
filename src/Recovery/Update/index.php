<?php declare(strict_types=1);

if (function_exists('opcache_reset')) {
    opcache_reset();
}

require_once __DIR__ . '/../autoload.php';

define('UPDATE_PATH', __DIR__);
$isManual = is_dir(SW_PATH . '/update-assets');

if ($isManual) {
    define('UPDATE_IS_MANUAL', true);
    define('UPDATE_FILES_PATH', null);
    define('UPDATE_ASSET_PATH', SW_PATH . '/update-assets');
    define('UPDATE_META_FILE', null);
} else {
    define('UPDATE_IS_MANUAL', false);
    define('UPDATE_FILES_PATH', SW_PATH . '/files/update/files');
    define('UPDATE_ASSET_PATH', SW_PATH . '/files/update/update-assets');
    define('UPDATE_META_FILE', SW_PATH . '/files/update/update.json');
}

use Shopware\Recovery\Update\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;

if (\PHP_SAPI === 'cli') {
    error_reporting(-1);
    ini_set('display_errors', '1');

    $input = new ArgvInput();
    $env = $input->getParameterOption(['--env', '-e'], 'production');

    $application = new Application($env);

    return $application->run($input);
}

$app = require __DIR__ . '/src/app.php';
$app->run();

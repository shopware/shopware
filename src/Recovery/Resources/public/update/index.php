<?php declare(strict_types=1);

$rootDir = dirname(__DIR__, 3);

if (file_exists($rootDir . '/vendor/shopware/recovery/Update/index.php')) {
    require_once $rootDir . '/vendor/shopware/recovery/Update/index.php';
} elseif (file_exists($rootDir . '/vendor/shopware/platform/src/Recovery/Update/index.php')) {
    require_once $rootDir . '/vendor/shopware/platform/src/Recovery/Update/index.php';
} elseif (file_exists($rootDir . '/src/Recovery/Update/index.php')) {
    require_once $rootDir . '/src/Recovery/Update/index.php';
} else {
    // if the recovery is not yet there use the old recovery logic
    require_once __DIR__ . '/../common/autoload.php';

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

    error_reporting(\E_ALL & ~\E_DEPRECATED);
    ini_set('display_errors', '1');

    if (\PHP_SAPI === 'cli') {
        $input = new ArgvInput();
        $env = $input->getParameterOption(['--env', '-e'], 'production');

        $application = new Application($env);

        return $application->run($input);
    }

    $app = require __DIR__ . '/src/app.php';
    $app->run();
}

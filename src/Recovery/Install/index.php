<?php declare(strict_types=1);

if (function_exists('opcache_reset')) {
    opcache_reset();
}

$parent = dirname(__DIR__, 2);
// root/platform/src/Recovery and root/vendor/shopware/recovery
$rootDir = dirname($parent, 2);
if (basename(dirname($rootDir)) === 'vendor') {
    // root/vendor/shopware/platform/src/Recovery
    $rootDir = dirname($rootDir, 2);
}
if (!is_dir($rootDir . '/vendor') && is_dir(dirname($parent) . '/vendor')) {
    // platform/src/Recovery -> platform only
    $rootDir = dirname($parent);
}

$lockFile = $rootDir . '/install.lock';

if (is_file($lockFile)) {
    header('Content-type: text/html; charset=utf-8', true, 503);
    echo '<br /><h4>Der Installer wurde bereits ausgeführt.</h4>Wenn Sie den Installationsvorgang erneut ausführen möchten, löschen Sie die Datei install.lock!<br /><br /><br />';
    echo '<h4>The installation process has already been finished.</h4>If you want to run the installation process again, delete the file install.lock!';
    exit;
}

// Check the minimum required php version
if (\PHP_VERSION_ID < 70403) {
    header('Content-type: text/html; charset=utf-8', true, 503);
    echo '<h2>Fehler</h2>';
    echo 'Auf Ihrem Server läuft PHP version ' . \PHP_VERSION . ', Shopware 6 benötigt mindestens PHP 7.4.3.';
    echo '<h2>Error</h2>';
    echo 'Your server is running PHP version ' . \PHP_VERSION . ' but Shopware 6 requires at least PHP 7.4.3.';
    exit;
}

error_reporting(\E_ALL & ~\E_DEPRECATED);
ini_set('display_errors', '1');
date_default_timezone_set('UTC');
set_time_limit(0);

use Shopware\Core\HttpKernel;
use Shopware\Recovery\Install\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__) . '/autoload.php';

$classLoader = require $rootDir . '/vendor/autoload.php';
$kernel = new HttpKernel('prod', false, $classLoader);
$kernel->setPluginLoader(new \Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader($classLoader));

if (file_exists($rootDir . '/.env')) {
    (new Dotenv())->usePutenv()->bootEnv($rootDir . '/.env');
}

if (!\Shopware\Core\DevOps\Environment\EnvironmentHelper::hasVariable('DATABASE_URL')) {
    // Dummy setting to be able to boot the kernel
    $_SERVER['DATABASE_URL'] = 'mysql://_placeholder.test';
}

if (\PHP_SAPI === 'cli') {
    $input = new ArgvInput();
    $env = $input->getParameterOption(['--env', '-e'], 'production');

    return (new Application($env, $kernel->getKernel()))->run($input);
}

//the execution time will be increased, because the import can take a while
ini_set('max_execution_time', '120');

require_once __DIR__ . '/src/app.php';
getApplication($kernel->getKernel())->run();

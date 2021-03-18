<?php declare(strict_types=1);

use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Symfony\Component\Dotenv\Dotenv;

function getProjectDir(): string
{
    if (isset($_SERVER['PROJECT_ROOT']) && file_exists($_SERVER['PROJECT_ROOT'])) {
        return $_SERVER['PROJECT_ROOT'];
    }
    if (isset($_ENV['PROJECT_ROOT']) && file_exists($_ENV['PROJECT_ROOT'])) {
        return $_ENV['PROJECT_ROOT'];
    }

    $dir = $rootDir = __DIR__;
    while (!file_exists($dir . '/.env')) {
        if ($dir === dirname($dir)) {
            return $rootDir;
        }
        $dir = dirname($dir);
    }

    return $dir;
}

define('TEST_PROJECT_DIR', getProjectDir());

$loader = require TEST_PROJECT_DIR . '/vendor/autoload.php';
KernelLifecycleManager::prepare($loader);

if (!class_exists(Dotenv::class)) {
    throw new RuntimeException('APP_ENV environment variable is not defined. You need to define environment variables for configuration or add "symfony/dotenv" as a Composer dependency to load variables from a .env file.');
}
(new Dotenv())->usePutenv()->load(TEST_PROJECT_DIR . '/.env');

$testDb = ($_SERVER['DATABASE_URL'] ?? '') . '_test';
putenv('DATABASE_URL=' . $testDb);
$_ENV['DATABASE_URL'] = $testDb;
$_SERVER['DATABASE_URL'] = $testDb;

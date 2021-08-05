<?php declare(strict_types=1);

namespace Shopware\Core;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;

function getProjectDir(): string
{
    if (isset($_SERVER['PROJECT_ROOT']) && file_exists($_SERVER['PROJECT_ROOT'])) {
        return $_SERVER['PROJECT_ROOT'];
    }
    if (isset($_ENV['PROJECT_ROOT']) && file_exists($_ENV['PROJECT_ROOT'])) {
        return $_ENV['PROJECT_ROOT'];
    }

    if (file_exists('vendor')) {
        return (string) getcwd();
    }

    $dir = $rootDir = __DIR__;
    while (!file_exists($dir . '/vendor')) {
        if ($dir === \dirname($dir)) {
            return $rootDir;
        }
        $dir = \dirname($dir);
    }

    return $dir;
}

\define('TEST_PROJECT_DIR', getProjectDir());

$loader = require TEST_PROJECT_DIR . '/vendor/autoload.php';

if (!class_exists(Dotenv::class)) {
    throw new \RuntimeException('APP_ENV environment variable is not defined. You need to define environment variables for configuration or add "symfony/dotenv" as a Composer dependency to load variables from a .env file.');
}

$envFilePath = TEST_PROJECT_DIR . '/.env';
if (file_exists($envFilePath) || file_exists($envFilePath . '.dist')) {
    (new Dotenv())->usePutenv()->loadEnv($envFilePath);
}

$dbUrlParts = parse_url($_SERVER['DATABASE_URL'] ?? '') ?: [];

$testToken = getenv('TEST_TOKEN');
$dbUrlParts['path'] = ($dbUrlParts['path'] ?? 'root') . '_' . ($testToken ?: 'test');

$auth = isset($dbUrlParts['user']) ? ($dbUrlParts['user'] . (isset($dbUrlParts['pass']) ? (':' . $dbUrlParts['pass']) : '') . '@') : '';
$testDb = sprintf(
    '%s://%s%s%s%s%s',
    $dbUrlParts['scheme'] ?? 'mysql',
    $auth,
    $dbUrlParts['host'] ?? 'localhost',
    isset($dbUrlParts['port']) ? (':' . $dbUrlParts['port']) : '',
    $dbUrlParts['path'],
    isset($dbUrlParts['query']) ? ('?' . $dbUrlParts['query']) : ''
);

$_ENV['DATABASE_URL'] = $testDb;
$_SERVER['DATABASE_URL'] = $testDb;

KernelLifecycleManager::prepare($loader);
KernelLifecycleManager::bootKernel();
$kernel = KernelLifecycleManager::getKernel();

try {
    $connection = $kernel->getContainer()->get(Connection::class);
    $connection->executeQuery('SELECT 1 FROM `plugin`')->fetchAll();
    $exists = true;
} catch (\Throwable $exists) {
    $exists = false;
}

if (!$exists || ($_SERVER['FORCE_INSTALL'] ?? false)) {
    $application = new Application($kernel);
    $installCommand = $application->find('system:install');

    $output = new ConsoleOutput();

    $returnCode = $installCommand->run(
        new ArrayInput(
            [
                '--create-database' => true,
                '--force' => true,
                '--drop-database' => true,
                '--basic-setup' => true,
                '--no-assign-theme' => true,
            ],
            $installCommand->getDefinition()
        ),
        $output
    );
    if ($returnCode !== 0) {
        throw new \RuntimeException('system:install failed');
    }

    KernelLifecycleManager::bootKernel(false);
}

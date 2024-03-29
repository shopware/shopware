<?php declare(strict_types=1);
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\TestBootstrapper;
use Symfony\Component\Dotenv\Dotenv;

$classloader = require __DIR__ . '/../../../vendor/autoload.php';

// Boot Kernel once to initialize the feature flags
KernelLifecycleManager::prepare($classloader);

KernelLifecycleManager::bootKernel();
KernelLifecycleManager::ensureKernelShutdown();

// Boot env
if (!class_exists(Dotenv::class)) {
    throw new RuntimeException('APP_ENV environment variable is not defined. You need to define environment variables for configuration or add "symfony/dotenv" as a Composer dependency to load variables from a .env file.');
}

$envFilePath = (new TestBootstrapper())->getProjectDir() . '/.env';
if (is_file($envFilePath) || is_file($envFilePath . '.dist') || is_file($envFilePath . '.local.php')) {
    (new Dotenv())->usePutenv()->bootEnv($envFilePath);
}

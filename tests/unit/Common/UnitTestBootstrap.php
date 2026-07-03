<?php declare(strict_types=1);
use PHPUnit\TextUI\Configuration\SourceFilter;
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

/*
 * Eagerly build PHPUnit's source map (full <source> tree traversal). It is otherwise built
 * lazily while classifying the FIRST triggered deprecation/notice — billing seconds (native fs)
 * to minutes (Docker bind mount) to whichever test happens to trigger it, which poisons the
 * slow-test-detector output with a wandering false entry. @internal API, so fail soft.
 */
if (class_exists(SourceFilter::class)) {
    try {
        SourceFilter::instance();
    } catch (Throwable) {
        // pre-warming is an optimization only — never break the suite over it
    }
}

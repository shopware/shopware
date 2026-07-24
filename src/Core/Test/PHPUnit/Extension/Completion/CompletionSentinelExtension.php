<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\Completion;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\Completion\Subscriber\WriteSentinelSubscriber;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Writes a sentinel file when the test runner finishes executing tests. CI
 * fails a job whose PHPUnit process exited without the sentinel: code under
 * test terminating the process (e.g. exit(0) from a console Application with
 * auto-exit) skips the ExecutionFinished event and yields a green step with
 * most of the suite never run. Inert unless PHPUNIT_COMPLETION_SENTINEL is
 * set to the target path, so local runs are unaffected.
 *
 * @internal
 */
#[Package('framework')]
class CompletionSentinelExtension implements Extension
{
    public const SENTINEL_PATH_VAR = 'PHPUNIT_COMPLETION_SENTINEL';

    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $path = EnvironmentHelper::getVariable(self::SENTINEL_PATH_VAR);

        if (!\is_string($path) || $path === '') {
            return;
        }

        // a stale sentinel from a previous run must not mask a killed run
        (new Filesystem())->remove($path);

        $facade->registerSubscribers(new WriteSentinelSubscriber($path));
    }
}

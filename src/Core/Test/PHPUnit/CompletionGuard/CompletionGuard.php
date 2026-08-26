<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\CompletionGuard;

use PHPUnit\Event\EventFacadeIsSealedException;
use PHPUnit\Event\Facade;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\CompletionGuard\Subscriber\MarkExecutionFinishedSubscriber;
use Shopware\Core\Test\PHPUnit\CompletionGuard\Subscriber\MarkExecutionStartedSubscriber;

/**
 * Forces a non-zero exit code when the PHPUnit process terminates before the
 * test runner finished: code under test terminating the process (e.g. exit(0)
 * from a console Application with auto-exit) skips the rest of the suite and
 * would otherwise yield a green run with most of the tests never executed.
 *
 * Registered from TestBootstrapper::bootstrap(), which PHPUnit runs as its
 * bootstrap script before the event facade is sealed — so every consumer of
 * the bootstrapper (core suites, plugins, projects) is protected without any
 * phpunit.xml wiring. The shutdown function is armed by ExecutionStarted and
 * disarmed by ExecutionFinished, so commands that never execute tests
 * (--list-tests, ...) and completed runs (including --stop-on-failure) are
 * unaffected. Isolated child processes never see ExecutionStarted and stay
 * inert. A fatal error already yields a non-zero exit code and keeps it.
 *
 * @internal
 */
#[Package('framework')]
class CompletionGuard
{
    public static bool $executionStarted = false;

    public static bool $executionFinished = false;

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered || !class_exists(Facade::class)) {
            return;
        }

        try {
            $facade = Facade::instance();
            $facade->registerSubscriber(new MarkExecutionStartedSubscriber());
            $facade->registerSubscriber(new MarkExecutionFinishedSubscriber());
        } catch (EventFacadeIsSealedException) {
            // bootstrap() was called from inside an already-running test process; nothing to guard here
            return;
        }

        self::$registered = true;

        register_shutdown_function(static function (): void {
            if (!self::shouldForceFailure(self::$executionStarted, self::$executionFinished, error_get_last())) {
                return;
            }

            fwrite(
                \STDERR,
                \PHP_EOL . 'PHPUnit terminated before the test runner finished the suite — exit()/die() reached from code under test? Forcing a failure exit code.' . \PHP_EOL,
            );

            exit(1);
        });
    }

    /**
     * @param array{type: int, message: string, file: string, line: int}|null $lastError
     */
    public static function shouldForceFailure(bool $started, bool $finished, ?array $lastError): bool
    {
        if (!$started || $finished) {
            return false;
        }

        // a fatal error already produces a non-zero exit code; keep it instead of overriding with ours
        return $lastError === null || !\in_array($lastError['type'], [\E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR], true);
    }
}

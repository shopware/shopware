<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\EventHook;

use PHPUnit\Event\EventFacadeIsSealedException;
use PHPUnit\Event\Facade;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

/**
 * Clears the hooks of the test-environment event dispatcher before each test. Registered by the test bootstrap.
 *
 * It reacts to PreparationStarted, which PHPUnit emits for every test before setUp(), because Finished is only
 * emitted for tests that got past preparation: a hook registered in setUp() before a skip or a preparation error
 * would otherwise stay on the dispatcher for the next test.
 *
 * @internal
 */
#[Package('framework')]
class ResetEventHooksSubscriber implements PreparationStartedSubscriber
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered || !class_exists(Facade::class)) {
            return;
        }

        try {
            Facade::instance()->registerSubscriber(new self());
        } catch (EventFacadeIsSealedException) {
            // bootstrap() was called from inside an already-running test process; the outer one registered it
            return;
        }

        self::$registered = true;
    }

    public function notify(PreparationStarted $event): void
    {
        EventHookDispatcher::resetCurrent();
    }
}

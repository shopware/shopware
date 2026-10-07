<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\EventHook;

use PHPUnit\Event\EventFacadeIsSealedException;
use PHPUnit\Event\Facade;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

/**
 * Clears the hooks of the test-environment event dispatcher after each test. Registered by the test bootstrap.
 *
 * @internal
 */
#[Package('framework')]
class ResetEventHooksSubscriber implements FinishedSubscriber
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

    public function notify(Finished $event): void
    {
        EventHookDispatcher::resetCurrent();
    }
}

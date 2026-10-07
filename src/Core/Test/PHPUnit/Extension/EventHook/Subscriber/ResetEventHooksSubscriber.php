<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\EventHook\Subscriber;

use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

/**
 * @internal
 */
#[Package('framework')]
class ResetEventHooksSubscriber implements FinishedSubscriber
{
    public function notify(Finished $event): void
    {
        EventHookDispatcher::resetCurrent();
    }
}

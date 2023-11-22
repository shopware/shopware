<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\Datadog\Subscriber;

use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\Common\TimeKeeper;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\DatadogPayload;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\DatadogPayloadCollection;

/**
 * @internal
 */
#[Package('core')]
class TestFailedSubscriber implements FailedSubscriber
{
    public function __construct(
        private readonly TimeKeeper $timeKeeper,
        private readonly DatadogPayloadCollection $failedTests
    ) {
    }

    public function notify(Failed $event): void
    {
        $time = $event->telemetryInfo()->time();

        $duration = $this->timeKeeper->stop(
            $event->test()->id(),
            HRTime::fromSecondsAndNanoseconds(
                $time->seconds(),
                $time->nanoseconds(),
            ),
        );

        $payload = new DatadogPayload(
            'phpunit',
            'phpunit,test:failed',
            $event->asString(),
            'PHPUnit',
            $event->test()->id(),
            $duration->asFloat()
        );

        $this->failedTests->set($event->test()->id(), $payload);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\Datadog\Subscriber;

use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\Test\SkippedSubscriber;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\Common\TimeKeeper;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\DatadogPayload;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\DatadogPayloadCollection;

/**
 * @internal
 */
#[Package('core')]
class TestSkippedSubscriber implements SkippedSubscriber
{
    public function __construct(
        private readonly TimeKeeper $timeKeeper,
        private readonly DatadogPayloadCollection $skippedTests
    ) {
    }

    public function notify(Skipped $event): void
    {
        $time = $event->telemetryInfo()->time();

        $duration = $this->timeKeeper->stop(
            $event->test()->id(),
            HRTime::fromSecondsAndNanoseconds(
                $time->seconds(),
                $time->nanoseconds(),
            ),
        );

        if (str_contains($event->message(), 'Skipping feature test for flag')) {
            return;
        }

        $payload = new DatadogPayload(
            'phpunit',
            'phpunit,test:skipped',
            $event->asString(),
            'PHPUnit',
            $event->test()->id(),
            $duration->asFloat()
        );

        $this->skippedTests->set($event->test()->id(), $payload);
    }
}

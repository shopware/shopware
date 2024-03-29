<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\Extension\Datadog;

use PHPUnit\Event\Telemetry\Duration;
use PHPUnit\Event\Telemetry\GarbageCollectorStatus;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\MemoryUsage;
use PHPUnit\Event\Telemetry\Snapshot;
use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\DatadogPayload;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\DatadogPayloadCollection;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\Gateway\DatadogGateway;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\Subscriber\TestRunnerExecutionFinishedSubscriber;

/**
 * @internal
 */
#[CoversClass(TestRunnerExecutionFinishedSubscriber::class)]
class TestRunnerExecutionFinishedSubscriberTest extends TestCase
{
    private DatadogGateway&MockObject $gateway;

    protected function setUp(): void
    {
        $this->gateway = $this->createMock(DatadogGateway::class);
    }

    public function testNotifyWithEmptyCollection(): void
    {
        $failed = new DatadogPayloadCollection();
        $slow = new DatadogPayloadCollection();

        $event = $this->buildEvent();
        $this->gateway
            ->expects(static::once())
            ->method('sendLogs')
            ->with([]);

        $runner = new TestRunnerExecutionFinishedSubscriber(
            $failed,
            $slow,
            $this->gateway
        );

        $runner->notify($event);
    }

    public function testNotifyWithSameIdentifierForFailedAndSlow(): void
    {
        $failed = new DatadogPayloadCollection();
        $slow = new DatadogPayloadCollection();
        $eventId = 'Shopware\\Tests\\DevOps\\Core\\Test\\AFakeTest::testNothing';

        $failed->set($eventId, new DatadogPayload(
            'phpunit',
            'phpunit,test:failed',
            'Failed: (' . $eventId . ')',
            'PHPUnit',
            $eventId,
            0.0
        ));

        $slow->set($eventId, new DatadogPayload(
            'phpunit',
            'phpunit,test:slow',
            'Slow test: (' . $eventId . ')',
            'PHPUnit',
            $eventId,
            1.4308640000000001
        ));

        $event = $this->buildEvent();

        $this->gateway
            ->expects(static::once())
            ->method('sendLogs')
            ->with(
                [
                    [
                        'ddsource' => 'phpunit',
                        'ddtags' => 'phpunit,test:failed',
                        'message' => 'Failed: (' . $eventId . ')',
                        'service' => 'PHPUnit',
                        'test-description' => $eventId,
                        'test-duration' => 0.0,
                    ],
                    [
                        'ddsource' => 'phpunit',
                        'ddtags' => 'phpunit,test:slow',
                        'message' => 'Slow test: (' . $eventId . ')',
                        'service' => 'PHPUnit',
                        'test-description' => $eventId,
                        'test-duration' => 1.4308640000000001,
                    ],
                ]
            );

        $runner = new TestRunnerExecutionFinishedSubscriber(
            $failed,
            $slow,
            $this->gateway
        );

        $runner->notify($event);
    }

    private function buildEvent(): ExecutionFinished
    {
        $time = HRTime::fromSecondsAndNanoseconds(0, 0);
        $duration = Duration::fromSecondsAndNanoseconds(0, 0);
        $memory = MemoryUsage::fromBytes(0);
        $gc = new GarbageCollectorStatus(
            0,
            0,
            0,
            0,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );
        $snap = new Snapshot($time, $memory, $memory, $gc);

        return new ExecutionFinished(new Info($snap, $duration, $memory, $duration, $memory));
    }
}

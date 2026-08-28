<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\CompletionGuard\Subscriber;

use PHPUnit\Event\Code\TestCollection;
use PHPUnit\Event\Telemetry\Duration;
use PHPUnit\Event\Telemetry\GarbageCollectorStatus;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\MemoryUsage;
use PHPUnit\Event\Telemetry\Snapshot;
use PHPUnit\Event\TestRunner\ExecutionStarted;
use PHPUnit\Event\TestSuite\TestSuiteWithName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\CompletionGuard\CompletionGuard;
use Shopware\Core\Test\PHPUnit\CompletionGuard\Subscriber\MarkExecutionStartedSubscriber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MarkExecutionStartedSubscriber::class)]
class MarkExecutionStartedSubscriberTest extends TestCase
{
    private bool $previousState;

    protected function setUp(): void
    {
        $this->previousState = CompletionGuard::$executionStarted;
    }

    protected function tearDown(): void
    {
        // the guard is armed for the very suite running this test; restore whatever state it had
        CompletionGuard::$executionStarted = $this->previousState;
    }

    public function testNotifyMarksExecutionAsStarted(): void
    {
        CompletionGuard::$executionStarted = false;

        (new MarkExecutionStartedSubscriber())->notify($this->buildEvent());

        static::assertTrue(CompletionGuard::$executionStarted);
    }

    private function buildEvent(): ExecutionStarted
    {
        $time = HRTime::fromSecondsAndNanoseconds(0, 0);
        $duration = Duration::fromSecondsAndNanoseconds(0, 0);
        $memory = MemoryUsage::fromBytes(0);
        $gc = new GarbageCollectorStatus(0, 0, 0, 0, 0.0, 0.0, 0.0, 0.0, false, false, false, 0);
        $snap = new Snapshot($time, $memory, $memory, $gc);

        return new ExecutionStarted(
            new Info($snap, $duration, $memory, $duration, $memory),
            new TestSuiteWithName('suite', 0, TestCollection::fromArray([])),
        );
    }
}

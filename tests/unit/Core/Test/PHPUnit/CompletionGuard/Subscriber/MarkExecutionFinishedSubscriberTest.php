<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\CompletionGuard\Subscriber;

use PHPUnit\Event\Telemetry\Duration;
use PHPUnit\Event\Telemetry\GarbageCollectorStatus;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\MemoryUsage;
use PHPUnit\Event\Telemetry\Snapshot;
use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\CompletionGuard\CompletionGuard;
use Shopware\Core\Test\PHPUnit\CompletionGuard\Subscriber\MarkExecutionFinishedSubscriber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MarkExecutionFinishedSubscriber::class)]
class MarkExecutionFinishedSubscriberTest extends TestCase
{
    private bool $previousState;

    protected function setUp(): void
    {
        $this->previousState = CompletionGuard::$executionFinished;
    }

    protected function tearDown(): void
    {
        // leaving this true would disarm the guard for the very suite running this test; restore it
        CompletionGuard::$executionFinished = $this->previousState;
    }

    public function testNotifyMarksExecutionAsFinished(): void
    {
        CompletionGuard::$executionFinished = false;

        (new MarkExecutionFinishedSubscriber())->notify($this->buildEvent());

        static::assertTrue(CompletionGuard::$executionFinished);
    }

    private function buildEvent(): ExecutionFinished
    {
        $time = HRTime::fromSecondsAndNanoseconds(0, 0);
        $duration = Duration::fromSecondsAndNanoseconds(0, 0);
        $memory = MemoryUsage::fromBytes(0);
        $gc = new GarbageCollectorStatus(0, 0, 0, 0, 0.0, 0.0, 0.0, 0.0, false, false, false, 0);
        $snap = new Snapshot($time, $memory, $memory, $gc);

        return new ExecutionFinished(new Info($snap, $duration, $memory, $duration, $memory));
    }
}

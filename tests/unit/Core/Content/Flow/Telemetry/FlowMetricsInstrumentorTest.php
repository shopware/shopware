<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Telemetry\FlowMetricsInstrumentor;
use Shopware\Core\Content\Flow\Telemetry\TriggerGroupResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Telemetry\CollectingMeter;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(FlowMetricsInstrumentor::class)]
class FlowMetricsInstrumentorTest extends TestCase
{
    private CollectingMeter $meter;

    public function testSuccessfulExecutionEmitsDurationWithResolvedGroup(): void
    {
        $this->createInstrumentor()->measureExecution($this->createFlow('checkout.order.placed'), fn () => null);

        $duration = $this->meter->getMetric('flow.execution.duration');
        static::assertIsFloat($duration->value);
        static::assertGreaterThanOrEqual(0, $duration->value);
        static::assertSame(['trigger_group' => 'trigger_group_label:checkout.order.placed', 'result' => 'success'], $duration->labels);
    }

    public function testCallbackIsInvokedExactlyOnce(): void
    {
        $calls = 0;

        $this->createInstrumentor()->measureExecution($this->createFlow('checkout.order.placed'), function () use (&$calls): void {
            ++$calls;
        });

        static::assertSame(1, $calls);
    }

    public function testFailingCallbackIsRethrownAndDurationRecordedAsFailed(): void
    {
        $thrown = null;

        try {
            $this->createInstrumentor()->measureExecution($this->createFlow('checkout.order.placed'), function (): void {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        static::assertNotNull($thrown, 'the original exception must propagate');
        static::assertSame('boom', $thrown->getMessage());

        $duration = $this->meter->getMetric('flow.execution.duration');
        $labels = $duration->labels;
        static::assertIsArray($labels);
        static::assertSame('failed', $labels['result']);
        static::assertSame('trigger_group_label:checkout.order.placed', $labels['trigger_group']);
    }

    private function createInstrumentor(): FlowMetricsInstrumentor
    {
        $this->meter = new CollectingMeter();

        // Pass-through resolver stub: echoes the event name back with a fixed prefix, so it's easy to validate
        $triggerGroupResolver = static::createStub(TriggerGroupResolver::class);
        $triggerGroupResolver->method('resolve')->willReturnCallback(
            static fn (string $eventName): string => 'trigger_group_label:' . $eventName
        );

        return new FlowMetricsInstrumentor($this->meter, $triggerGroupResolver);
    }

    private function createFlow(string $eventName): StorableFlow
    {
        return new StorableFlow($eventName, Context::createDefaultContext());
    }
}

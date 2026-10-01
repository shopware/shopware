<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Telemetry\MailGroupResolver;
use Shopware\Core\Content\Mail\Telemetry\MailMetricsInstrumentor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MailMetricsInstrumentor::class)]
class MailMetricsInstrumentorTest extends TestCase
{
    /**
     * @var list<ConfiguredMetric>
     */
    private array $emitted = [];

    public function testSuccessfulSendEmitsDurationAndCountWithResolvedGroup(): void
    {
        $this->createInstrumentor()->measureSend('checkout.order.placed', fn () => null);

        $duration = $this->getMetric('mail.send.duration');
        static::assertInstanceOf(ConfiguredMetric::class, $duration);
        static::assertIsFloat($duration->value);
        static::assertGreaterThanOrEqual(0.0, $duration->value);
        static::assertSame(['result' => 'sent'], $duration->labels);

        $count = $this->getMetric('mail.send.count');
        static::assertInstanceOf(ConfiguredMetric::class, $count);
        static::assertSame(1, $count->value);
        static::assertSame(['mail_group' => 'mail_group_label:checkout.order.placed', 'result' => 'sent'], $count->labels);
    }

    public function testEventNameIsForwardedToResolver(): void
    {
        // The instrumentor forwards the event name (here null, sent outside a flow) through the resolver
        // and labels with its output; mapping the event to a bounded group is MailGroupResolver's job.
        $this->createInstrumentor()->measureSend(null, fn () => null);

        $count = $this->getMetric('mail.send.count');
        static::assertInstanceOf(ConfiguredMetric::class, $count);
        static::assertSame('mail_group_label:', $count->labels['mail_group']);
    }

    public function testSendClosureIsInvokedExactlyOnce(): void
    {
        $calls = 0;

        $this->createInstrumentor()->measureSend('checkout.order.placed', function () use (&$calls): void {
            ++$calls;
        });

        static::assertSame(1, $calls);
    }

    public function testFailingSendIsRethrownAndBothMetricsRecordedAsFailed(): void
    {
        $thrown = null;

        try {
            $this->createInstrumentor()->measureSend('checkout.order.placed', function (): void {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        static::assertNotNull($thrown, 'the original exception must propagate');
        static::assertSame('boom', $thrown->getMessage());

        $duration = $this->getMetric('mail.send.duration');
        static::assertInstanceOf(ConfiguredMetric::class, $duration);
        static::assertSame('failed', $duration->labels['result']);

        $count = $this->getMetric('mail.send.count');
        static::assertInstanceOf(ConfiguredMetric::class, $count);
        static::assertSame('failed', $count->labels['result']);
        static::assertSame('mail_group_label:checkout.order.placed', $count->labels['mail_group']);
    }

    private function getMetric(string $name): ?ConfiguredMetric
    {
        foreach ($this->emitted as $metric) {
            if ($metric->name === $name) {
                return $metric;
            }
        }

        return null;
    }

    private function createInstrumentor(): MailMetricsInstrumentor
    {
        $meter = static::createStub(Meter::class);
        $meter->method('emit')->willReturnCallback(function (ConfiguredMetric $metric): void {
            $this->emitted[] = $metric;
        });

        // Pass-through resolver stub: echoes the event name back with a fixed prefix, so it's easy to validate
        $mailGroupResolver = static::createStub(MailGroupResolver::class);
        $mailGroupResolver->method('resolve')->willReturnCallback(
            static fn (?string $eventName): string => 'mail_group_label:' . $eventName
        );

        return new MailMetricsInstrumentor($meter, $mailGroupResolver);
    }
}

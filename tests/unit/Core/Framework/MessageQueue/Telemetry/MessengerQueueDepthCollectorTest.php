<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Telemetry\MessengerQueueDepthCollector;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MessengerQueueDepthCollector::class)]
class MessengerQueueDepthCollectorTest extends TestCase
{
    public function testEmitsDepthPerCountableTransport(): void
    {
        $collector = new MessengerQueueDepthCollector(
            $this->locator([
                'async' => $this->countableTransport(5),
                'low_priority' => $this->countableTransport(0),
                'failed' => $this->countableTransport(2),
            ]),
            static::createStub(LoggerInterface::class)
        );

        static::assertSame(
            ['async' => 5, 'low_priority' => 0, 'failed' => 2],
            $this->depthsByTransport($collector)
        );
    }

    public function testSkipsTransportsReturnedByMessengerTransportPrefix(): void
    {
        // The receiver locator exposes each transport under both its service id and its configured name;
        // only the configured name must be counted.
        $async = $this->countableTransport(7);

        $collector = new MessengerQueueDepthCollector(
            new ServiceLocator([
                'messenger.transport.async' => static fn (): MessageCountAwareInterface => $async,
                'async' => static fn (): MessageCountAwareInterface => $async,
            ]),
            static::createStub(LoggerInterface::class)
        );

        static::assertSame(['async' => 7], $this->depthsByTransport($collector));
    }

    public function testSkipsTransportsThatCannotReportACount(): void
    {
        $collector = new MessengerQueueDepthCollector(
            $this->locator([
                'async' => $this->countableTransport(3),
                // no count support
                'nocount' => $this->uncountableTransport(),
            ]),
            static::createStub(LoggerInterface::class)
        );

        static::assertSame(['async' => 3], $this->depthsByTransport($collector));
    }

    public function testEmitsNothingWhenNoTransportIsCountable(): void
    {
        $collector = new MessengerQueueDepthCollector(
            $this->locator(['nocount' => $this->uncountableTransport()]),
            static::createStub(LoggerInterface::class)
        );

        static::assertSame([], iterator_to_array($collector->collect()));
    }

    public function testFailingTransportIsLoggedAndDoesNotStopTheOthers(): void
    {
        $failing = static::createStubForIntersectionOfInterfaces([MessageCountAwareInterface::class, ReceiverInterface::class]);
        $failing->method('getMessageCount')->willThrowException(new \RuntimeException('broker down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                static::stringContains('async'),
                static::callback(static fn (array $context): bool => $context['exception'] instanceof \RuntimeException)
            );

        $collector = new MessengerQueueDepthCollector(
            $this->locator([
                'async' => $failing,
                'failed' => $this->countableTransport(4),
            ]),
            $logger
        );

        static::assertSame(['failed' => 4], $this->depthsByTransport($collector));
    }

    /**
     * @param array<string, ReceiverInterface> $transports
     *
     * @return ServiceLocator<ReceiverInterface>
     */
    private function locator(array $transports): ServiceLocator
    {
        $factories = [];
        foreach ($transports as $name => $transport) {
            $factories[$name] = static fn (): ReceiverInterface => $transport;
        }

        return new ServiceLocator($factories);
    }

    /**
     * @return array<string, int>
     */
    private function depthsByTransport(MessengerQueueDepthCollector $collector): array
    {
        $depths = [];
        foreach ($collector->collect() as $metric) {
            static::assertInstanceOf(ConfiguredMetric::class, $metric);
            static::assertSame('messenger.queue.depth', $metric->name);
            static::assertArrayHasKey('transport', $metric->labels);

            $transport = $metric->labels['transport'];
            $value = $metric->value;
            static::assertIsString($transport);
            static::assertIsInt($value);

            $depths[$transport] = $value;
        }

        return $depths;
    }

    private function countableTransport(int $count): MessageCountAwareInterface&ReceiverInterface
    {
        return new class($count) implements ReceiverInterface, MessageCountAwareInterface {
            public function __construct(private readonly int $count)
            {
            }

            public function getMessageCount(): int
            {
                return $this->count;
            }

            public function get(): iterable
            {
                return [];
            }

            public function ack(Envelope $envelope): void
            {
            }

            public function reject(Envelope $envelope): void
            {
            }
        };
    }

    private function uncountableTransport(): ReceiverInterface
    {
        return new class implements ReceiverInterface {
            public function get(): iterable
            {
                return [];
            }

            public function ack(Envelope $envelope): void
            {
            }

            public function reject(Envelope $envelope): void
            {
            }
        };
    }
}

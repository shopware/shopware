<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Subscriber\TelemetryFlushListener;
use Shopware\Core\Framework\Telemetry\Metrics\Transport\TransportCollection;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TelemetryFlushListener::class)]
class TelemetryFlushListenerTest extends TestCase
{
    public function testSubscribesToKernelAndConsoleTerminate(): void
    {
        $events = TelemetryFlushListener::getSubscribedEvents();

        static::assertArrayHasKey(KernelEvents::TERMINATE, $events);
        static::assertArrayHasKey(ConsoleEvents::TERMINATE, $events);
        static::assertSame('flush', $events[KernelEvents::TERMINATE]);
        static::assertSame('flush', $events[ConsoleEvents::TERMINATE]);
    }

    public function testFlushIsCalledOnAllTransports(): void
    {
        $transport1 = $this->createMock(MetricTransportInterface::class);
        $transport1->expects($this->once())->method('flush');
        $transport2 = $this->createMock(MetricTransportInterface::class);
        $transport2->expects($this->once())->method('flush');

        $collection = $this->createTransportCollectionMock([$transport1, $transport2]);

        $listener = new TelemetryFlushListener($collection, $this->createMock(LoggerInterface::class));
        $listener->flush();
    }

    public function testTransportFailureDoesNotPreventOtherTransportsFromFlushing(): void
    {
        $transport1 = $this->createMock(MetricTransportInterface::class);
        $transport1->expects($this->once())
            ->method('flush')
            ->willThrowException(new \RuntimeException('flush failed'));
        $transport2 = $this->createMock(MetricTransportInterface::class);
        $transport2->expects($this->once())->method('flush');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                static::stringContains('Failed to flush metric transport'),
                static::arrayHasKey('exception')
            );

        $collection = $this->createTransportCollectionMock([$transport1, $transport2]);

        $listener = new TelemetryFlushListener($collection, $logger);
        $listener->flush();
    }

    /**
     * @param array<MetricTransportInterface> $transports
     *
     * @return TransportCollection<MetricTransportInterface>
     */
    private function createTransportCollectionMock(array $transports): TransportCollection
    {
        $collection = $this->createMock(TransportCollection::class);
        $collection->expects($this->once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator($transports));

        return $collection;
    }
}

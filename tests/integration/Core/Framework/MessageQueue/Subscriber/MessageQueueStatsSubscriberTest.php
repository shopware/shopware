<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\MessageQueue\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Increment\AbstractIncrementer;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Histogram;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\BarMessage;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\FooMessage;
use Shopware\Core\Framework\Test\Telemetry\Transport\TraceableTransport;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\MessageQueue\fixtures\NoHandlerMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\SerializedMessageStamp;

/**
 * @internal
 */
class MessageQueueStatsSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    public function testListener(): void
    {
        /** @var AbstractIncrementer $pool */
        $pool = $this->getContainer()
            ->get('shopware.increment.gateway.registry')
            ->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);

        $pool->reset('message_queue_stats');

        /** @var MessageBusInterface $bus */
        $bus = $this->getContainer()->get('messenger.bus.test_shopware');

        $bus->dispatch(new FooMessage());
        $bus->dispatch(new BarMessage());
        $bus->dispatch(new BarMessage());
        $bus->dispatch(new BarMessage());

        $stats = $pool->list('message_queue_stats');
        static::assertEquals(1, $stats[FooMessage::class]['count']);
        static::assertEquals(3, $stats[BarMessage::class]['count']);

        $this->runWorker();

        $stats = $pool->list('message_queue_stats');
        static::assertEquals(0, $stats[FooMessage::class]['count']);
        static::assertEquals(0, $stats[BarMessage::class]['count']);

        $bus->dispatch(new NoHandlerMessage());

        $stats = $pool->list('message_queue_stats');
        static::assertEquals(1, $stats[NoHandlerMessage::class]['count']);

        $this->runWorker();
        $stats = $pool->list('message_queue_stats');
        static::assertEquals(0, $stats[NoHandlerMessage::class]['count']);
    }

    public function testOnMessageReceivedSizeMetricEmitted(): void
    {
        Feature::skipTestIfInActive('TELEMETRY_METRICS', $this);

        $transport = $this->getContainer()->get(TraceableTransport::class);
        static::assertInstanceOf(TraceableTransport::class, $transport);
        $bus = $this->getContainer()->get('messenger.bus.test_shopware');
        static::assertInstanceOf(MessageBusInterface::class, $bus);

        $stamp = new SerializedMessageStamp('{"name": "John Doe"}');
        $message = new Envelope(new FooMessage(), [$stamp]);
        $transport->reset();
        $bus->dispatch($message);
        $this->runWorker();
        static::assertEquals(new Histogram(
            name: 'messenger.message.size',
            value: \strlen($stamp->getSerializedMessage()),
            description: 'Size of the message in bytes',
            unit: 'byte',
        ), $transport->getEmittedMetrics()[0]);
    }
}

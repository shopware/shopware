<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Increment\AbstractIncrementer;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\MessageQueue\Monitoring\MonitoringBusDecorator;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\TestMessage;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

class MonitoringBusDecoratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
        $registry = $this->getContainer()->get('shopware.increment.gateway.registry');
        $gateway = $registry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);

        $gateway->reset('message_queue_stats');
    }

    public function testItDispatchesToTheInnerBus(): void
    {
        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);
        $innerBus
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(function ($message) use ($testMsg) {
                static::assertInstanceOf(Envelope::class, $message);
                static::assertEquals($testMsg, $message->getMessage());

                return true;
            }))
            ->willReturn(new Envelope($testMsg));

        $decoratedBus = new MonitoringBusDecorator($innerBus, 'default', $this->getContainer()->get('shopware.increment.gateway.registry'));
        $decoratedBus->dispatch($testMsg);
    }

    public function testStampsArePassedThrough(): void
    {
        $testMsg = new TestMessage();
        $stamps = [$this->createMock(StampInterface::class)];

        $innerBus = $this->createMock(MessageBusInterface::class);
        $innerBus
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(function ($message) use ($testMsg) {
                static::assertInstanceOf(Envelope::class, $message);
                static::assertEquals($testMsg, $message->getMessage());

                return true;
            }), static::equalTo($stamps))
            ->willReturn(new Envelope($testMsg));

        $decoratedBus = new MonitoringBusDecorator($innerBus, 'default', $this->getContainer()->get('shopware.increment.gateway.registry'));
        $decoratedBus->dispatch($testMsg, $stamps);
    }

    public function testItCountsOutgoingMessages(): void
    {
        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);
        $innerBus
            ->method('dispatch')
            ->willReturn(new Envelope($testMsg, [new SentStamp('', 'default')]));

        $gatewayRegistry = $this->getContainer()->get('shopware.increment.gateway.registry');
        $decoratedBus = new MonitoringBusDecorator($innerBus, 'default', $gatewayRegistry);

        $decoratedBus->dispatch($testMsg);

        static::assertEquals(1, $this->getQueueSize($gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL), TestMessage::class));
    }

    public function testItCountsIncomingMessages(): void
    {
        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);

        $gatewayRegistry = $this->getContainer()->get('shopware.increment.gateway.registry');
        $decoratedBus = new MonitoringBusDecorator($innerBus, 'default', $gatewayRegistry);

        $gateway = $gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);
        $gateway->reset('message_queue_stats', TestMessage::class);
        $gateway->increment('message_queue_stats', TestMessage::class);

        $envelope = new Envelope($testMsg);
        $innerBus
            ->method('dispatch')
            ->willReturnCallback(function ($message, $stamps) {
                return Envelope::wrap($message, $stamps)->with(new ReceivedStamp('default'));
            });

        $decoratedBus->dispatch($envelope);

        static::assertEquals(0, $this->getQueueSize($gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL), TestMessage::class));
    }

    public function testOutgoingEnvelopes(): void
    {
        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);
        $innerBus
            ->method('dispatch')
            ->willReturn(new Envelope($testMsg, [new SentStamp('', 'default')]));

        $gatewayRegistry = $this->getContainer()->get('shopware.increment.gateway.registry');
        $decoratedBus = new MonitoringBusDecorator($innerBus, 'default', $gatewayRegistry);

        $envelope = new Envelope($testMsg);
        $decoratedBus->dispatch($envelope);

        static::assertEquals(1, $this->getQueueSize($gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL), \get_class($testMsg)));
    }

    public function testDoesNotIncrementWithNonDefaultName(): void
    {
        $testMsg = new TestMessage();

        $defaultTransportName = 'default';
        $innerBus = $this->createMock(MessageBusInterface::class);
        $innerBus
            ->method('dispatch')
            ->willReturn(new Envelope($testMsg, [new SentStamp('', 'not ' . $defaultTransportName)]));

        $gatewayRegistry = $this->getContainer()->get('shopware.increment.gateway.registry');
        $decoratedBus = new MonitoringBusDecorator($innerBus, $defaultTransportName, $gatewayRegistry);

        $envelope = new Envelope($testMsg);
        $decoratedBus->dispatch($envelope);

        static::assertEquals(0, $this->getQueueSize($gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL), \get_class($testMsg)));
    }

    public function testDoesNotIncrementWithoutSentStamp(): void
    {
        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);
        $innerBus
            ->method('dispatch')
            ->willReturn(new Envelope($testMsg));

        $defaultTransportName = 'default';
        $gatewayRegistry = $this->getContainer()->get('shopware.increment.gateway.registry');
        $decoratedBus = new MonitoringBusDecorator($innerBus, $defaultTransportName, $gatewayRegistry);

        $envelope = new Envelope($testMsg);
        $decoratedBus->dispatch($envelope);

        static::assertEquals(0, $this->getQueueSize($gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL), TestMessage::class));
    }

    public function testDoesNotDecrementWithNonDefaultName(): void
    {
        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);

        $defaultTransportName = 'default';
        $gatewayRegistry = $this->getContainer()->get('shopware.increment.gateway.registry');
        $decoratedBus = new MonitoringBusDecorator($innerBus, $defaultTransportName, $gatewayRegistry);

        $gateway = $gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);
        $gateway->reset('message_queue_stats', TestMessage::class);
        $gateway->increment('message_queue_stats', TestMessage::class);

        $envelope = new Envelope($testMsg);
        $innerBus
            ->method('dispatch')
            ->willReturnCallback(function ($message, $stamps) {
                return Envelope::wrap($message, $stamps)->with(new ReceivedStamp('not default'));
            });

        $decoratedBus->dispatch($envelope);

        static::assertEquals(1, $this->getQueueSize($gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL), \get_class($testMsg)));
    }

    public function testDoesNotDecrementWithoutReceivedStamp(): void
    {
        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);

        $defaultTransportName = 'default';
        /** @var IncrementGatewayRegistry $gatewayRegistry */
        $gatewayRegistry = $this->getContainer()->get('shopware.increment.gateway.registry');
        $decoratedBus = new MonitoringBusDecorator($innerBus, $defaultTransportName, $gatewayRegistry);

        $gateway = $gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);
        $gateway->reset('message_queue_stats', TestMessage::class);
        $gateway->increment('message_queue_stats', TestMessage::class);

        $envelope = new Envelope($testMsg);
        $innerBus
            ->method('dispatch')
            ->willReturnCallback(function ($message, $stamps) {
                return Envelope::wrap($message, $stamps);
            });

        $decoratedBus->dispatch($envelope);

        static::assertEquals(1, $this->getQueueSize($gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL), \get_class($testMsg)));
    }

    private function getQueueSize(AbstractIncrementer $gateway, string $name): ?int
    {
        $records = $gateway->list('message_queue_stats');

        foreach ($records as $record) {
            if ($record['key'] === $name) {
                return (int) $record['count'];
            }
        }

        return null;
    }
}

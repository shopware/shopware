<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\Monitoring\AbstractMonitoringGateway;
use Shopware\Core\Framework\MessageQueue\Monitoring\ArrayMonitoringGateway;
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

        $decoratedBus = new MonitoringBusDecorator($innerBus, 'default', new ArrayMonitoringGateway());
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

        $decoratedBus = new MonitoringBusDecorator($innerBus, 'default', new ArrayMonitoringGateway());
        $decoratedBus->dispatch($testMsg, $stamps);
    }

    public function testItCountsOutgoingMessages(): void
    {
        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);
        $innerBus
            ->method('dispatch')
            ->willReturn(new Envelope($testMsg, [new SentStamp('', 'default')]));

        $gateway = $this->getContainer()->get('shopware.queue.monitoring.gateway');
        $decoratedBus = new MonitoringBusDecorator($innerBus, 'default', $gateway);

        $decoratedBus->dispatch($testMsg);

        static::assertEquals(1, $this->getQueueSize(TestMessage::class));
    }

    public function testItCountsIncomingMessages(): void
    {
        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);

        $gateway = $this->getContainer()->get('shopware.queue.monitoring.gateway');
        $decoratedBus = new MonitoringBusDecorator($innerBus, 'default', $gateway);

        $gateway->reset(TestMessage::class);
        $gateway->increment(TestMessage::class);

        $envelope = new Envelope($testMsg);
        $innerBus
            ->method('dispatch')
            ->willReturnCallback(function ($message, $stamps) {
                return Envelope::wrap($message, $stamps)->with(new ReceivedStamp('default'));
            });

        $decoratedBus->dispatch($envelope);

        static::assertEquals(0, $this->getQueueSize(TestMessage::class));
    }

    public function testOutgoingEnvelopes(): void
    {
        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);
        $innerBus
            ->method('dispatch')
            ->willReturn(new Envelope($testMsg, [new SentStamp('', 'default')]));

        $gateway = $this->getContainer()->get('shopware.queue.monitoring.gateway');
        $decoratedBus = new MonitoringBusDecorator($innerBus, 'default', $gateway);

        $envelope = new Envelope($testMsg);
        $decoratedBus->dispatch($envelope);

        static::assertEquals(1, $this->getQueueSize(\get_class($testMsg)));
    }

    public function testDoesNotIncrementWithNonDefaultName(): void
    {
        $testMsg = new TestMessage();

        $defaultTransportName = 'default';
        $innerBus = $this->createMock(MessageBusInterface::class);
        $innerBus
            ->method('dispatch')
            ->willReturn(new Envelope($testMsg, [new SentStamp('', 'not ' . $defaultTransportName)]));

        $gateway = $this->getContainer()->get('shopware.queue.monitoring.gateway');
        $decoratedBus = new MonitoringBusDecorator($innerBus, $defaultTransportName, $gateway);

        $envelope = new Envelope($testMsg);
        $decoratedBus->dispatch($envelope);

        static::assertNull($this->getQueueSize(\get_class($testMsg)));
    }

    public function testDoesNotIncrementWithoutSentStamp(): void
    {
        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);
        $innerBus
            ->method('dispatch')
            ->willReturn(new Envelope($testMsg));

        $defaultTransportName = 'default';
        $gateway = $this->getContainer()->get('shopware.queue.monitoring.gateway');
        $decoratedBus = new MonitoringBusDecorator($innerBus, $defaultTransportName, $gateway);

        $envelope = new Envelope($testMsg);
        $decoratedBus->dispatch($envelope);

        static::assertNull($this->getQueueSize(TestMessage::class));
    }

    public function testDoesNotDecrementWithNonDefaultName(): void
    {
        $context = Context::createDefaultContext();

        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);

        $defaultTransportName = 'default';
        $gateway = $this->getContainer()->get('shopware.queue.monitoring.gateway');
        $decoratedBus = new MonitoringBusDecorator($innerBus, $defaultTransportName, $gateway);

        $gateway->reset(TestMessage::class);
        $gateway->increment(TestMessage::class);

        $envelope = new Envelope($testMsg);
        $innerBus
            ->method('dispatch')
            ->willReturnCallback(function ($message, $stamps) {
                return Envelope::wrap($message, $stamps)->with(new ReceivedStamp('not default'));
            });

        $decoratedBus->dispatch($envelope);

        static::assertEquals(1, $this->getQueueSize(\get_class($testMsg)));
    }

    public function testDoesNotDecrementWithoutReceivedStamp(): void
    {
        $context = Context::createDefaultContext();

        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);

        $defaultTransportName = 'default';
        /** @var AbstractMonitoringGateway $gateway */
        $gateway = $this->getContainer()->get('shopware.queue.monitoring.gateway');
        $decoratedBus = new MonitoringBusDecorator($innerBus, $defaultTransportName, $gateway);

        $gateway->reset(TestMessage::class);
        $gateway->increment(TestMessage::class);

        $envelope = new Envelope($testMsg);
        $innerBus
            ->method('dispatch')
            ->willReturnCallback(function ($message, $stamps) {
                return Envelope::wrap($message, $stamps);
            });

        $decoratedBus->dispatch($envelope);

        static::assertEquals(1, $this->getQueueSize(\get_class($testMsg)));
    }

    private function getQueueSize(string $name): ?int
    {
        $records = $this->getContainer()->get('shopware.queue.monitoring.gateway')->get();

        foreach ($records as $record) {
            if ($record['name'] === $name) {
                return (int) $record['size'];
            }
        }
        return null;
    }
}

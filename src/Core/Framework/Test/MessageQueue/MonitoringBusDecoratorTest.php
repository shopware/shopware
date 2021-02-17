<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\MessageQueue\MonitoringBusDecorator;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\TestMessage;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
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
            ->with(static::equalTo($testMsg))
            ->willReturn(new Envelope($testMsg));

        $connectionMock = $this->createMock(Connection::class);

        $decoratedBus = new MonitoringBusDecorator($innerBus, $connectionMock);
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
            ->with(static::equalTo($testMsg), static::equalTo($stamps))
            ->willReturn(new Envelope($testMsg));

        $connectionMock = $this->createMock(Connection::class);

        $decoratedBus = new MonitoringBusDecorator($innerBus, $connectionMock);
        $decoratedBus->dispatch($testMsg, $stamps);
    }

    public function testItCountsOutgoingMessages(): void
    {
        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);
        $innerBus
            ->method('dispatch')
            ->willReturn(new Envelope($testMsg));

        $connection = $this->getContainer()->get(Connection::class);
        $decoratedBus = new MonitoringBusDecorator($innerBus, $connection);

        $decoratedBus->dispatch($testMsg);

        /** @var EntityRepositoryInterface $queueRepo */
        $queueRepo = $this->getContainer()->get('message_queue_stats.repository');
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->setLimit(1)->addFilter(new EqualsFilter('name', TestMessage::class));
        $queueStatus = $queueRepo->search($criteria, $context)->first();

        static::assertNotNull($queueStatus);
        static::assertEquals(1, $queueStatus->getSize());
    }

    public function testItCountsIncomingMessages(): void
    {
        $context = Context::createDefaultContext();

        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);
        $innerBus
            ->method('dispatch')
            ->willReturn(new Envelope($testMsg));

        $connection = $this->getContainer()->get(Connection::class);
        $decoratedBus = new MonitoringBusDecorator($innerBus, $connection);

        /** @var EntityRepositoryInterface $queueRepo */
        $queueRepo = $this->getContainer()->get('message_queue_stats.repository');
        $queueRepo->create(
            [[
                'name' => \get_class($testMsg),
                'size' => 1,
            ]],
            $context
        );

        $envelope = new Envelope($testMsg);
        $envelope = $envelope->with(new ReceivedStamp('test'));

        $decoratedBus->dispatch($envelope);

        $criteria = new Criteria();
        $criteria->setLimit(1)->addFilter(new EqualsFilter('name', \get_class($testMsg)));
        $queueStatus = $queueRepo->search($criteria, $context)->first();

        static::assertNotNull($queueStatus);
        static::assertEquals(0, $queueStatus->getSize());
    }

    public function testOutgoingEnvelopes(): void
    {
        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);
        $innerBus
            ->method('dispatch')
            ->willReturn(new Envelope($testMsg));

        $connection = $this->getContainer()->get(Connection::class);
        $decoratedBus = new MonitoringBusDecorator($innerBus, $connection);

        $envelope = new Envelope($testMsg);
        $decoratedBus->dispatch($envelope);

        /** @var EntityRepositoryInterface $queueRepo */
        $queueRepo = $this->getContainer()->get('message_queue_stats.repository');
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->setLimit(1)->addFilter(new EqualsFilter('name', TestMessage::class));
        $queueStatus = $queueRepo->search($criteria, $context)->first();

        static::assertNotNull($queueStatus);
        static::assertEquals(1, $queueStatus->getSize());
    }
}

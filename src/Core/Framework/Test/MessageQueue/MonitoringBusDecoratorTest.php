<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\MessageQueue\MonitoringBusDecorator;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class MonitoringBusDecoratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItDispatchesToTheInnerBus()
    {
        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);
        $innerBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->equalTo($testMsg))
            ->willReturn(new Envelope($testMsg));

        $repoDummy = $this->createMock(EntityRepositoryInterface::class);

        $decoratedBus = new MonitoringBusDecorator($innerBus, $repoDummy);
        $decoratedBus->dispatch($testMsg);
    }

    public function testItCountsOutgoingMessages()
    {
        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);
        $innerBus
            ->method('dispatch')
            ->willReturn(new Envelope($testMsg));

        /** @var EntityRepositoryInterface $queueRepo */
        $queueRepo = $this->getContainer()->get('message_queue_stats.repository');

        $decoratedBus = new MonitoringBusDecorator($innerBus, $queueRepo);

        $decoratedBus->dispatch($testMsg);

        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->setLimit(1)->addFilter(new EqualsFilter('name', TestMessage::class));
        $queueStatus = $queueRepo->search($criteria, $context)->first();

        static::assertNotNull($queueStatus);
        static::assertEquals(1, $queueStatus->getSize());
    }

    public function testItCountsIncomingMessages()
    {
        $context = Context::createDefaultContext();

        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);
        $innerBus
            ->method('dispatch')
            ->willReturn(new Envelope($testMsg));

        /** @var EntityRepositoryInterface $queueRepo */
        $queueRepo = $this->getContainer()->get('message_queue_stats.repository');

        $decoratedBus = new MonitoringBusDecorator($innerBus, $queueRepo);

        $queueRepo->create(
            [[
                'name' => get_class($testMsg),
                'size' => 1,
            ]], $context);

        $envelope = new Envelope($testMsg);
        $envelope = $envelope->with(new ReceivedStamp());

        $decoratedBus->dispatch($envelope);

        $criteria = new Criteria();
        $criteria->setLimit(1)->addFilter(new EqualsFilter('name', get_class($testMsg)));
        $queueStatus = $queueRepo->search($criteria, $context)->first();

        static::assertNotNull($queueStatus);
        static::assertEquals(0, $queueStatus->getSize());
    }

    public function testOutgoingEnvelopes()
    {
        $testMsg = new TestMessage();

        $innerBus = $this->createMock(MessageBusInterface::class);
        $innerBus
            ->method('dispatch')
            ->willReturn(new Envelope($testMsg));

        /** @var EntityRepositoryInterface $queueRepo */
        $queueRepo = $this->getContainer()->get('message_queue_stats.repository');

        $decoratedBus = new MonitoringBusDecorator($innerBus, $queueRepo);

        $envelope = new Envelope($testMsg);
        $decoratedBus->dispatch($envelope);

        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->setLimit(1)->addFilter(new EqualsFilter('name', TestMessage::class));
        $queueStatus = $queueRepo->search($criteria, $context)->first();

        static::assertNotNull($queueStatus);
        static::assertEquals(1, $queueStatus->getSize());
    }
}

class TestMessage
{
}

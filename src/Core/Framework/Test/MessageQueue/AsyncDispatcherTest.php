<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\MessageQueue\AsyncDispatcher;
use Shopware\Core\Framework\MessageQueue\Message;
use Shopware\Core\Framework\MessageQueue\MessageQueueSizeEntity;
use Shopware\Core\Framework\Test\MessageQueue\_fixtures\MyMessage;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AsyncDispatcherTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItDispatchesToEventDispatcher()
    {
        $queueSizeRepo = $this->getContainer()->get('message_queue_size.repository');

        $dispatcher = new AsyncDispatcher(
            $this->getContainer()->get('messenger.bus.default'),
            $queueSizeRepo
        );

        $wasCalled = false;
        $eventName = 'test.eventName';
        $message = new Message();

        $listener = function (Event $ev) use (&$wasCalled) {
            $wasCalled = true;
            static::assertInstanceOf(Message::class, $ev);
        };
        /** @var EventDispatcherInterface $eventBus */
        $eventBus = $this->getContainer()->get('event_dispatcher');
        $eventBus->addListener($eventName, $listener);

        $dispatcher->dispatch($eventName, $message);

        static::assertTrue($wasCalled, "Event \"${eventName}\" was not emitted");

        $eventBus->removeListener($eventName, $listener);
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('name', $eventName));

        /** @var MessageQueueSizeEntity $queueSize */
        $queueSize = $queueSizeRepo->search($criteria, $context)->first();
        static::assertNotNull($queueSize);
        static::assertEquals(0, $queueSize->getSize());
    }

    public function testItDispatchesSubclassesToDefaultHandler()
    {
        $queueSizeRepo = $this->getContainer()->get('message_queue_size.repository');

        $dispatcher = new AsyncDispatcher(
            $this->getContainer()->get('messenger.bus.default'),
            $queueSizeRepo
        );

        $wasCalled = false;
        $eventName = 'test.eventName';
        $message = new MyMessage();
        $message->setMyProp('test prop');

        /** @var EventDispatcherInterface $eventBus */
        $eventBus = $this->getContainer()->get('event_dispatcher');
        $listener = function (Event $ev) use (&$wasCalled) {
            $wasCalled = true;
            static::assertInstanceOf(MyMessage::class, $ev);
            static::assertEquals('test prop', $ev->getMyProp());
        };
        $eventBus->addListener($eventName, $listener);

        $dispatcher->dispatch($eventName, $message);

        static::assertTrue($wasCalled, "Event \"${eventName}\" was not emitted");

        $eventBus->removeListener($eventName, $listener);
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('name', $eventName));

        /** @var MessageQueueSizeEntity $queueSize */
        $queueSize = $queueSizeRepo->search($criteria, $context)->first();
        static::assertNotNull($queueSize);
        static::assertEquals(0, $queueSize->getSize());
    }
}

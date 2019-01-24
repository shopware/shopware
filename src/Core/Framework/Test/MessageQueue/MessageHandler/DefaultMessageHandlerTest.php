<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\MessageHandler;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\MessageQueue\Message;
use Shopware\Core\Framework\MessageQueue\MessageHandler\DefaultMessageHandler;
use Shopware\Core\Framework\MessageQueue\MessageQueueSizeEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class DefaultMessageHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testImplementsCorrectInterface()
    {
        $msgHandler = $this->getContainer()->get(DefaultMessageHandler::class);
        static::assertInstanceOf(MessageHandlerInterface::class, $msgHandler);
    }

    public function testItDispatchesToEventBus()
    {
        /** @var EventDispatcherInterface $eventBus */
        $eventBus = $this->getContainer()->get('event_dispatcher');
        $msgHandler = $this->getContainer()->get(DefaultMessageHandler::class);

        $eventName = 'test.eventName';
        $queueSizeRepo = $this->getContainer()->get('message_queue_size.repository');
        $context = Context::createDefaultContext();
        $queueSizeRepo->create([
            [
                'name' => $eventName,
                'size' => 1,
            ],
        ], $context);

        $msg = new Message();
        $msg->setEventName($eventName);
        $wasCalled = false;

        $listener = function (Event $ev) use (&$wasCalled) {
            $wasCalled = true;
            static::assertInstanceOf(Message::class, $ev);
        };

        $eventBus->addListener($eventName, $listener);

        $msgHandler($msg);

        $eventBus->removeListener($eventName, $listener);

        static::assertTrue($wasCalled, "Event \"${eventName}\" was not emitted");

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('name', $eventName));

        /** @var MessageQueueSizeEntity $queueSize */
        $queueSize = $queueSizeRepo->search($criteria, $context)->first();
        static::assertNotNull($queueSize);
        static::assertEquals(0, $queueSize->getSize());
    }
}

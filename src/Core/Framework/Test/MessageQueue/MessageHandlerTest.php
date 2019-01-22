<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\Message;
use Shopware\Core\Framework\MessageQueue\MessageHandler;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class MessageHandlerTest extends TestCase
{
    use KernelTestBehaviour;

    public function testImplementsCorrectInterface()
    {
        /** @var EventDispatcherInterface $eventBus */
        $eventBus = $this->getContainer()->get('event_dispatcher');

        $msgHandler = new MessageHandler($eventBus);
        static::assertInstanceOf(MessageHandlerInterface::class, $msgHandler);
    }

    public function testItDispatchesToEventBus()
    {
        /** @var EventDispatcherInterface $eventBus */
        $eventBus = $this->getContainer()->get('event_dispatcher');

        $msgHandler = new MessageHandler($eventBus);

        $eventName = 'test.eventName';
        $msg = new Message();
        $msg->setEventName($eventName);
        $wasCalled = false;

        $eventBus->addListener($eventName, function (Event $ev) use (&$wasCalled) {
            $wasCalled = true;
            static::assertInstanceOf(Message::class, $ev);
        });

        $msgHandler->__invoke($msg);

        static::assertTrue($wasCalled, "Event \"${eventName}\" was not emitted");
    }
}

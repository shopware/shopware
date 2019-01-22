<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\AsyncDispatcher;
use Shopware\Core\Framework\MessageQueue\Message;
use Shopware\Core\Framework\Test\MessageQueue\_fixtures\MyMessage;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class AsyncDispatcherTest extends TestCase
{
    use KernelTestBehaviour;

    public function testDispatch()
    {
        $busMock = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $busMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(Message::class));

        $dispatcher = new AsyncDispatcher($busMock);

        $message = new Message();

        $dispatcher->dispatch('test_dispatch', $message);
    }

    public function testItDispatchesToEventDispatcher()
    {
        $bus = $this->getContainer()->get('messenger.bus.default');

        $dispatcher = new AsyncDispatcher($bus);

        $wasCalled = false;
        $eventName = 'test.eventName';
        $message = new Message();

        /** @var EventDispatcherInterface $eventBus */
        $eventBus = $this->getContainer()->get('event_dispatcher');
        $eventBus->addListener($eventName, function (Event $ev) use (&$wasCalled) {
            $wasCalled = true;
            static::assertInstanceOf(Message::class, $ev);
        });

        $dispatcher->dispatch($eventName, $message);

        static::assertTrue($wasCalled, "Event \"${eventName}\" was not emitted");
    }

    // Works because of https://github.com/symfony/symfony/pull/28271 just in Symfony 4.2 so we update Symfony again
    public function testItDispatchesSubclassesToDefaultHandler()
    {
        $bus = $this->getContainer()->get('messenger.bus.default');

        $dispatcher = new AsyncDispatcher($bus);

        $wasCalled = false;
        $eventName = 'test.eventName';
        $message = new MyMessage();
        $message->setMyProp('test prop');

        /** @var EventDispatcherInterface $eventBus */
        $eventBus = $this->getContainer()->get('event_dispatcher');
        $eventBus->addListener($eventName, function (Event $ev) use (&$wasCalled) {
            $wasCalled = true;
            static::assertInstanceOf(MyMessage::class, $ev);
            static::assertEquals('test prop', $ev->getMyProp());
        });

        $dispatcher->dispatch($eventName, $message);

        static::assertTrue($wasCalled, "Event \"${eventName}\" was not emitted");
    }
}

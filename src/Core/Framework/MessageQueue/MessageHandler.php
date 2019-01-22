<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

class MessageHandler implements MessageSubscriberInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function __invoke(Message $msg)
    {
        $this->eventDispatcher->dispatch($msg->getEventName(), $msg);
    }

    public static function getHandledMessages(): iterable
    {
        return [Message::class];
    }
}

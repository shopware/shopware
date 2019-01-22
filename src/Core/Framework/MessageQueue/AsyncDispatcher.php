<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Symfony\Component\Messenger\MessageBusInterface;

class AsyncDispatcher
{
    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    public function __construct(
        MessageBusInterface $messageBus
    ) {
        $this->messageBus = $messageBus;
    }

    public function dispatch(string $eventName, Message $msg): Message
    {
        $msg->setEventName($eventName);
        $this->messageBus->dispatch($msg);

        return $msg;
    }
}

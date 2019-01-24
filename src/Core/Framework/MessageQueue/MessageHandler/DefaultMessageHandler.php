<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\MessageHandler;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\Message;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DefaultMessageHandler extends AbstractMessageHandler
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        EntityRepositoryInterface $messageQueueSizeRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($messageQueueSizeRepository);
        $this->eventDispatcher = $eventDispatcher;
    }

    public function handle(Message $msg): void
    {
        $this->eventDispatcher->dispatch($msg->getEventName(), $msg);
    }

    public static function getHandledMessages(): iterable
    {
        return [Message::class];
    }
}

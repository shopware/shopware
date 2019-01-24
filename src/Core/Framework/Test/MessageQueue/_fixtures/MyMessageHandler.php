<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\_fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\Message;
use Shopware\Core\Framework\MessageQueue\MessageHandler\AbstractMessageHandler;

class MyMessageHandler extends AbstractMessageHandler
{
    public function __construct(
        EntityRepositoryInterface $messageQueueSizeRepository
    ) {
        parent::__construct($messageQueueSizeRepository);
    }

    public function handle(Message $msg): void
    {
        throw new \Exception('MyMessageHandler->handle() was called');
    }

    public static function getHandledMessages(): iterable
    {
        return [MyMessage::class];
    }
}

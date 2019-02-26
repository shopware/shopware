<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Handler;

use Shopware\Core\Framework\MessageQueue\Exception\MessageFailedException;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

abstract class AbstractMessageHandler implements MessageSubscriberInterface
{
    /**
     * @throws MessageFailedException
     */
    public function __invoke($message): void
    {
        try {
            $this->handle($message);
        } catch (MessageFailedException $messageFailedException) {
            throw $messageFailedException;
        } catch (\Throwable $e) {
            throw new MessageFailedException($message, static::class, $e);
        }
    }

    abstract public function handle($message): void;

    abstract public static function getHandledMessages(): iterable;
}

<?php declare(strict_types=1);

namespace Shopware\Core\Test;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
class CollectingMessageBus implements MessageBusInterface
{
    /**
     * @var array<Envelope>
     */
    private array $messages = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = new Envelope($message);

        $this->messages[] = $envelope;

        return $envelope;
    }

    /**
     * @return array<Envelope>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function clear(): void
    {
        $this->messages = [];
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Receiver;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

class CountHandledMessagesReceiver implements ReceiverInterface
{
    /**
     * @var ReceiverInterface
     */
    private $decoratedReceiver;

    /**
     * @var int
     */
    private $handledMessages;

    public function __construct(ReceiverInterface $decoratedReceiver)
    {
        $this->decoratedReceiver = $decoratedReceiver;
        $this->handledMessages = 0;
    }

    public function receive(callable $handler): void
    {
        $this->decoratedReceiver->receive(function (?Envelope $envelope) use ($handler) {
            $handler($envelope);

            if ($envelope) {
                ++$this->handledMessages;
            }
        });
    }

    public function stop(): void
    {
        $this->decoratedReceiver->stop();
    }

    public function getHandledMessagesCount(): int
    {
        return $this->handledMessages;
    }
}

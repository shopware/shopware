<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

class RunUntilEmptyReceiver implements ReceiverInterface
{
    /**
     * @var ReceiverInterface
     */
    private $receiver;

    /**
     * @var string
     */
    private $queueFile;

    public function __construct(ReceiverInterface $receiver, string $queueFile)
    {
        $this->receiver = $receiver;
        $this->queueFile = $queueFile;
    }

    public function receive(callable $handler): void
    {
        $this->receiver->receive(function (?Envelope $envelope) use ($handler) {
            $handler($envelope);
            if (file_get_contents($this->queueFile) === '') {
                $this->stop();
            }
        });
    }

    public function stop(): void
    {
        $this->receiver->stop();
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

class MakeReceiverRestartableDecorator implements ReceiverInterface
{
    /**
     * @var ReceiverInterface
     */
    private $receiver;

    /**
     * @var bool
     */
    private $shouldStop = false;

    public function __construct(ReceiverInterface $receiver)
    {
        $this->receiver = $receiver;
    }

    public function receive(callable $handler): void
    {
        try {
            $this->receiver->receive(function (?Envelope $envelope) use ($handler) {
                if ($this->shouldStop) {
                    $this->shouldStop = false;
                    // workaround so the receiver can be restarted
                    throw new StopReceiverException();
                }
                $handler($envelope);
            });
        } catch (StopReceiverException $e) {
            // do nothing
        }
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }
}

class StopReceiverException extends \Exception
{
}

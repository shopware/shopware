<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Monitoring;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

class MonitoringBusDecorator implements MessageBusInterface
{
    private MessageBusInterface $innerBus;

    private string $defaultTransportName;

    private AbstractMonitoringGateway $gateway;

    public function __construct(
        MessageBusInterface $inner,
        string $defaultTransportName,
        AbstractMonitoringGateway $gateway
    ) {
        $this->innerBus = $inner;
        $this->defaultTransportName = $defaultTransportName;
        $this->gateway = $gateway;
    }

    /**
     * Dispatches the given message to the inner Bus and Logs it.
     *
     * @param object|Envelope $message
     */
    public function dispatch($message, array $stamps = []): Envelope
    {
        $message = $this->innerBus->dispatch(Envelope::wrap($message, $stamps), $stamps);
        if ($this->wasSentToDefaultTransport($message)) {
            $this->incrementMessageQueueSize($message);
        }

        if ($this->wasReceivedByDefaultTransport($message)) {
            $this->decrementMessageQueueSize($message);
        }

        return $message;
    }

    private function incrementMessageQueueSize(Envelope $message): void
    {
        $this->gateway->increment(\get_class($message->getMessage()));
    }

    private function decrementMessageQueueSize(Envelope $message): void
    {
        $this->gateway->decrement(\get_class($message->getMessage()));
    }

    private function wasSentToDefaultTransport(Envelope $message): bool
    {
        foreach ($message->all(SentStamp::class) as $stamp) {
            if ($stamp instanceof SentStamp && $stamp->getSenderAlias() === $this->defaultTransportName) {
                return true;
            }
        }

        return false;
    }

    private function wasReceivedByDefaultTransport(Envelope $message): bool
    {
        foreach ($message->all(ReceivedStamp::class) as $stamp) {
            if ($stamp instanceof ReceivedStamp && $stamp->getTransportName() === $this->defaultTransportName) {
                return true;
            }
        }

        return false;
    }
}

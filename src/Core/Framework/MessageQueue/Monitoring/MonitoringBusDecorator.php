<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Monitoring;

use Shopware\Core\Framework\Increment\Exception\IncrementGatewayNotFoundException;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

class MonitoringBusDecorator implements MessageBusInterface
{
    private MessageBusInterface $innerBus;

    private string $defaultTransportName;

    private IncrementGatewayRegistry $gatewayRegistry;

    public function __construct(
        MessageBusInterface $inner,
        string $defaultTransportName,
        IncrementGatewayRegistry $gatewayRegistry
    ) {
        $this->innerBus = $inner;
        $this->defaultTransportName = $defaultTransportName;
        $this->gatewayRegistry = $gatewayRegistry;
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
        try {
            $gateway = $this->gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);
        } catch (IncrementGatewayNotFoundException $exception) {
            // In case message_queue pool is disabled
            return;
        }

        $gateway->increment('message_queue_stats', \get_class($message->getMessage()));
    }

    private function decrementMessageQueueSize(Envelope $message): void
    {
        try {
            $gateway = $this->gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);
        } catch (IncrementGatewayNotFoundException $exception) {
            // In case message_queue pool is disabled
            return;
        }

        $gateway->decrement('message_queue_stats', \get_class($message->getMessage()));
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

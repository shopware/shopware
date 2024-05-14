<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Messenger;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * @internal
 */
#[Package('core')]
class MessageBus implements MessageBusInterface
{
    /**
     * @param array<string, string|string[]> $routing
     */
    public function __construct(
        private readonly MessageBusInterface $decorated,
        private readonly array $routing
    ) {
    }

    /**
     * @param array<StampInterface> $stamps
     */
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        if ($this->hasTransportStamp($stamps)) {
            return $this->decorated->dispatch($message, $stamps);
        }

        $transports = $this->getTransports($message);

        if (empty($transports)) {
            return $this->decorated->dispatch($message, $stamps);
        }

        $stamps[] = new TransportNamesStamp($transports);

        return $this->decorated->dispatch($message, $stamps);
    }

    /**
     * @param array<StampInterface> $stamps
     */
    private function hasTransportStamp(array $stamps): bool
    {
        return \in_array(TransportNamesStamp::class, array_map('get_class', $stamps), true);
    }

    /**
     * @return array<string>|string|null
     */
    private function getTransports(object $message): array|string|null
    {
        $class = $message::class;

        if (!\array_key_exists($class, $this->routing)) {
            return null;
        }

        return $this->routing[$class];
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Messenger;

use Shopware\Core\Framework\Feature;
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
     * @param array<string, string|string[]> $overwrite
     */
    public function __construct(
        private readonly MessageBusInterface $decorated,
        /**
         * @deprecated tag:v6.7.0 - Will be removed in v6.7.0 Use $overwrite instead
         */
        private readonly array $routing,
        private readonly array $overwrite
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

        $transports = $this->getTransports($message, $this->overwrite, true);
        if (!empty($transports)) {
            $stamps[] = new TransportNamesStamp($transports);

            return $this->decorated->dispatch($message, $stamps);
        }

        if (Feature::isActive('v6.7.0.0')) {
            return $this->decorated->dispatch($message, $stamps);
        }

        $transports = $this->getTransports($message, $this->routing, false);
        if (!empty($transports)) {
            $stamps[] = new TransportNamesStamp($transports);

            return $this->decorated->dispatch($message, $stamps);
        }

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
     * @param array<string, string|string[]> $overwrites
     *
     * @return array<string>|string|null
     */
    private function getTransports(object $message, array $overwrites, bool $inherited): array|string|null
    {
        $class = $message::class;

        if (\array_key_exists($class, $overwrites)) {
            return $overwrites[$class];
        }

        if (!$inherited) {
            return null;
        }

        foreach ($overwrites as $class => $transports) {
            if ($message instanceof $class) {
                return $transports;
            }
        }

        return null;
    }
}

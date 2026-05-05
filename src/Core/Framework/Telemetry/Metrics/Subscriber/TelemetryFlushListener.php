<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Transport\TransportCollection;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
class TelemetryFlushListener implements EventSubscriberInterface
{
    /**
     * @param TransportCollection<MetricTransportInterface> $transports
     */
    public function __construct(
        private readonly TransportCollection $transports,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'flush',
            ConsoleEvents::TERMINATE => 'flush',
        ];
    }

    public function flush(): void
    {
        foreach ($this->transports as $transport) {
            try {
                $transport->flush();
            } catch (\Throwable $e) {
                $this->logger->warning(
                    \sprintf('Failed to flush metric transport %s', $transport::class),
                    ['exception' => $e]
                );
            }
        }
    }
}

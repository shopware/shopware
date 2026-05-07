<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Transport\TransportCollection;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * @internal
 */
#[Package('framework')]
class TelemetryFlushListener implements EventSubscriberInterface
{
    private const DEFAULT_WORKER_FLUSH_INTERVAL_SECONDS = 60;

    private float $lastFlushAt;

    private readonly int $workerFlushIntervalSeconds;

    /**
     * @param TransportCollection<MetricTransportInterface> $transports
     */
    public function __construct(
        private readonly TransportCollection $transports,
        private readonly LoggerInterface $logger,
        ?int $workerFlushIntervalSeconds = null,
    ) {
        $this->workerFlushIntervalSeconds = $workerFlushIntervalSeconds ?? self::DEFAULT_WORKER_FLUSH_INTERVAL_SECONDS;
        $this->lastFlushAt = microtime(true);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'flush',
            ConsoleEvents::TERMINATE => 'flush',
            WorkerRunningEvent::class => 'flushIfStale',
        ];
    }

    public function flush(): void
    {
        $this->lastFlushAt = microtime(true);

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

    public function flushIfStale(WorkerRunningEvent $event): void
    {
        if (microtime(true) - $this->lastFlushAt < $this->workerFlushIntervalSeconds) {
            return;
        }

        $this->flush();
    }
}

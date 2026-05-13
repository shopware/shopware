<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\PeriodicMetricCollectorInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: CollectPeriodicMetricsTask::class)]
#[Package('framework')]
final class CollectPeriodicMetricsTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param iterable<PeriodicMetricCollectorInterface> $collectors
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly Meter $meter,
        private readonly iterable $collectors,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        foreach ($this->collectors as $collector) {
            try {
                foreach ($collector->collect() as $metric) {
                    $this->meter->emit($metric);
                }
            } catch (\Throwable $e) {
                $this->exceptionLogger->error(
                    \sprintf('Periodic metric collector %s failed: %s', $collector::class, $e->getMessage()),
                    ['exception' => $e]
                );
            }
        }
    }
}

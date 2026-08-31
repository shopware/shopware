<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Telemetry;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\PeriodicMetricCollectorInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * Samples the pending message count of each configured transport for the `messenger.queue.depth` gauge.
 *
 * Only transports implementing {@see MessageCountAwareInterface} can be counted; the rest are skipped.
 * `transport=failed` is the dead-letter queue depth.
 *
 * Driven by the `telemetry.collect_periodic_metrics` scheduled task.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class MessengerQueueDepthCollector implements PeriodicMetricCollectorInterface
{
    /**
     * @param ServiceLocator<ReceiverInterface> $transportLocator
     */
    public function __construct(
        private readonly ServiceLocator $transportLocator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function collect(): iterable
    {
        foreach ($this->countableTransports() as $name => $transport) {
            // Per-transport guard: a single unreachable transport (e.g. broker connection error) must not
            // abort the count of the remaining ones.
            try {
                $count = $transport->getMessageCount();
            } catch (\Throwable $e) {
                $this->logger->error(
                    \sprintf('Failed to read message count for transport "%s": %s', $name, $e->getMessage()),
                    ['exception' => $e]
                );

                continue;
            }

            yield new ConfiguredMetric(
                name: 'messenger.queue.depth',
                value: $count,
                labels: ['transport' => $name],
            );
        }
    }

    /**
     * @return \Generator<string, MessageCountAwareInterface>
     */
    private function countableTransports(): \Generator
    {
        foreach ($this->transportLocator->getProvidedServices() as $name => $type) {
            // The receiver locator exposes each transport twice: under its service id (`messenger.transport.*`)
            // and under its configured name. Keep only the latter, which is the human transport label.
            if (str_starts_with($name, 'messenger.transport.')) {
                continue;
            }

            $transport = $this->transportLocator->get($name);
            if (!$transport instanceof MessageCountAwareInterface) {
                continue;
            }

            yield $name => $transport;
        }
    }
}

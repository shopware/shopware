<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Extractor;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Attribute\MetricAttributeInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\InvalidMetricValueException;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\MetricInterface;

/**
 * @internal
 */
#[Package('core')]
class MetricExtractor
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<MetricInterface>
     */
    public function fromEvent(object $event): array
    {
        $reflection = new \ReflectionClass($event);

        $metrics = [];
        foreach ($reflection->getAttributes() as $attribute) {
            $attribute = $attribute->newInstance();
            if (!$attribute instanceof MetricAttributeInterface) {
                continue;
            }
            try {
                $metrics[] = $attribute->getMetric($event);
            } catch (InvalidMetricValueException $e) {
                // problem with processing the metric should not break the application
                $this->logger->error(
                    \sprintf('Failed to extract metric from the attribute %s of event', $attribute::class),
                    ['exception' => $e]
                );
            }
        }

        return $metrics;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Attribute;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\InvalidMetricValueException;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\MetricInterface;

/**
 * @internal
 */
#[Package('core')]
interface MetricAttributeInterface
{
    public const TYPE_VALUE = 'value';
    public const TYPE_DYNAMIC = 'dynamic';

    /**
     * @throws InvalidMetricValueException
     */
    public function getMetric(object $decorated): MetricInterface;
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityTelemetrySubscriber;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(EntityTelemetrySubscriber::class)]
class EntityTelemetrySubscriberTest extends TestCase
{
    public function testEmitAssociationsCountMetric(): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('association1');
        $criteria->addAssociation('association2');

        $event = new EntitySearchedEvent($criteria, $this->createMock(EntityDefinition::class), Context::createDefaultContext());
        $meter = $this->createMock(Meter::class);
        $meter->expects(static::once())
            ->method('emit')
            ->with(static::callback(function (ConfiguredMetric $metric) {
                return $metric->name === 'dal.associations.count' && $metric->value === 2;
            }));

        $subscriber = new EntityTelemetrySubscriber($meter);
        $subscriber->emitAssociationsCountMetric($event);
    }
}

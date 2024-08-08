<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Subscriber\EntityStatsSubscriber;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Histogram;

/**
 * @internal
 */
#[CoversClass(EntityStatsSubscriber::class)]
class EntityStatsSubscriberTest extends TestCase
{
    private EntityStatsSubscriber $subscriber;

    private Meter&MockObject $meter;

    protected function setUp(): void
    {
        $this->meter = $this->createMock(Meter::class);
        $this->subscriber = new EntityStatsSubscriber($this->meter);
    }

    public function testOnEntitySearched(): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('association1');
        $criteria->addAssociation('association2')->addAssociation('association3');

        $event = new EntitySearchedEvent($criteria, $this->createMock(EntityDefinition::class), Context::createDefaultContext());

        $this->meter->expects(static::once())
            ->method('emit')
            ->with(static::callback(function (Histogram $histogram) {
                return $histogram->name === 'dal.association.count'
                    && $histogram->value === 3
                    && $histogram->description === 'Number of associations in request';
            }));

        $this->subscriber->onEntitySearched($event);
    }
}

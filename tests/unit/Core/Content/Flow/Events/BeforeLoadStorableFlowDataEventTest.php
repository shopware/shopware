<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(BeforeLoadStorableFlowDataEvent::class)]
class BeforeLoadStorableFlowDataEventTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetters(): void
    {
        $event = new BeforeLoadStorableFlowDataEvent(
            'entity_name',
            new Criteria(),
            Context::createDefaultContext()
        );

        static::assertSame('entity_name', $event->getEntityName());
        static::assertSame('flow.storer.entity_name.criteria.event', $event->getName());
    }
}

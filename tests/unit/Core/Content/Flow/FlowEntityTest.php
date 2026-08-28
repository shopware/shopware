<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceCollection;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(FlowEntity::class)]
class FlowEntityTest extends TestCase
{
    protected function tearDown(): void
    {
        FieldVisibility::$isInTwigRenderingContext = false;
    }

    public function testAccessorsRoundTrip(): void
    {
        $sequences = new FlowSequenceCollection();
        $appFlowEvent = new AppFlowEventEntity();

        $flow = new FlowEntity();
        $flow->setName('Order placed');
        $flow->setEventName('checkout.order.placed');
        $flow->setDescription('Sends the confirmation');
        $flow->setActive(true);
        $flow->setPriority(10);
        $flow->setInvalid(false);
        $flow->setSequences($sequences);
        $flow->setAppFlowEvent($appFlowEvent);
        $flow->setAppFlowEventId('app-flow-event-id');

        static::assertSame('Order placed', $flow->getName());
        static::assertSame('checkout.order.placed', $flow->getEventName());
        static::assertSame('Sends the confirmation', $flow->getDescription());
        static::assertTrue($flow->isActive());
        static::assertSame(10, $flow->getPriority());
        static::assertFalse($flow->isInvalid());
        static::assertSame($sequences, $flow->getSequences());
        static::assertSame($appFlowEvent, $flow->getAppFlowEvent());
        static::assertSame('app-flow-event-id', $flow->getAppFlowEventId());
    }

    public function testPayloadIsReadableOutsideTwig(): void
    {
        $flow = $this->flowWithInternalPayload();
        $flow->setPayload('serialized-flow');

        static::assertSame('serialized-flow', $flow->getPayload());
    }

    public function testPayloadIsGuardedInsideTwig(): void
    {
        $flow = $this->flowWithInternalPayload();
        $flow->setPayload('serialized-flow');

        FieldVisibility::$isInTwigRenderingContext = true;

        $this->expectExceptionObject(DataAbstractionLayerException::internalFieldAccessNotAllowed('payload', FlowEntity::class));
        $flow->getPayload();
    }

    private function flowWithInternalPayload(): FlowEntity
    {
        $flow = new FlowEntity();
        $flow->internalSetEntityData(FlowDefinition::ENTITY_NAME, new FieldVisibility(['payload']));

        return $flow;
    }
}

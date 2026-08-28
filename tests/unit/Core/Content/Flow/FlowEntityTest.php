<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Content\Flow\FlowEntity;
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

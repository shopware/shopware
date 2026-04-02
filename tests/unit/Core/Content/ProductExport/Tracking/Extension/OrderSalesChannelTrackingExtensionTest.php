<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Tracking\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\ProductExport\Tracking\Extension\OrderSalesChannelTrackingExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(OrderSalesChannelTrackingExtension::class)]
class OrderSalesChannelTrackingExtensionTest extends TestCase
{
    public function testGetEntityName(): void
    {
        $extension = new OrderSalesChannelTrackingExtension();

        static::assertSame(OrderDefinition::ENTITY_NAME, $extension->getEntityName());
    }

    public function testExtendFieldsAddsAssociation(): void
    {
        $extension = new OrderSalesChannelTrackingExtension();
        $collection = new FieldCollection();

        $extension->extendFields($collection);

        static::assertCount(1, $collection);

        $field = $collection->first();
        static::assertInstanceOf(OneToOneAssociationField::class, $field);
        static::assertSame('salesChannelTracking', $field->getPropertyName());
    }
}

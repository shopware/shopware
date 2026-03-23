<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Tracking\Extension;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingOrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class OrderSalesChannelTrackingExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField(
                'salesChannelTracking',
                'id',
                'order_id',
                SalesChannelTrackingOrderDefinition::class,
                false,
            ))->addFlags(new CascadeDelete()),
        );
    }

    public function getEntityName(): string
    {
        return OrderDefinition::ENTITY_NAME;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Tracking\Extension;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingOrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */
#[Package('discovery')]
class OrderSalesChannelTrackingExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToOneAssociationField(
                'salesChannelTracking',
                'id',
                'order_id',
                SalesChannelTrackingOrderDefinition::class,
                false,
            ),
        );
    }

    public function getEntityName(): string
    {
        return OrderDefinition::ENTITY_NAME;
    }
}

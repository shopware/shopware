<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Tracking\Extension;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingCustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class CustomerSalesChannelTrackingExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField(
                'salesChannelTracking',
                'id',
                'customer_id',
                SalesChannelTrackingCustomerDefinition::class,
                false,
            ))->addFlags(new CascadeDelete()),
        );
    }

    public function getEntityName(): string
    {
        return CustomerDefinition::ENTITY_NAME;
    }
}

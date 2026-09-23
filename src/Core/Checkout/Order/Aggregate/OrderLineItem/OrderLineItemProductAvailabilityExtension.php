<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderLineItem;

use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * Declares the runtime `productAvailable` extension so it is part of the Store API response.
 *
 * @internal
 */
#[Package('checkout')]
class OrderLineItemProductAvailabilityExtension extends EntityExtension
{
    public function getEntityName(): string
    {
        return OrderLineItemDefinition::ENTITY_NAME;
    }

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new ObjectField('productAvailable', 'productAvailable'))->addFlags(new ApiAware(), new Runtime())
        );
    }

    public function getDefinitionClass(): string
    {
        return OrderLineItemDefinition::class;
    }
}

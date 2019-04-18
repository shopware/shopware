<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\SalesChannel;

use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelShippingMethodDefinition extends ShippingMethodDefinition implements SalesChannelDefinitionInterface
{
    use SalesChannelDefinitionTrait;

    public static function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('shipping_method.salesChannels.id', $context->getSalesChannel()->getId()));
    }

    protected static function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        self::decorateDefinitions($fields);

        return $fields;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelCountryDefinition extends CountryDefinition implements SalesChannelDefinitionInterface
{
    use SalesChannelDefinitionTrait;

    public static function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('country.salesChannels.id', $context->getSalesChannel()->getId()));
    }

    protected static function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        self::decorateDefinitions($fields);

        return $fields;
    }
}

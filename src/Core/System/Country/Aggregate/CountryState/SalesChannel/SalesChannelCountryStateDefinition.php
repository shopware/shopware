<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryState\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('system-settings')]
class SalesChannelCountryStateDefinition extends CountryStateDefinition implements SalesChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addFilter(
            new EqualsFilter('country_state.country.salesChannels.id', $context->getSalesChannel()->getId())
        );
    }
}

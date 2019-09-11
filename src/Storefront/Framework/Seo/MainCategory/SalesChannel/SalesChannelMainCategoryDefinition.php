<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\MainCategory\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Seo\MainCategory\MainCategoryDefinition;

class SalesChannelMainCategoryDefinition extends MainCategoryDefinition implements SalesChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()));
    }
}

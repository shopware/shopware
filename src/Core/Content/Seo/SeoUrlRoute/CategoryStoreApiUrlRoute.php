<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('discovery')]
class CategoryStoreApiUrlRoute implements EntitySeoUrlRouteInterface
{
    final public const ROUTE_NAME = 'store-api.category.detail';

    /**
     * @internal
     */
    public function __construct(private readonly CategoryDefinition $categoryDefinition)
    {
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->categoryDefinition,
            self::ROUTE_NAME,
            '',
            true,
            'navigationId'
        );
    }

    public function prepareCriteria(Criteria $criteria, SalesChannelEntity $salesChannel): void
    {
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_OR, [
            new EqualsFilter('type', CategoryDefinition::TYPE_FOLDER),
            new EqualsFilter('type', CategoryDefinition::TYPE_LINK),
        ]));

        $rootCategoryIds = array_values(array_filter([
            $salesChannel->getNavigationCategoryId(),
            $salesChannel->getFooterCategoryId(),
            $salesChannel->getServiceCategoryId(),
        ]));

        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_OR,
            array_map(
                static fn (string $rootCategoryId): ContainsFilter => new ContainsFilter('path', '|' . $rootCategoryId . '|'),
                $rootCategoryIds
            )
        ));
    }
}

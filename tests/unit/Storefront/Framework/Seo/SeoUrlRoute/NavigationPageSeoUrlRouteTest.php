<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\SeoUrlRoute;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute
 */
class NavigationPageSeoUrlRouteTest extends TestCase
{
    public function testPrepareCriteria(): void
    {
        $navigationPageSeoUrlRoute = new NavigationPageSeoUrlRoute(
            new CategoryDefinition(),
            static::createStub(CategoryBreadcrumbBuilder::class)
        );

        $salesChannel = new SalesChannelEntity();

        $criteria = new Criteria();
        $navigationPageSeoUrlRoute->prepareCriteria($criteria, $salesChannel);

        $filters = $criteria->getFilters();
        static::assertCount(2, $filters);

        $notFilter = $filters[0];
        static::assertInstanceOf(NotFilter::class, $notFilter);

        static::assertEquals(MultiFilter::CONNECTION_OR, $notFilter->getOperator());

        $notFilterQueries = $notFilter->getQueries();
        static::assertCount(2, $notFilterQueries);

        $equalsFilter = $notFilterQueries[0];
        static::assertInstanceOf(EqualsFilter::class, $equalsFilter);
        static::assertEquals('type', $equalsFilter->getField());
        static::assertEquals(CategoryDefinition::TYPE_FOLDER, $equalsFilter->getValue());

        $equalsFilter2 = $notFilterQueries[1];
        static::assertInstanceOf(EqualsFilter::class, $equalsFilter2);
        static::assertEquals('linkType', $equalsFilter2->getField());
        static::assertEquals(CategoryDefinition::LINK_TYPE_EXTERNAL, $equalsFilter2->getValue());

        $equalsFilterActive = $filters[1];
        static::assertInstanceOf(EqualsFilter::class, $equalsFilterActive);
        static::assertEquals('active', $equalsFilterActive->getField());
        static::assertTrue($equalsFilterActive->getValue());
    }
}

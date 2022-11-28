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
        /** @var MultiFilter $multiFilter */
        $multiFilter = $filters[0];
        static::assertInstanceOf(MultiFilter::class, $multiFilter);
        static::assertEquals('AND', $multiFilter->getOperator());
        $multiFilterQueries = $multiFilter->getQueries();

        static::assertCount(2, $multiFilterQueries);
        $this->assertEqualsFilter(
            $multiFilterQueries[0],
            'active',
            true
        );

        $notFilter = $multiFilterQueries[1];
        static::assertInstanceOf(NotFilter::class, $notFilter);
        static::assertEquals('OR', $notFilter->getOperator());

        $notFilterQueries = $notFilter->getQueries();
        static::assertCount(2, $notFilterQueries);
        $this->assertEqualsFilter(
            $notFilterQueries[0],
            'type',
            'folder'
        );
        $this->assertEqualsFilter(
            $notFilterQueries[1],
            'type',
            'link'
        );
    }

    /**
     * @param string|bool $value
     */
    private function assertEqualsFilter(
        EqualsFilter $equalsFilter,
        string $field,
        $value
    ): void {
        static::assertInstanceOf(EqualsFilter::class, $equalsFilter);
        static::assertEquals($field, $equalsFilter->getField());
        static::assertEquals($value, $equalsFilter->getValue());
    }
}

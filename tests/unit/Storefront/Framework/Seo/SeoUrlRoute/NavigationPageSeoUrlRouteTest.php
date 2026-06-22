<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(NavigationPageSeoUrlRoute::class)]
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
        static::assertSame('AND', $multiFilter->getOperator());
        $multiFilterQueries = $multiFilter->getQueries();

        static::assertCount(2, $multiFilterQueries);
        static::assertInstanceOf(EqualsFilter::class, $multiFilterQueries[0]);
        $this->assertEqualsFilter(
            $multiFilterQueries[0],
            'active',
            true
        );

        $notFilter = $multiFilterQueries[1];
        static::assertInstanceOf(NotFilter::class, $notFilter);
        static::assertSame('OR', $notFilter->getOperator());

        $notFilterQueries = $notFilter->getQueries();
        static::assertCount(2, $notFilterQueries);
    }

    public function testConfigRouteBySalesChannelClosure(): void
    {
        $navigationPageSeoUrlRoute = new NavigationPageSeoUrlRoute(
            new CategoryDefinition(),
            static::createStub(CategoryBreadcrumbBuilder::class)
        );
        $config = $navigationPageSeoUrlRoute->getConfig();
        $categoryId = Uuid::randomHex();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->assign(['navigationCategoryId' => $categoryId]);

        $parameters = new ArrayStruct(['navigationId' => $categoryId]);
        static::assertSame(
            'frontend.home.page',
            $config->getRouteBySalesChannel($salesChannel, $parameters)
        );
        static::assertFalse($parameters->has('navigationId'));
        static::assertSame(
            'frontend.navigation.page',
            $config->getRouteBySalesChannel($salesChannel, $parameters)
        );
    }

    private function assertEqualsFilter(
        EqualsFilter $equalsFilter,
        string $field,
        string|bool $value
    ): void {
        static::assertSame($field, $equalsFilter->getField());
        static::assertSame($value, $equalsFilter->getValue());
    }
}

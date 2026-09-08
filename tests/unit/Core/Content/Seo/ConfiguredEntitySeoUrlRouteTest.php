<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\ConfiguredEntitySeoUrlRoute;
use Shopware\Core\Content\Seo\SeoUrlRoute\ProductStoreApiUrlRoute;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ConfiguredEntitySeoUrlRoute::class)]
class ConfiguredEntitySeoUrlRouteTest extends TestCase
{
    public function testGetConfigReturnsTheConfiguredConfig(): void
    {
        $config = $this->createConfig();
        $decorated = static::createMock(SeoUrlRouteInterface::class);
        $decorated->expects($this->once())->method('getConfig')->willReturn($config);

        static::assertSame($config, (new ConfiguredEntitySeoUrlRoute($decorated))->getConfig());
    }

    public function testDelegatesToAFullSeoUrlRoute(): void
    {
        $config = $this->createConfig();
        $criteria = new Criteria();
        $salesChannel = new SalesChannelEntity();
        $entity = new PartialEntity();
        $mapping = new SeoUrlMapping($entity, [], []);

        $decorated = $this->createMock(SeoUrlRouteInterface::class);
        $decorated->expects($this->once())->method('prepareCriteria')->with($criteria, $salesChannel);
        $decorated->expects($this->once())->method('getMapping')->with($entity, $salesChannel)->willReturn($mapping);

        $route = new ConfiguredEntitySeoUrlRoute($decorated);
        $route->prepareCriteria($criteria, $salesChannel);

        static::assertSame($mapping, $route->getMapping($entity, $salesChannel));
    }

    public function testDelegatesPrepareCriteriaButFallsBackToGenericMappingForEntityRoutes(): void
    {
        $criteria = new Criteria();
        $salesChannel = new SalesChannelEntity();
        $decorated = $this->createMock(ProductStoreApiUrlRoute::class);
        $decorated->expects($this->once())
            ->method('prepareCriteria')
            ->with($criteria, $salesChannel)
            ->willReturnCallback(static function (Criteria $criteria): void {
                $criteria->addFilter(new EqualsFilter('active', true));
            });
        $decorated->expects($this->once())
            ->method('getConfig')
            ->willReturn($this->createConfig());

        $route = new ConfiguredEntitySeoUrlRoute($decorated);

        $route->prepareCriteria($criteria, $salesChannel);
        static::assertCount(1, $criteria->getFilters());

        // getMapping falls back to a generic mapping (entity under its entity name + the primary key parameter)
        $entity = new PartialEntity();
        $entity->setUniqueIdentifier('abc123');
        $entity->assign(['name' => 'foo']);

        $mapping = $route->getMapping($entity, null);
        static::assertSame(['productId' => 'abc123'], $mapping->getInfoPathContext());
        static::assertArrayHasKey('product', $mapping->getSeoPathInfoContext());
        static::assertSame($entity->jsonSerialize(), $mapping->getSeoPathInfoContext()['product']);
    }

    private function createConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(new ProductDefinition(), 'store-api.product.detail', '', true, 'productId');
    }
}

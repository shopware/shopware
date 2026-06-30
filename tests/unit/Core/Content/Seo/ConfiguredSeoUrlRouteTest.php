<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\ConfiguredSeoUrlRoute;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
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
#[CoversClass(ConfiguredSeoUrlRoute::class)]
class ConfiguredSeoUrlRouteTest extends TestCase
{
    public function testGetConfigReturnsTheConfiguredConfig(): void
    {
        $config = $this->createConfig();
        $decorated = $this->createMock(SeoUrlRouteInterface::class);

        static::assertSame($config, (new ConfiguredSeoUrlRoute($decorated, $config))->getConfig());
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

        $route = new ConfiguredSeoUrlRoute($decorated, $config);
        $route->prepareCriteria($criteria, $salesChannel);

        static::assertSame($mapping, $route->getMapping($entity, $salesChannel));
    }

    public function testDelegatesPrepareCriteriaButFallsBackToGenericMappingForEntityRoutes(): void
    {
        // store-api routes implement only EntitySeoUrlRouteInterface (prepareCriteria, but no getMapping)
        $decorated = new class($this->createConfig()) implements EntitySeoUrlRouteInterface {
            public bool $prepareCriteriaCalled = false;

            public function __construct(private readonly SeoUrlRouteConfig $config)
            {
            }

            public function getConfig(): SeoUrlRouteConfig
            {
                return $this->config;
            }

            public function prepareCriteria(Criteria $criteria, SalesChannelEntity $salesChannel): void
            {
                $this->prepareCriteriaCalled = true;
                $criteria->addFilter(new EqualsFilter('active', true));
            }
        };

        $route = new ConfiguredSeoUrlRoute($decorated, $decorated->getConfig());

        // prepareCriteria is delegated to the decorated entity route
        $criteria = new Criteria();
        $route->prepareCriteria($criteria, new SalesChannelEntity());
        static::assertTrue($decorated->prepareCriteriaCalled);
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

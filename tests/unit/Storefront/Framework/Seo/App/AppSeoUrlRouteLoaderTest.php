<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\ConfiguredEntitySeoUrlRoute;
use Shopware\Core\Framework\App\Aggregate\AppSeoUrlRoute\AppSeoUrlRouteEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityWriterGateway;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlRouteLoader;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlRouteProvider;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlRouteLoader::class)]
class AppSeoUrlRouteLoaderTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [ProductDefinition::class],
            Validation::createValidator(),
            new StaticEntityWriterGateway()
        );
    }

    public function testEveryEntityRouteIsWrappedIntoAConfiguredSeoUrlRoute(): void
    {
        $loader = $this->loader($this->entityRoute('product-teaser', 'product'));

        $routes = iterator_to_array($loader->load(), false);

        static::assertCount(1, $routes);
        static::assertInstanceOf(ConfiguredEntitySeoUrlRoute::class, $routes[0]);

        $config = $routes[0]->getConfig();
        static::assertSame('storefront.app.SwagSeoUrlApp.product-teaser', $config->getRouteName());
        static::assertSame('frontend.script_endpoint', $config->getTargetRouteName());
        static::assertSame('{{ product.translated.name }}', $config->getTemplate());
        static::assertSame('product', $config->getDefinition()->getEntityName());
        static::assertSame(['hook' => 'product-teaser', 'id' => 'the-id'], $config->getPrimaryKeyParameter('the-id'));
    }

    public function testRoutesBoundToAnUnknownEntityAreSkipped(): void
    {
        $loader = $this->loader(
            $this->entityRoute('blog-detail', 'ce_blog'),
            $this->entityRoute('product-teaser', 'product')
        );

        $routes = iterator_to_array($loader->load(), false);

        static::assertCount(1, $routes);
        static::assertSame('storefront.app.SwagSeoUrlApp.product-teaser', $routes[0]->getConfig()->getRouteName());
    }

    public function testWithoutEntityRoutesNothingIsLoaded(): void
    {
        static::assertSame([], iterator_to_array($this->loader()->load(), false));
    }

    private function loader(AppSeoUrlRouteEntity ...$routes): AppSeoUrlRouteLoader
    {
        $provider = static::createStub(AppSeoUrlRouteProvider::class);
        $provider->method('getEntityRoutes')->willReturn(new EntityCollection($routes));

        return new AppSeoUrlRouteLoader($provider, $this->definitionRegistry);
    }

    private function entityRoute(string $name, string $entityName): AppSeoUrlRouteEntity
    {
        $route = new AppSeoUrlRouteEntity();
        $route->id = Uuid::randomHex();
        $route->appId = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $route->name = $name;
        $route->routeName = 'storefront.app.SwagSeoUrlApp.' . $name;
        $route->hook = $name;
        $route->entityName = $entityName;
        $route->defaultTemplate = '{{ ' . $entityName . '.translated.name }}';
        $route->setUniqueIdentifier($route->id);

        return $route;
    }
}

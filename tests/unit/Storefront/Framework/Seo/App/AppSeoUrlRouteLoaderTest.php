<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\ConfiguredEntitySeoUrlRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityWriterGateway;
use Shopware\Storefront\Framework\Seo\App\AppEntitySeoUrlConfig;
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

    public function testEveryEntitySeoUrlIsWrappedIntoAConfiguredSeoUrlRoute(): void
    {
        $loader = $this->loader($this->seoUrl('product-teaser', 'product'));

        $routes = iterator_to_array($loader->load(), false);

        static::assertCount(1, $routes);
        static::assertInstanceOf(ConfiguredEntitySeoUrlRoute::class, $routes[0]);

        $config = $routes[0]->getConfig();
        static::assertSame($this->definitionRegistry->getByEntityName('product'), $config->getDefinition());
        static::assertSame('storefront.app.SwagSeoUrlApp.product-teaser', $config->getRouteName());
        static::assertSame('frontend.script_endpoint', $config->getTargetRouteName());
        static::assertSame('{{ product.translated.name }}', $config->getTemplate());
        static::assertSame(['hook' => 'product-teaser', 'id' => 'the-id'], $config->getPrimaryKeyParameter('the-id'));
    }

    public function testSeoUrlsBoundToAnUnknownEntityAreSkipped(): void
    {
        $loader = $this->loader(
            $this->seoUrl('blog-detail', 'ce_blog'),
            $this->seoUrl('product-teaser', 'product')
        );

        $routes = iterator_to_array($loader->load(), false);

        static::assertCount(1, $routes);
        static::assertSame('storefront.app.SwagSeoUrlApp.product-teaser', $routes[0]->getConfig()->getRouteName());
    }

    public function testWithoutEntitySeoUrlsNothingIsLoaded(): void
    {
        static::assertSame([], iterator_to_array($this->loader()->load(), false));
    }

    private function loader(AppEntitySeoUrlConfig ...$seoUrls): AppSeoUrlRouteLoader
    {
        $provider = static::createStub(AppSeoUrlRouteProvider::class);
        $provider->method('getEntityRoutes')->willReturn(array_values($seoUrls));

        return new AppSeoUrlRouteLoader($provider, $this->definitionRegistry);
    }

    private function seoUrl(string $name, string $entityName): AppEntitySeoUrlConfig
    {
        return new AppEntitySeoUrlConfig(
            name: $name,
            routeName: 'storefront.app.SwagSeoUrlApp.' . $name,
            hook: $name,
            entityName: $entityName,
            defaultTemplate: '{{ ' . $entityName . '.translated.name }}',
        );
    }
}

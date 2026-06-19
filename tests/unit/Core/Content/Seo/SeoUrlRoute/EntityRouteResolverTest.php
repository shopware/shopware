<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(EntityRouteResolver::class)]
class EntityRouteResolverTest extends TestCase
{
    private SeoUrlPlaceholderHandlerInterface&MockObject $placeholderHandler;

    private RouterInterface&MockObject $router;

    protected function setUp(): void
    {
        $this->placeholderHandler = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $this->router = $this->createMock(RouterInterface::class);
    }

    public function testGetRouteNameReturnsRegisteredRoute(): void
    {
        $resolver = $this->createResolverWithRoute('product', 'frontend.detail.page');

        static::assertSame('frontend.detail.page', $resolver->getRouteNameForEntityName('product'));
    }

    public function testGetRouteNameFallsBackToStoreApiRouteWhenNotRegistered(): void
    {
        $routeCollection = static::createStub(RouteCollection::class);
        $routeCollection->method('get')
            ->willReturnMap([
                ['store-api.product.detail', new Route('/store-api/product/{productId}')],
            ]);
        $this->router->method('getRouteCollection')->willReturn($routeCollection);

        $resolver = new EntityRouteResolver(new SeoUrlRouteRegistry([]), $this->placeholderHandler, $this->router);

        static::assertSame('store-api.product.detail', $resolver->getRouteNameForEntityName('product'));
    }

    public function testGetRouteNameConvertsUnderscoreToKebabCaseForStoreApiFallback(): void
    {
        $routeCollection = static::createStub(RouteCollection::class);
        $routeCollection->method('get')
            ->willReturnCallback(static fn (string $name) => $name === 'store-api.landing-page.detail' ? new Route('/') : null);
        $this->router->method('getRouteCollection')->willReturn($routeCollection);

        $resolver = new EntityRouteResolver(new SeoUrlRouteRegistry([]), $this->placeholderHandler, $this->router);

        static::assertSame('store-api.landing-page.detail', $resolver->getRouteNameForEntityName('landing_page'));
    }

    public function testGetRouteNameFallsBackToEntityNameWhenNothingMatches(): void
    {
        $routeCollection = static::createStub(RouteCollection::class);
        $routeCollection->method('get')->willReturn(null);
        $this->router->method('getRouteCollection')->willReturn($routeCollection);

        $resolver = new EntityRouteResolver(new SeoUrlRouteRegistry([]), $this->placeholderHandler, $this->router);

        static::assertSame('unknown_entity', $resolver->getRouteNameForEntityName('unknown_entity'));
    }

    public function testGenerateSeoUrlPlaceholderPassesResolvedRouteAndParameters(): void
    {
        $this->placeholderHandler
            ->expects($this->once())
            ->method('generate')
            ->with('frontend.detail.page', ['productId' => 'abc123'])
            ->willReturn('SEO_PLACEHOLDER');

        $resolver = $this->createResolverWithRoute('product', 'frontend.detail.page');

        static::assertSame('SEO_PLACEHOLDER', $resolver->generateSeoUrlPlaceholder('product', ['productId' => 'abc123']));
    }

    public function testGenerateSeoUrlPlaceholderUsesEmptyParametersByDefault(): void
    {
        $this->placeholderHandler
            ->expects($this->once())
            ->method('generate')
            ->with('frontend.navigation.page', [])
            ->willReturn('SEO_PLACEHOLDER');

        $resolver = $this->createResolverWithRoute('category', 'frontend.navigation.page');

        static::assertSame('SEO_PLACEHOLDER', $resolver->generateSeoUrlPlaceholder('category'));
    }

    public function testGenerateUrlPassesResolvedRouteAndParameters(): void
    {
        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with('frontend.detail.page', ['productId' => 'abc123'])
            ->willReturn('/product/some-product/abc123');

        $resolver = $this->createResolverWithRoute('product', 'frontend.detail.page');

        static::assertSame('/product/some-product/abc123', $resolver->generateUrl('product', ['productId' => 'abc123']));
    }

    private function createResolverWithRoute(string $entityName, string $routeName): EntityRouteResolver
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $config = new SeoUrlRouteConfig($definition, $routeName, '{{ entity.name }}');

        $seoUrlRoute = static::createStub(SeoUrlRouteInterface::class);
        $seoUrlRoute->method('getConfig')->willReturn($config);

        return new EntityRouteResolver(
            new SeoUrlRouteRegistry([$seoUrlRoute]),
            $this->placeholderHandler,
            $this->router,
        );
    }
}

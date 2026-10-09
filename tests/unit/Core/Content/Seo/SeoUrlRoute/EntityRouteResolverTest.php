<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\Exception\SeoUrlRouteConfigException;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(EntityRouteResolver::class)]
class EntityRouteResolverTest extends TestCase
{
    private SeoUrlPlaceholderHandlerInterface&Stub $placeholderHandler;

    private RouterInterface&Stub $router;

    protected function setUp(): void
    {
        $this->placeholderHandler = static::createStub(SeoUrlPlaceholderHandlerInterface::class);
        $this->router = static::createStub(RouterInterface::class);
    }

    public function testGetRouteNameReturnsRegisteredRoute(): void
    {
        $resolver = $this->createResolverWithRoute('product', ProductPageSeoUrlRoute::ROUTE_NAME);

        static::assertSame(ProductPageSeoUrlRoute::ROUTE_NAME, $resolver->getRouteNameForEntityName('product'));
    }

    public function testGetRouteNameResolvesViaConfiguredRouteWhenNotRegistered(): void
    {
        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([]),
            $this->placeholderHandler,
            $this->router,
            [$this->createSeoUrlRoute('product', 'store-api.product.detail')],
        );

        static::assertSame('store-api.product.detail', $resolver->getRouteNameForEntityName('product'));
    }

    public function testGetRouteNameThrowsWhenEntityHasNoRoute(): void
    {
        $resolver = new EntityRouteResolver(new SeoUrlRouteRegistry([]), $this->placeholderHandler, $this->router);

        $this->expectExceptionObject(SeoUrlRouteConfigException::routeConfigNotFoundForEntityName('product'));

        $resolver->getRouteNameForEntityName('product');
    }

    public function testGenerateSeoUrlPlaceholderPassesResolvedRouteAndParameters(): void
    {
        $this->placeholderHandler
            ->method('generate')
            ->willReturn('SEO_PLACEHOLDER');

        $resolver = $this->createResolverWithRoute('product', ProductPageSeoUrlRoute::ROUTE_NAME, 'productId');

        static::assertSame('SEO_PLACEHOLDER', $resolver->generateSeoUrlPlaceholder('product', 'abc123'));
    }

    public function testGenerateUrlPassesResolvedRouteAndParameters(): void
    {
        $this->router
            ->method('generate')
            ->willReturn('/product/some-product/abc123');

        $resolver = $this->createResolverWithRoute('product', ProductPageSeoUrlRoute::ROUTE_NAME, 'productId');

        static::assertSame('/product/some-product/abc123', $resolver->generateUrl('product', 'abc123'));
    }

    public function testGetSeoUrlRouteNameAndPathInfoSwapsRouteAndStripsBasePath(): void
    {
        $context = new RequestContext();
        $context->setBaseUrl('/subfolder');
        $this->router->method('getContext')->willReturn($context);
        $this->router->method('generate')->willReturn('/subfolder/store-api/product/abc123');

        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([]),
            $this->placeholderHandler,
            $this->router,
            [$this->createEntitySeoUrlRoute('product', 'store-api.product.detail', 'productId')],
        );

        static::assertSame(
            ['routeName' => 'store-api.product.detail', 'pathInfo' => '/store-api/product/abc123'],
            $resolver->getSeoUrlRouteNameAndPathInfo(
                'product',
                ProductPageSeoUrlRoute::ROUTE_NAME,
                'abc123',
                Defaults::SALES_CHANNEL_TYPE_API,
            ),
        );
    }

    public function testGetSeoUrlRouteNameAndPathInfoReturnsEmptyWhenRouteAlreadyMatches(): void
    {
        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([]),
            $this->placeholderHandler,
            $this->router,
            [$this->createEntitySeoUrlRoute('product', 'store-api.product.detail')],
        );

        static::assertSame([], $resolver->getSeoUrlRouteNameAndPathInfo(
            'product',
            'store-api.product.detail',
            'abc123',
            Defaults::SALES_CHANNEL_TYPE_API,
        ));
    }

    public function testGetSeoUrlRouteNameAndPathInfoReturnsEmptyWhenEntityHasNoRoute(): void
    {
        $resolver = new EntityRouteResolver(new SeoUrlRouteRegistry([]), $this->placeholderHandler, $this->router);

        static::assertSame([], $resolver->getSeoUrlRouteNameAndPathInfo(
            'product',
            ProductPageSeoUrlRoute::ROUTE_NAME,
            'abc123',
            Defaults::SALES_CHANNEL_TYPE_API,
        ));
    }

    public function testThrowsExceptionWhenRouteHasNoPrimaryKeyConfigured(): void
    {
        $this->expectExceptionObject(SeoUrlRouteConfigException::routeConfigMissingParameterKeyForPrimaryKey('product'));

        $resolver = $this->createResolverWithRoute('product', ProductPageSeoUrlRoute::ROUTE_NAME);

        $resolver->generateUrl('product', 'abc123');
    }

    public function testFindEntitySeoUrlRouteReturnsMatchingStoreApiRoute(): void
    {
        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([]),
            $this->placeholderHandler,
            $this->router,
            [
                $this->createEntitySeoUrlRoute('product', 'store-api.product.detail'),
                $this->createEntitySeoUrlRoute('category', 'store-api.category.detail'),
            ],
        );

        $route = $resolver->findEntitySeoUrlRoute('store-api.category.detail');

        static::assertInstanceOf(EntitySeoUrlRouteInterface::class, $route);
        static::assertSame('store-api.category.detail', $route->getConfig()->getRouteName());
    }

    public function testFindEntitySeoUrlRouteReturnsNullWhenNoRouteMatches(): void
    {
        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([]),
            $this->placeholderHandler,
            $this->router,
            [$this->createEntitySeoUrlRoute('product', 'store-api.product.detail')],
        );

        static::assertNull($resolver->findEntitySeoUrlRoute('store-api.category.detail'));
    }

    private function createEntitySeoUrlRoute(string $entityName, string $routeName, ?string $primaryKeyParameterKey = null): EntitySeoUrlRouteInterface
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $route = static::createStub(EntitySeoUrlRouteInterface::class);
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($definition, $routeName, '{{ entity.name }}', true, $primaryKeyParameterKey));

        return $route;
    }

    private function createResolverWithRoute(string $entityName, string $routeName, ?string $primaryKeyParameterKey = null): EntityRouteResolver
    {
        return new EntityRouteResolver(
            new SeoUrlRouteRegistry([$this->createSeoUrlRoute($entityName, $routeName, $primaryKeyParameterKey)]),
            $this->placeholderHandler,
            $this->router,
        );
    }

    private function createSeoUrlRoute(string $entityName, string $routeName, ?string $primaryKeyParameterKey = null): SeoUrlRouteInterface
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $config = new SeoUrlRouteConfig($definition, $routeName, '{{ entity.name }}', true, $primaryKeyParameterKey);

        $seoUrlRoute = static::createStub(SeoUrlRouteInterface::class);
        $seoUrlRoute->method('getConfig')->willReturn($config);

        return $seoUrlRoute;
    }
}

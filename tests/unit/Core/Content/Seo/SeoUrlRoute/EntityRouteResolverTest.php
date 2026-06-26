<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\Exception\SeoUrlRouteConfigException;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
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
            ->expects($this->once())
            ->method('generate')
            ->with('frontend.detail.page', ['productId' => 'abc123'])
            ->willReturn('SEO_PLACEHOLDER');

        $resolver = $this->createResolverWithRoute('product', 'frontend.detail.page', 'productId');

        static::assertSame('SEO_PLACEHOLDER', $resolver->generateSeoUrlPlaceholder('product', 'abc123'));
    }

    public function testGenerateUrlPassesResolvedRouteAndParameters(): void
    {
        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with('frontend.detail.page', ['productId' => 'abc123'])
            ->willReturn('/product/some-product/abc123');

        $resolver = $this->createResolverWithRoute('product', 'frontend.detail.page', 'productId');

        static::assertSame('/product/some-product/abc123', $resolver->generateUrl('product', 'abc123'));
    }

    public function testThrowsExceptionWhenRouteHasNoPrimaryKeyConfigured(): void
    {
        $this->expectExceptionObject(SeoUrlRouteConfigException::routeConfigMissingParameterKeyForPrimaryKey('product'));

        $resolver = $this->createResolverWithRoute('product', 'frontend.detail.page');

        $resolver->generateUrl('product', 'abc123');
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

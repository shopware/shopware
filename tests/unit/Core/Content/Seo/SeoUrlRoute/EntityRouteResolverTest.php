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

        $resolver = $this->createResolverWithRoute('product', 'frontend.detail.page', ['productId']);

        static::assertSame('SEO_PLACEHOLDER', $resolver->generateSeoUrlPlaceholder('product', ['abc123']));
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

        $resolver = $this->createResolverWithRoute('product', 'frontend.detail.page', ['productId']);

        static::assertSame('/product/some-product/abc123', $resolver->generateUrl('product', ['abc123']));
    }

    public function testThrowsExceptionOnRouteParameterMismatch(): void
    {
        $this->expectExceptionObject(SeoUrlRouteConfigException::routeParametersMismatching(['productId'], []));

        $resolver = $this->createResolverWithRoute('product', 'frontend.detail.page', ['productId']);

        $resolver->generateUrl('product', []);
    }

    /**
     * @param list<string> $parameterKeys
     */
    private function createResolverWithRoute(string $entityName, string $routeName, array $parameterKeys = []): EntityRouteResolver
    {
        return new EntityRouteResolver(
            new SeoUrlRouteRegistry([$this->createSeoUrlRoute($entityName, $routeName, $parameterKeys)]),
            $this->placeholderHandler,
            $this->router,
        );
    }

    /**
     * @param list<string> $parameterKeys
     */
    private function createSeoUrlRoute(string $entityName, string $routeName, array $parameterKeys = []): SeoUrlRouteInterface
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $config = new SeoUrlRouteConfig($definition, $routeName, '{{ entity.name }}', true, $parameterKeys);

        $seoUrlRoute = static::createStub(SeoUrlRouteInterface::class);
        $seoUrlRoute->method('getConfig')->willReturn($config);

        return $seoUrlRoute;
    }
}

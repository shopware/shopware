<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandler;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\CategoryStoreApiUrlRoute;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Content\Seo\SeoUrlRoute\LandingPageStoreApiUrlRoute;
use Shopware\Core\Content\Seo\SeoUrlRoute\ProductStoreApiUrlRoute;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
class EntityRouteResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[DataProvider('storefrontUrlProvider')]
    public function testGenerateStorefrontUrl(string $entityName, string $expectedPath, string $id): void
    {
        if (!static::getContainer()->has(NavigationPageSeoUrlRoute::class)) {
            static::markTestSkipped('Storefront seo url tests need storefront bundle to be installed');
        }

        $resolver = $this->getContainer()->get(EntityRouteResolver::class);
        static::assertInstanceOf(EntityRouteResolver::class, $resolver);

        static::assertSame($expectedPath, $resolver->generateUrl($entityName, $id));
        static::assertSame(
            SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . $expectedPath . '#',
            $resolver->generateSeoUrlPlaceholder($entityName, $id)
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function storefrontUrlProvider(): iterable
    {
        $id = Uuid::randomHex();

        yield 'product' => [ProductDefinition::ENTITY_NAME, '/detail/' . $id, $id];
        yield 'landing page' => [LandingPageDefinition::ENTITY_NAME, '/landingPage/' . $id, $id];
        yield 'category' => [CategoryDefinition::ENTITY_NAME, '/navigation/' . $id, $id];
    }

    #[DataProvider('storeApiUrlProvider')]
    public function testGenerateStoreApiUrl(string $entityName, string $expectedPath, string $id): void
    {
        $seoUrlPlaceholderHandler = $this->getContainer()->get(SeoUrlPlaceholderHandlerInterface::class);
        static::assertInstanceOf(SeoUrlPlaceholderHandlerInterface::class, $seoUrlPlaceholderHandler);
        $router = $this->getContainer()->get('router');
        static::assertInstanceOf(RouterInterface::class, $router);

        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([]),
            $seoUrlPlaceholderHandler,
            $router,
            [
                $this->getContainer()->get(ProductStoreApiUrlRoute::class),
                $this->getContainer()->get(CategoryStoreApiUrlRoute::class),
                $this->getContainer()->get(LandingPageStoreApiUrlRoute::class),
            ],
        );

        static::assertSame($expectedPath, $resolver->generateUrl($entityName, $id));
        static::assertSame(
            SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . $expectedPath . '#',
            $resolver->generateSeoUrlPlaceholder($entityName, $id)
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function storeApiUrlProvider(): iterable
    {
        $id = Uuid::randomHex();

        yield 'product' => [ProductDefinition::ENTITY_NAME, '/store-api/product/' . $id, $id];
        yield 'landing page' => [LandingPageDefinition::ENTITY_NAME, '/store-api/landing-page/' . $id, $id];
        yield 'category' => [CategoryDefinition::ENTITY_NAME, '/store-api/category/' . $id, $id];
    }
}

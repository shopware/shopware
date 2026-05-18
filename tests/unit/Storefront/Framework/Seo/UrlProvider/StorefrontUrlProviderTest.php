<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\UrlProvider;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\UrlProvider\UrlType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\UrlProvider\StorefrontUrlProvider;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorefrontUrlProvider::class)]
class StorefrontUrlProviderTest extends TestCase
{
    private MockObject&SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler;

    private MockObject&RouterInterface $router;

    private MockObject&Connection $connection;

    private StorefrontUrlProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seoUrlPlaceholderHandler = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $this->router = $this->createMock(RouterInterface::class);
        $this->connection = $this->createMock(Connection::class);

        $this->provider = new StorefrontUrlProvider(
            $this->seoUrlPlaceholderHandler,
            $this->router,
            $this->connection,
        );
    }

    public function testItGetsSeoUrls(): void
    {
        $id = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                static::anything(),
                [
                    'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
                    'languageId' => Uuid::fromHexToBytes($languageId),
                    'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
                    'ids' => Uuid::fromHexToBytesList([$id]),
                ],
                [
                    'ids' => ArrayParameterType::BINARY,
                ]
            );

        $this->provider->getSeoUrls(
            [$id],
            UrlType::PRODUCT,
            $languageId,
            $salesChannelId
        );
    }

    public function testItGeneratesUrl(): void
    {
        $this->router->expects($this->once())
            ->method('generate')
            ->with(ProductPageSeoUrlRoute::ROUTE_NAME, ['productId' => 'foobar']);

        $this->provider->generate(UrlType::PRODUCT, ['productId' => 'foobar']);
    }

    public function testItGeneratesUrlWithPlaceholder(): void
    {
        $this->seoUrlPlaceholderHandler->expects($this->once())
            ->method('generate')
            ->with(ProductPageSeoUrlRoute::ROUTE_NAME, ['productId' => 'foobar']);

        $this->provider->generateWithPlaceholder(UrlType::PRODUCT, ['productId' => 'foobar']);
    }

    #[DataProvider('urlTypeProvider')]
    public function testItGetsRouteNameByUrlType(UrlType $urlType, string $expectedRouteName): void
    {
        $routeName = $this->provider->getRouteNameByUrlType($urlType);

        static::assertSame($expectedRouteName, $routeName);
    }

    public static function urlTypeProvider(): \Generator
    {
        yield UrlType::PRODUCT->name => [UrlType::PRODUCT, ProductPageSeoUrlRoute::ROUTE_NAME];
        yield UrlType::CATEGORY->name => [UrlType::CATEGORY, NavigationPageSeoUrlRoute::ROUTE_NAME];
        yield UrlType::LANDING_PAGE->name => [UrlType::LANDING_PAGE, LandingPageSeoUrlRoute::ROUTE_NAME];
        yield UrlType::HOME->name => [UrlType::HOME, 'frontend.home.page'];
    }

    #[DataProvider('routeNameProvider')]
    public function testItGetsUrlTypeByRouteName(string $routeName, UrlType $expectedUrlType): void
    {
        $urlType = $this->provider->getUrlTypeByRouteName($routeName);

        static::assertSame($expectedUrlType, $urlType);
    }

    public static function routeNameProvider(): \Generator
    {
        yield ProductPageSeoUrlRoute::ROUTE_NAME => [ProductPageSeoUrlRoute::ROUTE_NAME, UrlType::PRODUCT];
        yield NavigationPageSeoUrlRoute::ROUTE_NAME => [NavigationPageSeoUrlRoute::ROUTE_NAME, UrlType::CATEGORY];
        yield LandingPageSeoUrlRoute::ROUTE_NAME => [LandingPageSeoUrlRoute::ROUTE_NAME, UrlType::LANDING_PAGE];
        yield 'frontend.home.page' => ['frontend.home.page', UrlType::HOME];
    }
}

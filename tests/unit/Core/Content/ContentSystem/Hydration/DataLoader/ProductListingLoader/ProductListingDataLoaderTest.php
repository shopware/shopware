<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataLoader\ProductListingLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ProductListingLoader\ProductListingDataLoader;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ProductListingLoader\ProductListingLoaderConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ProductListingDataLoader::class)]
class ProductListingDataLoaderTest extends TestCase
{
    private AbstractProductListingRoute&MockObject $listingRoute;

    private ProductListingDataLoader $loader;

    protected function setUp(): void
    {
        $this->listingRoute = $this->createMock(AbstractProductListingRoute::class);
        $this->loader = new ProductListingDataLoader($this->listingRoute);
    }

    #[TestDox('returns product_listing as requirement type identifier')]
    public function testGetRequirementTypeReturnsProductListingString(): void
    {
        static::assertSame('product_listing', ProductListingDataLoader::getRequirementType());
    }

    #[TestDox('returns listing result as data and marks result as cache-aware with no tags')]
    public function testLoadReturnsCachedExternallyResultWithListingData(): void
    {
        $navigationId = Uuid::randomHex();

        $config = new ProductListingLoaderConfig();
        $requirement = new DataRequirement('listing', 'product_listing', $config);
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', $navigationId)
            ->build();
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $this->listingRoute
            ->method('load')
            ->with($navigationId, $request, $context, static::isInstanceOf(Criteria::class))
            ->willReturn($response);

        $result = $this->loader->load($element, $requirement, $context, $request);

        static::assertSame($listingResult, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lowercases navigationId before passing it to the listing route')]
    public function testLoadCallsListingRouteWithLowercasedNavigationId(): void
    {
        $navigationId = Uuid::randomHex();
        $upperCaseId = strtoupper($navigationId);

        $config = new ProductListingLoaderConfig();
        $requirement = new DataRequirement('listing', 'product_listing', $config);
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', $upperCaseId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $capturedNavigationId = null;
        $this->listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId) use (&$capturedNavigationId, $response): ProductListingRouteResponse {
                $capturedNavigationId = $catId;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertSame($navigationId, $capturedNavigationId);
    }

    #[TestDox('reads navigationId from custom property name when configured')]
    public function testLoadUsesCustomPropertyNameFromConfig(): void
    {
        $navigationId = Uuid::randomHex();

        $config = new ProductListingLoaderConfig(property: 'categoryId');
        $requirement = new DataRequirement('listing', 'product_listing', $config);
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('categoryId', $navigationId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $capturedCategoryId = null;
        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $this->listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId) use (&$capturedCategoryId, $response): ProductListingRouteResponse {
                $capturedCategoryId = $catId;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertSame($navigationId, $capturedCategoryId);
    }

    #[TestDox('adds config associations to criteria when loading listing')]
    public function testLoadAddsConfigAssociationsToCriteria(): void
    {
        $navigationId = Uuid::randomHex();

        $config = new ProductListingLoaderConfig(associations: ['manufacturer', 'cover']);
        $requirement = new DataRequirement('listing', 'product_listing', $config);
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', $navigationId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $this->listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductListingRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('manufacturer', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
    }

    #[TestDox('merges element associations property into criteria when it is an array of strings')]
    public function testLoadMergesElementAssociationsIntoCriteria(): void
    {
        $navigationId = Uuid::randomHex();

        $config = new ProductListingLoaderConfig(associations: ['manufacturer']);
        $requirement = new DataRequirement('listing', 'product_listing', $config);
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', $navigationId)
            ->withProperty('associations', ['cover', 'media'])
            ->build();
        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $this->listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductListingRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('manufacturer', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('media', $capturedCriteria->getAssociations());
    }

    #[TestDox('ignores non-string values in element associations array when building criteria')]
    public function testLoadIgnoresNonStringValuesInElementAssociations(): void
    {
        $navigationId = Uuid::randomHex();

        $config = new ProductListingLoaderConfig();
        $requirement = new DataRequirement('listing', 'product_listing', $config);
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', $navigationId)
            ->withProperty('associations', ['cover', 42, null, 'media'])
            ->build();
        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $this->listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductListingRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('media', $capturedCriteria->getAssociations());
        static::assertCount(2, $capturedCriteria->getAssociations());
    }

    #[TestDox('returns notFound result when config is not a ProductListingLoaderConfig instance')]
    public function testLoadReturnsNotFoundWhenConfigIsWrongType(): void
    {
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('listing', 'product_listing', $wrongConfig);
        $element = ContentElementBuilder::create('product-listing')->build();
        $context = Generator::generateSalesChannelContext();

        $this->listingRoute->expects($this->never())->method('load');

        $result = $this->loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when navigationId element property is not a string')]
    public function testLoadReturnsNotFoundWhenNavigationIdPropertyIsNotString(): void
    {
        $config = new ProductListingLoaderConfig(property: 'navigationId');

        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', 42)
            ->build();

        $context = Generator::generateSalesChannelContext();

        $this->listingRoute->expects($this->never())->method('load');

        $result = $this->loader->load(
            $element,
            new DataRequirement('listing', 'product_listing', $config),
            $context,
            new Request()
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when navigationId element property is missing')]
    public function testLoadReturnsNotFoundWhenNavigationIdPropertyIsMissing(): void
    {
        $config = new ProductListingLoaderConfig(property: 'navigationId');

        $element = ContentElementBuilder::create('product-listing')->build();

        $context = Generator::generateSalesChannelContext();

        $this->listingRoute->expects($this->never())->method('load');

        $result = $this->loader->load(
            $element,
            new DataRequirement('listing', 'product_listing', $config),
            $context,
            new Request()
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
    }

    #[TestDox('throws DecorationPatternException when getDecorated is called')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->expectExceptionMessage('The getDecorated() function of core class');

        $this->loader->getDecorated();
    }
}

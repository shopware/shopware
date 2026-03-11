<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductSuggestDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductSuggestLoaderConfig;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Suggest\AbstractProductSuggestRoute;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRouteResponse;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ProductSuggestDataLoader::class)]
class ProductSuggestDataLoaderTest extends TestCase
{
    private AbstractProductSuggestRoute&MockObject $suggestRoute;

    private ProductSuggestDataLoader $loader;

    protected function setUp(): void
    {
        $this->suggestRoute = $this->createMock(AbstractProductSuggestRoute::class);
        $this->loader = new ProductSuggestDataLoader($this->suggestRoute);
    }

    #[TestDox('returns product_suggest as requirement type identifier')]
    public function testGetRequirementTypeReturnsProductSuggestString(): void
    {
        static::assertSame('product_suggest', ProductSuggestDataLoader::getRequirementType());
    }

    #[TestDox('returns suggest listing result as data and marks result as cache-aware with no tags')]
    public function testLoadReturnsCachedExternallyResultWithSuggestData(): void
    {
        $config = new ProductSuggestLoaderConfig();
        $requirement = new DataRequirement('suggest', 'product_suggest', $config);
        $element = ContentElementBuilder::create('suggest')
            ->withProperty('searchTerm', 'shoes')
            ->build();
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        $this->suggestRoute
            ->method('load')
            ->willReturn($response);

        $result = $this->loader->load($element, $requirement, $context, $request);

        static::assertSame($listingResult, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('sets search term on cloned request POST body for route consumption')]
    public function testLoadSetsSearchTermOnClonedRequestBody(): void
    {
        $config = new ProductSuggestLoaderConfig();
        $requirement = new DataRequirement('suggest', 'product_suggest', $config);
        $element = ContentElementBuilder::create('suggest')
            ->withProperty('searchTerm', 'running shoes')
            ->build();
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Request|null $capturedRequest */
        $capturedRequest = null;
        $this->suggestRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): ProductSuggestRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, $request);

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('running shoes', $capturedRequest->request->get('search'));
        static::assertNotSame($request, $capturedRequest);
    }

    #[TestDox('reads search term from custom property name when configured')]
    public function testLoadUsesCustomSearchTermPropertyFromConfig(): void
    {
        $config = new ProductSuggestLoaderConfig(searchTermProperty: 'query');
        $requirement = new DataRequirement('suggest', 'product_suggest', $config);
        $element = ContentElementBuilder::create('suggest')
            ->withProperty('query', 'blue shirt')
            ->build();
        $context = Generator::generateSalesChannelContext();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Request|null $capturedRequest */
        $capturedRequest = null;
        $this->suggestRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): ProductSuggestRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('blue shirt', $capturedRequest->request->get('search'));
    }

    #[TestDox('adds config associations to criteria when loading suggestions')]
    public function testLoadAddsConfigAssociationsToCriteria(): void
    {
        $config = new ProductSuggestLoaderConfig(associations: ['manufacturer', 'cover']);
        $requirement = new DataRequirement('suggest', 'product_suggest', $config);
        $element = ContentElementBuilder::create('suggest')
            ->withProperty('searchTerm', 'shoes')
            ->build();
        $context = Generator::generateSalesChannelContext();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $this->suggestRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductSuggestRouteResponse {
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
        $config = new ProductSuggestLoaderConfig(associations: ['manufacturer']);
        $requirement = new DataRequirement('suggest', 'product_suggest', $config);
        $element = ContentElementBuilder::create('suggest')
            ->withProperty('searchTerm', 'shoes')
            ->withProperty('associations', ['cover', 'media'])
            ->build();
        $context = Generator::generateSalesChannelContext();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $this->suggestRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductSuggestRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('manufacturer', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('media', $capturedCriteria->getAssociations());
    }

    #[TestDox('returns notFound result when config is not a ProductSuggestLoaderConfig instance')]
    public function testLoadReturnsNotFoundWhenConfigIsWrongType(): void
    {
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('suggest', 'product_suggest', $wrongConfig);
        $element = ContentElementBuilder::create('suggest')->build();
        $context = Generator::generateSalesChannelContext();

        $this->suggestRoute->expects($this->never())->method('load');

        $result = $this->loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when search term element property is an empty string')]
    public function testLoadReturnsNotFoundWhenSearchTermPropertyIsEmptyString(): void
    {
        $config = new ProductSuggestLoaderConfig();
        $element = ContentElementBuilder::create('suggest')
            ->withProperty('searchTerm', '')
            ->build();
        $context = Generator::generateSalesChannelContext();

        $this->suggestRoute->expects($this->never())->method('load');

        $result = $this->loader->load(
            $element,
            new DataRequirement('suggest', 'product_suggest', $config),
            $context,
            new Request()
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
    }

    #[DataProvider('guardsInvalidSearchTermProvider')]
    #[TestDox('returns notFound result when searchTerm is invalid: $_dataName')]
    public function testLoadReturnsNotFoundWhenSearchTermPropertyIsInvalid(ContentElement $element): void
    {
        $config = new ProductSuggestLoaderConfig();
        $context = Generator::generateSalesChannelContext();

        $this->suggestRoute->expects($this->never())->method('load');

        $result = $this->loader->load(
            $element,
            new DataRequirement('suggest', 'product_suggest', $config),
            $context,
            new Request()
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    /**
     * @return iterable<string, array{ContentElement}>
     */
    public static function guardsInvalidSearchTermProvider(): iterable
    {
        yield 'non-string value triggers guard' => [
            ContentElementBuilder::create('suggest')->withProperty('searchTerm', 42)->build(),
        ];
        yield 'missing property triggers guard' => [
            ContentElementBuilder::create('suggest')->build(),
        ];
    }
}

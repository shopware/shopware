<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductReviewDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductReviewLoaderConfig;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewRouteResponse;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ProductReviewDataLoader::class)]
class ProductReviewDataLoaderTest extends TestCase
{
    private AbstractProductReviewRoute&MockObject $productReviewRoute;

    private ProductReviewDataLoader $loader;

    protected function setUp(): void
    {
        $this->productReviewRoute = $this->createMock(AbstractProductReviewRoute::class);
        $this->loader = new ProductReviewDataLoader($this->productReviewRoute);
    }

    #[TestDox('returns product_review as requirement type identifier')]
    public function testGetRequirementTypeReturnsProductReviewString(): void
    {
        static::assertSame('product_review', ProductReviewDataLoader::getRequirementType());
    }

    #[TestDox('returns review search result as data and marks result as cache-aware with no tags')]
    public function testLoadReturnsCachedExternallyResultWithReviewData(): void
    {
        $productId = Uuid::randomHex();

        $config = new ProductReviewLoaderConfig();
        $requirement = new DataRequirement('reviews', 'product_review', $config);
        $element = ContentElementBuilder::create('product-reviews')
            ->withProperty('productId', $productId)
            ->build();
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        /** @var EntitySearchResult<ProductReviewCollection> $reviewResult */
        $reviewResult = static::createStub(EntitySearchResult::class);
        $response = static::createStub(ProductReviewRouteResponse::class);
        $response->method('getResult')->willReturn($reviewResult);

        $this->productReviewRoute
            ->method('load')
            ->with($productId, $request, $context, static::isInstanceOf(Criteria::class))
            ->willReturn($response);

        $result = $this->loader->load($element, $requirement, $context, $request);

        static::assertSame($reviewResult, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lowercases productId before passing it to the review route')]
    public function testLoadCallsReviewRouteWithLowercasedProductId(): void
    {
        $productId = Uuid::randomHex();
        $upperCaseId = strtoupper($productId);

        $config = new ProductReviewLoaderConfig();
        $requirement = new DataRequirement('reviews', 'product_review', $config);
        $element = ContentElementBuilder::create('product-reviews')
            ->withProperty('productId', $upperCaseId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        /** @var EntitySearchResult<ProductReviewCollection> $reviewResult */
        $reviewResult = static::createStub(EntitySearchResult::class);
        $response = static::createStub(ProductReviewRouteResponse::class);
        $response->method('getResult')->willReturn($reviewResult);

        $capturedProductId = null;
        $this->productReviewRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId) use (&$capturedProductId, $response): ProductReviewRouteResponse {
                $capturedProductId = $prodId;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertSame($productId, $capturedProductId);
    }

    #[TestDox('reads productId from custom property name when configured')]
    public function testLoadUsesCustomPropertyNameFromConfig(): void
    {
        $productId = Uuid::randomHex();

        $config = new ProductReviewLoaderConfig(property: 'reviewProductId');
        $requirement = new DataRequirement('reviews', 'product_review', $config);
        $element = ContentElementBuilder::create('product-reviews')
            ->withProperty('reviewProductId', $productId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $capturedProductId = null;
        /** @var EntitySearchResult<ProductReviewCollection> $reviewResult */
        $reviewResult = static::createStub(EntitySearchResult::class);
        $response = static::createStub(ProductReviewRouteResponse::class);
        $response->method('getResult')->willReturn($reviewResult);

        $this->productReviewRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId) use (&$capturedProductId, $response): ProductReviewRouteResponse {
                $capturedProductId = $prodId;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertSame($productId, $capturedProductId);
    }

    #[TestDox('adds config associations to criteria when loading reviews')]
    public function testLoadAddsConfigAssociationsToCriteria(): void
    {
        $productId = Uuid::randomHex();

        $config = new ProductReviewLoaderConfig(associations: ['customer', 'product']);
        $requirement = new DataRequirement('reviews', 'product_review', $config);
        $element = ContentElementBuilder::create('product-reviews')
            ->withProperty('productId', $productId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        /** @var EntitySearchResult<ProductReviewCollection> $reviewResult */
        $reviewResult = static::createStub(EntitySearchResult::class);
        $response = static::createStub(ProductReviewRouteResponse::class);
        $response->method('getResult')->willReturn($reviewResult);

        $this->productReviewRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductReviewRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('customer', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('product', $capturedCriteria->getAssociations());
    }

    #[TestDox('merges element associations property into criteria when it is an array of strings')]
    public function testLoadMergesElementAssociationsIntoCriteria(): void
    {
        $productId = Uuid::randomHex();

        $config = new ProductReviewLoaderConfig(associations: ['customer']);
        $requirement = new DataRequirement('reviews', 'product_review', $config);
        $element = ContentElementBuilder::create('product-reviews')
            ->withProperty('productId', $productId)
            ->withProperty('associations', ['product', 'media'])
            ->build();
        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        /** @var EntitySearchResult<ProductReviewCollection> $reviewResult */
        $reviewResult = static::createStub(EntitySearchResult::class);
        $response = static::createStub(ProductReviewRouteResponse::class);
        $response->method('getResult')->willReturn($reviewResult);

        $this->productReviewRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductReviewRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('customer', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('product', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('media', $capturedCriteria->getAssociations());
    }

    #[TestDox('returns notFound result when config is not a ProductReviewLoaderConfig instance')]
    public function testLoadReturnsNotFoundWhenConfigIsWrongType(): void
    {
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('reviews', 'product_review', $wrongConfig);
        $element = ContentElementBuilder::create('product-reviews')->build();
        $context = Generator::generateSalesChannelContext();

        $this->productReviewRoute->expects($this->never())->method('load');

        $result = $this->loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when productId element property is not a string')]
    public function testLoadReturnsNotFoundWhenProductIdPropertyIsNotString(): void
    {
        $config = new ProductReviewLoaderConfig();

        $element = ContentElementBuilder::create('product-reviews')
            ->withProperty('productId', 42)
            ->build();

        $context = Generator::generateSalesChannelContext();

        $this->productReviewRoute->expects($this->never())->method('load');

        $result = $this->loader->load(
            $element,
            new DataRequirement('reviews', 'product_review', $config),
            $context,
            new Request()
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when productId element property is missing')]
    public function testLoadReturnsNotFoundWhenProductIdPropertyIsMissing(): void
    {
        $config = new ProductReviewLoaderConfig();

        $element = ContentElementBuilder::create('product-reviews')->build();

        $context = Generator::generateSalesChannelContext();

        $this->productReviewRoute->expects($this->never())->method('load');

        $result = $this->loader->load(
            $element,
            new DataRequirement('reviews', 'product_review', $config),
            $context,
            new Request()
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\CrossSellingDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\CrossSellingLoaderConfig;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\AbstractProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\ProductCrossSellingRouteResponse;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CrossSellingDataLoader::class)]
class CrossSellingDataLoaderTest extends TestCase
{
    private AbstractProductCrossSellingRoute&MockObject $crossSellingRoute;

    private CrossSellingDataLoader $loader;

    protected function setUp(): void
    {
        $this->crossSellingRoute = $this->createMock(AbstractProductCrossSellingRoute::class);
        $this->loader = new CrossSellingDataLoader($this->crossSellingRoute);
    }

    #[TestDox('returns cross_selling as requirement type identifier')]
    public function testGetRequirementTypeReturnsCrossSellingString(): void
    {
        static::assertSame('cross_selling', CrossSellingDataLoader::getRequirementType());
    }

    #[TestDox('resolves provided data type from annotation')]
    public function testGetProvidedDataResolvesExpectedType(): void
    {
        $descriptor = CrossSellingDataLoader::getProvidedData();

        static::assertSame(CrossSellingElementCollection::class, $descriptor->className);
        static::assertSame([], $descriptor->genericParameters);
    }

    #[TestDox('returns cross-selling collection as data and marks result as cache-aware with no tags')]
    public function testLoadReturnsCachedExternallyResultWithCrossSellingData(): void
    {
        $productId = Uuid::randomHex();

        $config = new CrossSellingLoaderConfig();
        $requirement = new DataRequirement('cross-selling', 'cross_selling', $config);
        $element = ContentElementBuilder::create('cross-selling')
            ->withProperty('productId', $productId)
            ->build();
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $crossSellingCollection = new CrossSellingElementCollection();
        $response = static::createStub(ProductCrossSellingRouteResponse::class);
        $response->method('getResult')->willReturn($crossSellingCollection);

        $this->crossSellingRoute
            ->method('load')
            ->with($productId, $request, $context, static::isInstanceOf(Criteria::class))
            ->willReturn($response);

        $result = $this->loader->load($element, $requirement, $context, $request);

        static::assertSame($crossSellingCollection, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lowercases productId before passing it to the cross-selling route')]
    public function testLoadCallsCrossSellingRouteWithLowercasedProductId(): void
    {
        $productId = Uuid::randomHex();
        $upperCaseId = strtoupper($productId);

        $config = new CrossSellingLoaderConfig();
        $requirement = new DataRequirement('cross-selling', 'cross_selling', $config);
        $element = ContentElementBuilder::create('cross-selling')
            ->withProperty('productId', $upperCaseId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $crossSellingCollection = new CrossSellingElementCollection();
        $response = static::createStub(ProductCrossSellingRouteResponse::class);
        $response->method('getResult')->willReturn($crossSellingCollection);

        $capturedProductId = null;
        $this->crossSellingRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId) use (&$capturedProductId, $response): ProductCrossSellingRouteResponse {
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

        $config = new CrossSellingLoaderConfig(property: 'mainProductId');
        $requirement = new DataRequirement('cross-selling', 'cross_selling', $config);
        $element = ContentElementBuilder::create('cross-selling')
            ->withProperty('mainProductId', $productId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $capturedProductId = null;
        $crossSellingCollection = new CrossSellingElementCollection();
        $response = static::createStub(ProductCrossSellingRouteResponse::class);
        $response->method('getResult')->willReturn($crossSellingCollection);

        $this->crossSellingRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId) use (&$capturedProductId, $response): ProductCrossSellingRouteResponse {
                $capturedProductId = $prodId;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertSame($productId, $capturedProductId);
    }

    #[TestDox('adds config associations to criteria when loading cross-selling')]
    public function testLoadAddsConfigAssociationsToCriteria(): void
    {
        $productId = Uuid::randomHex();

        $config = new CrossSellingLoaderConfig(associations: ['manufacturer', 'cover']);
        $requirement = new DataRequirement('cross-selling', 'cross_selling', $config);
        $element = ContentElementBuilder::create('cross-selling')
            ->withProperty('productId', $productId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $crossSellingCollection = new CrossSellingElementCollection();
        $response = static::createStub(ProductCrossSellingRouteResponse::class);
        $response->method('getResult')->willReturn($crossSellingCollection);

        $this->crossSellingRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductCrossSellingRouteResponse {
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
        $productId = Uuid::randomHex();

        $config = new CrossSellingLoaderConfig(associations: ['manufacturer']);
        $requirement = new DataRequirement('cross-selling', 'cross_selling', $config);
        $element = ContentElementBuilder::create('cross-selling')
            ->withProperty('productId', $productId)
            ->withProperty('associations', ['cover', 'media'])
            ->build();
        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $crossSellingCollection = new CrossSellingElementCollection();
        $response = static::createStub(ProductCrossSellingRouteResponse::class);
        $response->method('getResult')->willReturn($crossSellingCollection);

        $this->crossSellingRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductCrossSellingRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('manufacturer', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('media', $capturedCriteria->getAssociations());
    }

    #[TestDox('returns notFound result when config is not a CrossSellingLoaderConfig instance')]
    public function testLoadReturnsNotFoundWhenConfigIsWrongType(): void
    {
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('cross-selling', 'cross_selling', $wrongConfig);
        $element = ContentElementBuilder::create('cross-selling')->build();
        $context = Generator::generateSalesChannelContext();

        $this->crossSellingRoute->expects($this->never())->method('load');

        $result = $this->loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[DataProvider('guardsInvalidProductIdProvider')]
    #[TestDox('returns notFound result when productId is invalid: $_dataName')]
    public function testLoadReturnsNotFoundWhenProductIdPropertyIsInvalid(ContentElement $element): void
    {
        $config = new CrossSellingLoaderConfig();
        $context = Generator::generateSalesChannelContext();

        $this->crossSellingRoute->expects($this->never())->method('load');

        $result = $this->loader->load(
            $element,
            new DataRequirement('cross-selling', 'cross_selling', $config),
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
    public static function guardsInvalidProductIdProvider(): iterable
    {
        yield 'non-string value triggers guard' => [
            ContentElementBuilder::create('cross-selling')->withProperty('productId', 42)->build(),
        ];
        yield 'missing property triggers guard' => [
            ContentElementBuilder::create('cross-selling')->build(),
        ];
    }
}

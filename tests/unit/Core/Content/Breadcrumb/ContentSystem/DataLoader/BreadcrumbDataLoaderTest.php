<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Breadcrumb\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Breadcrumb\ContentSystem\DataLoader\BreadcrumbDataLoader;
use Shopware\Core\Content\Breadcrumb\ContentSystem\DataLoader\BreadcrumbLoaderConfig;
use Shopware\Core\Content\Breadcrumb\SalesChannel\AbstractBreadcrumbRoute;
use Shopware\Core\Content\Breadcrumb\SalesChannel\BreadcrumbRouteResponse;
use Shopware\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BreadcrumbDataLoader::class)]
class BreadcrumbDataLoaderTest extends TestCase
{
    private AbstractBreadcrumbRoute&MockObject $breadcrumbRoute;

    private BreadcrumbDataLoader $loader;

    protected function setUp(): void
    {
        $this->breadcrumbRoute = $this->createMock(AbstractBreadcrumbRoute::class);
        $this->loader = new BreadcrumbDataLoader($this->breadcrumbRoute);
    }

    #[TestDox('returns breadcrumb as requirement type identifier')]
    public function testGetRequirementTypeReturnsBreadcrumbString(): void
    {
        static::assertSame('breadcrumb', BreadcrumbDataLoader::getRequirementType());
    }

    #[TestDox('returns breadcrumb collection as data and marks result as cache-aware with no tags')]
    public function testLoadReturnsCachedExternallyResultWithBreadcrumbData(): void
    {
        $entityId = Uuid::randomHex();

        $config = new BreadcrumbLoaderConfig();
        $requirement = new DataRequirement('breadcrumb', 'breadcrumb', $config);
        $element = ContentElementBuilder::create('breadcrumb')
            ->withProperty('entityId', $entityId)
            ->build();
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $breadcrumbCollection = new BreadcrumbCollection();
        $response = static::createStub(BreadcrumbRouteResponse::class);
        $response->method('getBreadcrumbCollection')->willReturn($breadcrumbCollection);

        $this->breadcrumbRoute
            ->method('load')
            ->willReturn($response);

        $result = $this->loader->load($element, $requirement, $context, $request);

        static::assertSame($breadcrumbCollection, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('sets entity ID on cloned request attributes and type on query params')]
    public function testLoadSetsIdAndTypeOnClonedRequest(): void
    {
        $entityId = Uuid::randomHex();

        $config = new BreadcrumbLoaderConfig(type: 'category');
        $requirement = new DataRequirement('breadcrumb', 'breadcrumb', $config);
        $element = ContentElementBuilder::create('breadcrumb')
            ->withProperty('entityId', $entityId)
            ->build();
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $breadcrumbCollection = new BreadcrumbCollection();
        $response = static::createStub(BreadcrumbRouteResponse::class);
        $response->method('getBreadcrumbCollection')->willReturn($breadcrumbCollection);

        $capturedRequest = null;
        $this->breadcrumbRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): BreadcrumbRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, $request);

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame($entityId, $capturedRequest->attributes->get('id'));
        static::assertSame('category', $capturedRequest->query->get('type'));
    }

    #[TestDox('lowercases entity ID before passing it to the breadcrumb route')]
    public function testLoadCallsBreadcrumbRouteWithLowercasedEntityId(): void
    {
        $entityId = Uuid::randomHex();
        $upperCaseId = strtoupper($entityId);

        $config = new BreadcrumbLoaderConfig();
        $requirement = new DataRequirement('breadcrumb', 'breadcrumb', $config);
        $element = ContentElementBuilder::create('breadcrumb')
            ->withProperty('entityId', $upperCaseId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $breadcrumbCollection = new BreadcrumbCollection();
        $response = static::createStub(BreadcrumbRouteResponse::class);
        $response->method('getBreadcrumbCollection')->willReturn($breadcrumbCollection);

        $capturedRequest = null;
        $this->breadcrumbRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): BreadcrumbRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame($entityId, $capturedRequest->attributes->get('id'));
    }

    #[TestDox('reads entity ID from custom property name when configured')]
    public function testLoadUsesCustomPropertyNameFromConfig(): void
    {
        $entityId = Uuid::randomHex();

        $config = new BreadcrumbLoaderConfig(property: 'categoryId');
        $requirement = new DataRequirement('breadcrumb', 'breadcrumb', $config);
        $element = ContentElementBuilder::create('breadcrumb')
            ->withProperty('categoryId', $entityId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $breadcrumbCollection = new BreadcrumbCollection();
        $response = static::createStub(BreadcrumbRouteResponse::class);
        $response->method('getBreadcrumbCollection')->willReturn($breadcrumbCollection);

        $capturedRequest = null;
        $this->breadcrumbRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): BreadcrumbRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame($entityId, $capturedRequest->attributes->get('id'));
    }

    #[TestDox('sets referrerCategoryId on cloned request when referrerCategoryProperty is configured')]
    public function testLoadSetsReferrerCategoryIdOnRequest(): void
    {
        $entityId = Uuid::randomHex();
        $referrerCategoryId = Uuid::randomHex();

        $config = new BreadcrumbLoaderConfig(referrerCategoryProperty: 'referrerCategory');
        $requirement = new DataRequirement('breadcrumb', 'breadcrumb', $config);
        $element = ContentElementBuilder::create('breadcrumb')
            ->withProperty('entityId', $entityId)
            ->withProperty('referrerCategory', $referrerCategoryId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $breadcrumbCollection = new BreadcrumbCollection();
        $response = static::createStub(BreadcrumbRouteResponse::class);
        $response->method('getBreadcrumbCollection')->willReturn($breadcrumbCollection);

        $capturedRequest = null;
        $this->breadcrumbRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): BreadcrumbRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame($referrerCategoryId, $capturedRequest->query->get('referrerCategoryId'));
    }

    #[TestDox('does not set referrerCategoryId when referrerCategoryProperty value is not a string')]
    public function testLoadDoesNotSetReferrerCategoryIdWhenValueIsNotString(): void
    {
        $entityId = Uuid::randomHex();

        $config = new BreadcrumbLoaderConfig(referrerCategoryProperty: 'referrerCategory');
        $requirement = new DataRequirement('breadcrumb', 'breadcrumb', $config);
        $element = ContentElementBuilder::create('breadcrumb')
            ->withProperty('entityId', $entityId)
            ->withProperty('referrerCategory', 42)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $breadcrumbCollection = new BreadcrumbCollection();
        $response = static::createStub(BreadcrumbRouteResponse::class);
        $response->method('getBreadcrumbCollection')->willReturn($breadcrumbCollection);

        $capturedRequest = null;
        $this->breadcrumbRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): BreadcrumbRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertFalse($capturedRequest->query->has('referrerCategoryId'));
    }

    #[TestDox('returns notFound result when config is not a BreadcrumbLoaderConfig instance')]
    public function testLoadReturnsNotFoundWhenConfigIsWrongType(): void
    {
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('breadcrumb', 'breadcrumb', $wrongConfig);
        $element = ContentElementBuilder::create('breadcrumb')->build();
        $context = Generator::generateSalesChannelContext();

        $this->breadcrumbRoute->expects($this->never())->method('load');

        $result = $this->loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when entityId element property is not a string')]
    public function testLoadReturnsNotFoundWhenEntityIdPropertyIsNotString(): void
    {
        $config = new BreadcrumbLoaderConfig();

        $element = ContentElementBuilder::create('breadcrumb')
            ->withProperty('entityId', 42)
            ->build();

        $context = Generator::generateSalesChannelContext();

        $this->breadcrumbRoute->expects($this->never())->method('load');

        $result = $this->loader->load(
            $element,
            new DataRequirement('breadcrumb', 'breadcrumb', $config),
            $context,
            new Request()
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when entityId element property is missing')]
    public function testLoadReturnsNotFoundWhenEntityIdPropertyIsMissing(): void
    {
        $config = new BreadcrumbLoaderConfig();

        $element = ContentElementBuilder::create('breadcrumb')->build();

        $context = Generator::generateSalesChannelContext();

        $this->breadcrumbRoute->expects($this->never())->method('load');

        $result = $this->loader->load(
            $element,
            new DataRequirement('breadcrumb', 'breadcrumb', $config),
            $context,
            new Request()
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
    }
}

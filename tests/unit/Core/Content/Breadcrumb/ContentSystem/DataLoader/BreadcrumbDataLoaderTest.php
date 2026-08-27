<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Breadcrumb\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Breadcrumb\ContentSystem\DataLoader\BreadcrumbDataLoader;
use Shopware\Core\Content\Breadcrumb\ContentSystem\DataLoader\BreadcrumbLoaderConfig;
use Shopware\Core\Content\Breadcrumb\SalesChannel\AbstractBreadcrumbRoute;
use Shopware\Core\Content\Breadcrumb\SalesChannel\BreadcrumbRouteResponse;
use Shopware\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(BreadcrumbDataLoader::class)]
class BreadcrumbDataLoaderTest extends TestCase
{
    private AbstractBreadcrumbRoute&Stub $breadcrumbRoute;

    private BreadcrumbDataLoader $loader;

    private ?Request $capturedRequest = null;

    protected function setUp(): void
    {
        $this->capturedRequest = null;

        $response = new BreadcrumbRouteResponse(new BreadcrumbCollection());

        $this->breadcrumbRoute = static::createStub(AbstractBreadcrumbRoute::class);
        $this->breadcrumbRoute
            ->method('load')
            ->willReturnCallback(function (Request $request) use ($response): BreadcrumbRouteResponse {
                $this->capturedRequest = $request;

                return $response;
            });

        $this->loader = new BreadcrumbDataLoader($this->breadcrumbRoute);
    }

    #[TestDox('returns breadcrumb as requirement type identifier')]
    public function testGetRequirementTypeReturnsBreadcrumbString(): void
    {
        static::assertSame('breadcrumb', BreadcrumbDataLoader::getRequirementType());
    }

    #[TestDox('declares BreadcrumbCollection as its single producible type')]
    public function testProducibleTypesReturnsSingleBreadcrumbCapability(): void
    {
        $capabilities = $this->loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(BreadcrumbCollection::class, $capabilities[0]->producedType);
        static::assertSame([], $capabilities[0]->genericParameters);
        static::assertSame([], $capabilities[0]->configTemplate);
    }

    #[TestDox('returns breadcrumb collection as data and marks result as cache-aware with no tags')]
    public function testLoadReturnsCachedExternallyResultWithBreadcrumbData(): void
    {
        $breadcrumbCollection = new BreadcrumbCollection();
        $response = new BreadcrumbRouteResponse($breadcrumbCollection);

        $breadcrumbRoute = static::createStub(AbstractBreadcrumbRoute::class);
        $breadcrumbRoute->method('load')->willReturn($response);

        $loader = new BreadcrumbDataLoader($breadcrumbRoute);
        $result = $loader->load(
            self::inputs('product-alice'),
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertSame($breadcrumbCollection, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('sets entity ID on cloned request attributes and type on query params')]
    public function testLoadSetsIdAndTypeOnClonedRequest(): void
    {
        $this->loader->load(
            self::inputs('product-alice', type: 'category'),
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(Request::class, $this->capturedRequest);
        static::assertSame('product-alice', $this->capturedRequest->attributes->get('id'));
        static::assertSame('category', $this->capturedRequest->query->get('type'));
    }

    #[TestDox('lowercases entity ID before passing it to the breadcrumb route')]
    public function testLoadCallsBreadcrumbRouteWithLowercasedEntityId(): void
    {
        $this->loader->load(
            self::inputs('PRODUCT-ALICE'),
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(Request::class, $this->capturedRequest);
        static::assertSame('product-alice', $this->capturedRequest->attributes->get('id'));
    }

    #[TestDox('reads entity ID from the element property the config names')]
    public function testLoadReadsEntityIdFromCustomProperty(): void
    {
        $inputs = $this->resolve(
            new BreadcrumbLoaderConfig(property: 'categoryId'),
            ['categoryId' => 'category-alice'],
        );

        $this->loader->load(
            $inputs,
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(Request::class, $this->capturedRequest);
        static::assertSame('category-alice', $this->capturedRequest->attributes->get('id'));
    }

    #[TestDox('resolves an unset property to the declared entityId default')]
    public function testUnsetPropertyResolvesToDeclaredEntityIdDefault(): void
    {
        $inputs = $this->resolve(new BreadcrumbLoaderConfig(), ['entityId' => 'product-alice']);

        $this->loader->load(
            $inputs,
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(Request::class, $this->capturedRequest);
        static::assertSame('product-alice', $this->capturedRequest->attributes->get('id'));
    }

    #[TestDox('sets lowercased referrerCategoryId on cloned request when the referrer input is resolved')]
    public function testLoadSetsReferrerCategoryIdOnRequest(): void
    {
        $this->loader->load(
            self::inputs('product-alice', referrerCategoryProperty: 'CATEGORY-BOB'),
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(Request::class, $this->capturedRequest);
        static::assertSame('category-bob', $this->capturedRequest->query->get('referrerCategoryId'));
    }

    #[TestDox('does not set referrerCategoryId when the referrer input is unresolved')]
    public function testLoadDoesNotSetReferrerCategoryIdWhenReferrerInputIsUnresolved(): void
    {
        $this->loader->load(
            self::inputs('product-alice'),
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(Request::class, $this->capturedRequest);
        static::assertFalse($this->capturedRequest->query->has('referrerCategoryId'));
    }

    #[TestDox('returns notFound result when the entity ID input is unresolved')]
    public function testLoadReturnsNotFoundWhenEntityIdInputIsUnresolved(): void
    {
        $breadcrumbRoute = $this->createMock(AbstractBreadcrumbRoute::class);
        $breadcrumbRoute->expects($this->never())->method('load');
        $loader = new BreadcrumbDataLoader($breadcrumbRoute);

        $result = $loader->load(
            self::inputs(null),
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function resolve(BreadcrumbLoaderConfig $config, array $properties): LoaderInputs
    {
        return (new LoaderInputResolver())->resolve($this->loader->configSpecification(), $config, $properties);
    }

    private static function inputs(
        ?string $property,
        string $type = 'product',
        ?string $referrerCategoryProperty = null,
    ): LoaderInputs {
        return new LoaderInputs([
            'property' => $property,
            'type' => $type,
            'referrerCategoryProperty' => $referrerCategoryProperty,
        ]);
    }

    private static function requirement(): DataRequirement
    {
        return new DataRequirement('breadcrumb', 'breadcrumb', new BreadcrumbLoaderConfig());
    }
}

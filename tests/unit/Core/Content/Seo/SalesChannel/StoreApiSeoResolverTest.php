<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductListResponse;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\SalesChannel\StoreApiSeoResolver;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Content\Test\TestProductSeoUrlRoute;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SingleFieldFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(StoreApiSeoResolver::class)]
class StoreApiSeoResolverTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $subscribedEvents = StoreApiSeoResolver::getSubscribedEvents();

        static::assertCount(1, $subscribedEvents);
        static::assertArrayHasKey(KernelEvents::RESPONSE, $subscribedEvents);
        static::assertSame('addSeoInformation', $subscribedEvents[KernelEvents::RESPONSE][0]);
        static::assertSame(11000, $subscribedEvents[KernelEvents::RESPONSE][1]);
    }

    public function testAddSeoInformation(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT,
            static::createStub(SalesChannelContext::class),
        );

        $productEntity = $this->createProductEntity();
        $response = new ProductListResponse(new EntitySearchResult(
            'product',
            1,
            new ProductCollection([$productEntity]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        ));

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        static::assertEmpty($productEntity->getSeoUrls());

        $storeApiSeoResolver = $this->createStoreApiSeoResolver();
        $storeApiSeoResolver->addSeoInformation($event);

        static::assertNotEmpty($productEntity->getSeoUrls());
    }

    public function testAddSeoWithRepeatedEntity(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT,
            static::createStub(SalesChannelContext::class),
        );

        $productEntity = $this->createProductEntity();

        // two products with same unique identifier
        $crossSellingProduct1 = $this->createProductEntity('cross-selling-product');
        $crossSellingProduct2 = $this->createProductEntity('cross-selling-product');

        $crossSellingProductEntity1 = new ProductCrossSellingEntity();
        $crossSellingProductEntity1->setUniqueIdentifier('cross-item-1');
        $crossSellingProductEntity1->setProduct($crossSellingProduct1);

        $crossSellingProductEntity2 = new ProductCrossSellingEntity();
        $crossSellingProductEntity2->setUniqueIdentifier('cross-item-2');
        $crossSellingProductEntity2->setProduct($crossSellingProduct2);

        $productCrossSellingCollection = new ProductCrossSellingCollection([$crossSellingProductEntity1, $crossSellingProductEntity2]);

        $productEntity->setCrossSellings($productCrossSellingCollection);

        $response = new ProductListResponse(new EntitySearchResult(
            'product',
            1,
            new ProductCollection([$productEntity]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        ));

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        static::assertEmpty($productEntity->getSeoUrls());

        $storeApiSeoResolver = $this->createStoreApiSeoResolver(['random', 'cross-selling-product']);
        $storeApiSeoResolver->addSeoInformation($event);

        static::assertNotEmpty($productEntity->getSeoUrls());

        $crossSellingProduct1 = $productEntity->getCrossSellings()?->get('cross-item-1');

        static::assertInstanceOf(ProductCrossSellingEntity::class, $crossSellingProduct1);
        static::assertInstanceOf(SalesChannelProductEntity::class, $outputProduct1 = $crossSellingProduct1->getProduct());
        static::assertSame('cross-selling-product', $outputProduct1->getUniqueIdentifier());
        static::assertNotNull($outputProduct1->getSeoUrls());

        $crossSellingProduct2 = $productEntity->getCrossSellings()?->get('cross-item-2');

        static::assertInstanceOf(ProductCrossSellingEntity::class, $crossSellingProduct2);
        static::assertInstanceOf(SalesChannelProductEntity::class, $outputProduct2 = $crossSellingProduct2->getProduct());
        static::assertSame('cross-selling-product', $outputProduct2->getUniqueIdentifier());
        static::assertNotNull($outputProduct2->getSeoUrls());
    }

    public function testAddSeoInformationWithExtensions(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT,
            static::createStub(SalesChannelContext::class),
        );

        $searchResult = new EntitySearchResult(
            'product',
            0,
            new ProductCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $product = $this->createProductEntity();

        $result = new MockSeoUrlAwareExtension();
        $result->addSearchResult($product);

        $searchResult->addExtension('multiSearchResult', $result);
        $response = new ProductListResponse($searchResult);

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        static::assertEmpty($product->getSeoUrls());

        $storeApiSeoResolver = $this->createStoreApiSeoResolver();
        $storeApiSeoResolver->addSeoInformation($event);

        static::assertNotEmpty($product->getSeoUrls());
    }

    public function testAddSeoInformationForSearchResultNestedInStructVars(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT,
            static::createStub(SalesChannelContext::class),
        );

        $product = $this->createProductEntity();
        $nestedResult = new EntitySearchResult(
            'product',
            1,
            new ProductCollection([$product]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $searchResult = new EntitySearchResult(
            'product',
            0,
            new ProductCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
        $searchResult->addExtension('cmsSlotData', new MockNestedSearchResultStruct($nestedResult));

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new ProductListResponse($searchResult)
        );

        static::assertEmpty($product->getSeoUrls());

        $storeApiSeoResolver = $this->createStoreApiSeoResolver();
        $storeApiSeoResolver->addSeoInformation($event);

        static::assertNotEmpty($product->getSeoUrls());
    }

    /**
     * @param callable(SalesChannelProductEntity): list<RenderedElement> $forest
     */
    #[DataProvider('renderedElementPlacementProvider')]
    #[TestDox('adds SEO information for rendered element placed at $_dataName')]
    public function testAddSeoInformationForRenderedElement(callable $forest): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT,
            static::createStub(SalesChannelContext::class),
        );

        $product = $this->createProductEntity();

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $this->createContentRouteResponse($forest($product)),
        );

        $this->createStoreApiSeoResolver()->addSeoInformation($event);

        static::assertSame(['random'], $this->collectedForeignKeys($product));
    }

    /**
     * @return iterable<string, array{callable(SalesChannelProductEntity): list<RenderedElement>}>
     */
    public static function renderedElementPlacementProvider(): iterable
    {
        yield 'directly under a property key' => [
            static fn (SalesChannelProductEntity $product): array => [
                new RenderedElement('element-1', 'product-box', ['product' => $product]),
            ],
        ];

        yield 'inside a list-valued property' => [
            static fn (SalesChannelProductEntity $product): array => [
                new RenderedElement('element-1', 'product-listing', ['products' => [$product]]),
            ],
        ];

        yield 'two slot levels deep' => [
            static fn (SalesChannelProductEntity $product): array => [
                new RenderedElement('root', 'section', [], [
                    'content' => [
                        new RenderedElement('middle', 'grid', [], [
                            'inner' => [
                                new RenderedElement('leaf', 'product-box', ['product' => $product]),
                            ],
                        ]),
                    ],
                ]),
            ],
        ];
    }

    #[TestDox('ignores non-Struct rendered property values (headline, publishedAt)')]
    public function testAddSeoInformationIgnoresNonStructRenderedPropertyValues(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT,
            static::createStub(SalesChannelContext::class),
        );

        $product = $this->createProductEntity();

        $element = new RenderedElement('element-1', 'product-box', [
            'headline' => 'A headline',
            'publishedAt' => new \DateTimeImmutable('2026-08-25 12:00:00'),
            'product' => $product,
        ]);

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $this->createContentRouteResponse([$element]),
        );

        $this->createStoreApiSeoResolver()->addSeoInformation($event);

        static::assertSame(['random'], $this->collectedForeignKeys($product));
    }

    /**
     * A rendered element sitting directly in another struct's vars, not inside an array. The page's `elements`
     * array never produces that placement, so the direct-variable filter has no other coverage.
     */
    #[TestDox('adds SEO information when rendered element is held directly in struct vars (not array)')]
    public function testAddSeoInformationForARenderedElementHeldDirectlyInStructVars(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT,
            static::createStub(SalesChannelContext::class),
        );

        $product = $this->createProductEntity();

        $searchResult = new EntitySearchResult(
            'product',
            0,
            new ProductCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
        $searchResult->addExtension('contentElement', new MockRenderedElementHolderStruct(
            new RenderedElement('element-1', 'product-box', ['product' => $product]),
        ));

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new ProductListResponse($searchResult),
        );

        $this->createStoreApiSeoResolver()->addSeoInformation($event);

        static::assertSame(['random'], $this->collectedForeignKeys($product));
    }

    #[DoesNotPerformAssertions]
    public function testResponseIsNotStoreApiResponse(): void
    {
        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );

        $storeApiSeoResolver = $this->createStoreApiSeoResolver();
        $storeApiSeoResolver->addSeoInformation($event);
    }

    public function testRequestHeaderDoesNotIncludeSeoUrls(): void
    {
        $productEntity = $this->createProductEntity();
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, static::createStub(SalesChannelContext::class));

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new ProductListResponse(new EntitySearchResult(
                'product',
                1,
                new ProductCollection([$productEntity]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            )),
        );

        $storeApiSeoResolver = $this->createStoreApiSeoResolver();
        $storeApiSeoResolver->addSeoInformation($event);

        static::assertNull($productEntity->getSeoUrls());
    }

    public function testContextIsNoSalesChannelContext(): void
    {
        $productEntity = $this->createProductEntity();

        $response = new ProductListResponse(new EntitySearchResult(
            'product',
            1,
            new ProductCollection([$productEntity]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        ));

        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT,
            Context::createDefaultContext(),
        );

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        $storeApiSeoResolver = $this->createStoreApiSeoResolver();
        $storeApiSeoResolver->addSeoInformation($event);

        static::assertNull($productEntity->getSeoUrls());
    }

    /**
     * @return list<string>
     */
    private function collectedForeignKeys(SalesChannelProductEntity $product): array
    {
        $seoUrls = $product->getSeoUrls();

        static::assertInstanceOf(SeoUrlCollection::class, $seoUrls);

        return array_values($seoUrls->map(static fn (SeoUrlEntity $seoUrl): string => $seoUrl->getForeignKey()));
    }

    /**
     * @param list<RenderedElement> $forest
     */
    private function createContentRouteResponse(array $forest): ContentRouteResponse
    {
        return new ContentRouteResponse(new RenderResult(
            $forest,
            LayoutReference::create('layout-id', 'Layout', null),
            null,
        ));
    }

    private function createProductEntity(string $identifier = 'random'): SalesChannelProductEntity
    {
        $productEntity = new SalesChannelProductEntity();
        $productEntity->internalSetEntityData('product', new FieldVisibility([]));
        $productEntity->setUniqueIdentifier($identifier);

        return $productEntity;
    }

    /**
     * @param array<string> $foreignKeys
     */
    private function createStoreApiSeoResolver(array $foreignKeys = ['random']): StoreApiSeoResolver
    {
        $definitionInstanceRegistry = $this->getDefinitionRegistry();

        $seoUrlCollection = new SeoUrlCollection();

        foreach ($foreignKeys as $foreignKey) {
            $seoUrlEntity = new SeoUrlEntity();
            $seoUrlEntity->setUniqueIdentifier('seo-url.' . $foreignKey);
            $seoUrlEntity->setForeignKey($foreignKey);

            $seoUrlCollection->add($seoUrlEntity);
        }

        $entitySearchResult = new EntitySearchResult(
            'seo_url',
            1,
            $seoUrlCollection,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $productDefinition = $definitionInstanceRegistry->getByClassOrEntityName('product');

        // not a PHPUnit assertion to avoid indirect assertions and hiding risky tests, narrows from EntityDefinition
        \assert($productDefinition instanceof ProductDefinition);

        /** @var StaticSalesChannelRepository<SeoUrlCollection> $salesChannelRepository */
        $salesChannelRepository = new StaticSalesChannelRepository([
            static function (Criteria $criteria) use ($entitySearchResult): EntitySearchResult {
                $fields = [];
                foreach ($criteria->getFilters() as $filter) {
                    if ($filter instanceof SingleFieldFilter) {
                        $fields[] = $filter->getField();
                    }
                }

                static::assertContains('foreignKey', $fields);
                static::assertContains('isCanonical', $fields);

                return $entitySearchResult;
            },
        ]);

        return new StoreApiSeoResolver(
            $salesChannelRepository,
            $definitionInstanceRegistry,
            static::createStub(SalesChannelDefinitionInstanceRegistry::class),
            new SeoUrlRouteRegistry([new TestProductSeoUrlRoute($productDefinition)]),
        );
    }

    private function getDefinitionRegistry(): DefinitionInstanceRegistry
    {
        return new StaticDefinitionInstanceRegistry(
            [
                ProductDefinition::class,
                SalesChannelProductDefinition::class,
                SeoUrlDefinition::class,
                ProductCrossSellingDefinition::class,
                ProductTranslationDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }
}

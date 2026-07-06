<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
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

    private function createProductEntity(string $identifier = 'random'): SalesChannelProductEntity
    {
        $productEntity = new SalesChannelProductEntity();
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
            'seoUrl',
            1,
            $seoUrlCollection,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $productDefinition = $definitionInstanceRegistry->getByClassOrEntityName('product');

        // not a PHPUnit assertion to avoid indirect assertions and hiding risky tests, narrows from EntityDefinition
        \assert($productDefinition instanceof ProductDefinition);

        $salesChannelRepository = static::createStub(SalesChannelRepository::class);
        $salesChannelRepository
            ->method('search')
            ->willReturn($entitySearchResult);

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

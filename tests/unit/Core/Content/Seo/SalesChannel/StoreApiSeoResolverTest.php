<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductListResponse;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\SalesChannel\StoreApiSeoResolver;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Content\Test\TestProductSeoUrlRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Seo\SalesChannel\StoreApiSeoResolver
 */
#[Package('buyers-experience')]
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
            $this->createMock(SalesChannelContext::class),
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
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        static::assertEmpty($productEntity->getSeoUrls());

        $storeApiSeoResolver = $this->createStoreApiSeoResolver();
        $storeApiSeoResolver->addSeoInformation($event);

        static::assertNotEmpty($productEntity->getSeoUrls());
    }

    public function testResponseIsNotStoreApiResponse(): void
    {
        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );

        $storeApiSeoResolver = $this->createStoreApiSeoResolver();
        $storeApiSeoResolver->addSeoInformation($event);

        // Implicitly asserts that no exception is thrown, since `getObject` does not exist here
    }

    public function testRequestHeaderDoesNotIncludeSeoUrls(): void
    {
        // @phpstan-ignore-next-line > Ignore PHPStan error, to be able to assert that this method has not been called
        $attributes = $this->createMock(ParameterBag::class);
        $attributes
            ->expects(static::never())
            ->method('get');

        $request = new Request();
        $request->attributes = $attributes;

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new ProductListResponse(new EntitySearchResult(
                'product',
                1,
                new ProductCollection([$this->createProductEntity()]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            )),
        );

        $storeApiSeoResolver = $this->createStoreApiSeoResolver();
        $storeApiSeoResolver->addSeoInformation($event);

        // Implicitly asserts that no exception is thrown, since `$this->enrich` would receive a wrong context
    }

    public function testContextIsNoSalesChannelContext(): void
    {
        $response = new ProductListResponse(new EntitySearchResult(
            'willneverbecalled',
            0,
            new ProductCollection([]),
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
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        $storeApiSeoResolver = $this->createStoreApiSeoResolver();
        $storeApiSeoResolver->addSeoInformation($event);
    }

    public function createProductEntity(): SalesChannelProductEntity
    {
        $productEntity = new SalesChannelProductEntity();
        $productEntity->setUniqueIdentifier('random');

        return $productEntity;
    }

    private function createStoreApiSeoResolver(): StoreApiSeoResolver
    {
        $productDefinition = $this->createMock(ProductDefinition::class);
        $productDefinition
            ->method('isSeoAware')
            ->willReturn(true);
        $productDefinition
            ->expects(static::atLeastOnce())
            ->method('getEntityName')
            ->willReturn('product');

        $salesChannelDefinitionInstanceRegistry = $this->createMock(SalesChannelDefinitionInstanceRegistry::class);
        $salesChannelDefinitionInstanceRegistry
            ->method('getByEntityClass')
            ->willReturn($productDefinition);

        $seoUrlEntity = new SeoUrlEntity();
        $seoUrlEntity->setUniqueIdentifier('seo-url');
        $seoUrlEntity->setForeignKey('random');

        $entitySearchResult = new EntitySearchResult(
            'seoUrl',
            1,
            new SeoUrlCollection([$seoUrlEntity]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $salesChannelRepository = $this->createMock(SalesChannelRepository::class);
        $salesChannelRepository
            ->method('search')
            ->willReturn($entitySearchResult);

        return new StoreApiSeoResolver(
            $salesChannelRepository,
            $this->createMock(DefinitionInstanceRegistry::class),
            $salesChannelDefinitionInstanceRegistry,
            new SeoUrlRouteRegistry([new TestProductSeoUrlRoute($productDefinition)]),
        );
    }
}

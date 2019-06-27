<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Seo\Subscriber;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Seo\SeoUrl\CanonicalUrlCollection;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Storefront\Framework\Seo\Subscriber\SeoUrlLoaderSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class SeoUrlLoaderSubscriberTest extends TestCase
{
    use KernelTestBehaviour;

    public function testAddCanonicalsSalesChannelApiContext(): void
    {
        $productDefinition = $this->getContainer()->get(ProductDefinition::class);
        $registry = $this->getContainer()->get(SeoUrlRouteRegistry::class);

        $salesChannelId = Uuid::randomHex();
        $productId = Uuid::randomHex();

        $seoUrl = new SeoUrlEntity();
        $seoUrl->setId(Uuid::randomHex());
        $seoUrl->setForeignKey($productId);
        $seoUrl->setSeoPathInfo('awesome-product');

        $salesChannelApiContext = $this->getSaleChannelApiContextMock($salesChannelId);
        $seoUrlRepo = $this->getSeoUrlRepoMock([$seoUrl], $salesChannelApiContext);

        $request = Request::create('https://shop.test/foo/bar');
        $request->attributes->set(RequestTransformer::SALES_CHANNEL_ABSOLUTE_BASE_URL, 'https://shop.test');
        $requestStack = $this->getRequestStackMock($request);
        $subscriber = new SeoUrlLoaderSubscriber($seoUrlRepo, $registry, $requestStack);

        /** @var ProductEntity|MockObject $product */
        $product = new ProductEntity();
        $product->setId($productId);

        $entities = [$product];
        $event = new EntityLoadedEvent($productDefinition, $entities, $salesChannelApiContext);
        $subscriber->addCanonicals($event);

        /** @var SeoUrlEntity $canonicalUrl */
        $canonicalUrl = $product->getExtension('canonicalUrl');

        static::assertNotEmpty($canonicalUrl);
        static::assertSame('awesome-product', $canonicalUrl->getSeoPathInfo());
        static::assertSame('https://shop.test/awesome-product', $canonicalUrl->getUrl());
    }

    public function testAddCanonicalsDefaultContext(): void
    {
        $registry = $this->getContainer()->get(SeoUrlRouteRegistry::class);
        $productDefinition = $this->getContainer()->get(ProductDefinition::class);

        $context = Context::createDefaultContext();
        $productId = Uuid::randomHex();

        $seoUrl = new SeoUrlEntity();
        $seoUrl->setId(Uuid::randomHex());
        $seoUrl->setForeignKey($productId);
        $seoUrl->setSeoPathInfo('awesome-product');

        $seoUrlRepo = $this->getSeoUrlRepoMock([$seoUrl], $context);
        $request = Request::create('https://shop.test/foo/bar');
        $request->attributes->set(RequestTransformer::SALES_CHANNEL_ABSOLUTE_BASE_URL, 'https://shop.test');
        $requestStack = $this->getRequestStackMock($request);
        $subscriber = new SeoUrlLoaderSubscriber($seoUrlRepo, $registry, $requestStack);

        /** @var ProductEntity|MockObject $product */
        $product = new ProductEntity();
        $product->setId($productId);

        $entities = [$product];
        $event = new EntityLoadedEvent($productDefinition, $entities, $context);
        $subscriber->addCanonicals($event);

        static::assertEmpty($product->getExtension('canonicalUrl'));
    }

    public function testAbsoluteSeoUrls(): void
    {
        $seoUrlDefinition = $this->getContainer()->get(SeoUrlDefinition::class);
        $registry = $this->getContainer()->get(SeoUrlRouteRegistry::class);

        $salesChannelId = Uuid::randomHex();
        $productId = Uuid::randomHex();

        $seoUrl1 = new SeoUrlEntity();
        $seoUrl1->setId(Uuid::randomHex());
        $seoUrl1->setForeignKey($productId);
        $seoUrl1->setSeoPathInfo('awesome-product');

        $seoUrl2 = new SeoUrlEntity();
        $seoUrl2->setId(Uuid::randomHex());
        $seoUrl2->setForeignKey($productId);
        $seoUrl2->setSeoPathInfo('amazing-product');

        $salesChannelApiContext = $this->getSaleChannelApiContextMock($salesChannelId);
        $seoUrlRepo = $this->getSeoUrlRepoMock([$seoUrl1, $seoUrl2], $salesChannelApiContext);

        $request = Request::create('https://shop.test/foo/bar');
        $request->attributes->set(RequestTransformer::SALES_CHANNEL_ABSOLUTE_BASE_URL, 'https://shop.test');
        $requestStack = $this->getRequestStackMock($request);
        $subscriber = new SeoUrlLoaderSubscriber($seoUrlRepo, $registry, $requestStack);

        $entities = [$seoUrl1, $seoUrl2];
        $event = new EntityLoadedEvent($seoUrlDefinition, $entities, $salesChannelApiContext);
        $subscriber->addUrls($event);

        static::assertSame('https://shop.test/awesome-product', $seoUrl1->getUrl());
        static::assertSame('https://shop.test/amazing-product', $seoUrl2->getUrl());
    }

    private function getSeoUrlRepoMock(array $seoUrls, Context $context): EntityRepositoryInterface
    {
        $seoUrlRepo = $this->createMock(EntityRepositoryInterface::class);
        $seoUrlRepo
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    1,
                    new CanonicalUrlCollection($seoUrls),
                    null,
                    new Criteria(),
                    $context
                )
            );

        return $seoUrlRepo;
    }

    private function getSaleChannelApiContextMock(string $salesChannelId): Context
    {
        /** @var Context|MockObject $salesChannelApiContext */
        $salesChannelApiContext = $this->createMock(Context::class);
        $salesChannelApiContext
            ->method('getSource')
            ->willReturn(new SalesChannelApiSource($salesChannelId));

        return $salesChannelApiContext;
    }

    private function getRequestStackMock(Request $masterRequest): RequestStack
    {
        $request = $this->createMock(RequestStack::class);
        $request->method('getMasterRequest')->willReturn($masterRequest);

        return $request;
    }
}

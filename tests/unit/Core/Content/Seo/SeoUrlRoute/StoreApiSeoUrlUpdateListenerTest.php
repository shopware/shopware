<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Event\CategoryIndexerEvent;
use Shopware\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Content\Seo\SeoUrlRoute\CategoryStoreApiUrlRoute;
use Shopware\Core\Content\Seo\SeoUrlRoute\LandingPageStoreApiUrlRoute;
use Shopware\Core\Content\Seo\SeoUrlRoute\ProductStoreApiUrlRoute;
use Shopware\Core\Content\Seo\SeoUrlRoute\StoreApiSeoUrlUpdateListener;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(StoreApiSeoUrlUpdateListener::class)]
class StoreApiSeoUrlUpdateListenerTest extends TestCase
{
    private SeoUrlUpdater&MockObject $seoUrlUpdater;

    private StoreApiSeoUrlUpdateListener $listener;

    protected function setUp(): void
    {
        $this->seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $this->listener = new StoreApiSeoUrlUpdateListener($this->seoUrlUpdater);
    }

    public function testGetSubscribedEvents(): void
    {
        $this->seoUrlUpdater->expects($this->never())->method('update');

        static::assertSame(
            [
                ProductIndexerEvent::class => 'updateProductUrls',
                CategoryIndexerEvent::class => 'updateCategoryUrls',
                LandingPageIndexerEvent::class => 'updateLandingPageUrls',
            ],
            StoreApiSeoUrlUpdateListener::getSubscribedEvents()
        );
    }

    public function testUpdateProductUrls(): void
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex()];
        $event = new ProductIndexerEvent($ids, Context::createDefaultContext());

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with(ProductStoreApiUrlRoute::ROUTE_NAME, $ids);

        $this->listener->updateProductUrls($event);
    }

    public function testUpdateProductUrlsSkipped(): void
    {
        $event = new ProductIndexerEvent([Uuid::randomHex()], Context::createDefaultContext(), ['product.seo-url']);

        $this->seoUrlUpdater->expects($this->never())->method('update');

        $this->listener->updateProductUrls($event);
    }

    public function testUpdateCategoryUrls(): void
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex()];
        $event = new CategoryIndexerEvent($ids, Context::createDefaultContext());

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with(CategoryStoreApiUrlRoute::ROUTE_NAME, $ids);

        $this->listener->updateCategoryUrls($event);
    }

    public function testUpdateCategoryUrlsSkipped(): void
    {
        $event = new CategoryIndexerEvent([Uuid::randomHex()], Context::createDefaultContext(), ['category.seo-url']);

        $this->seoUrlUpdater->expects($this->never())->method('update');

        $this->listener->updateCategoryUrls($event);
    }

    public function testUpdateLandingPageUrls(): void
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex()];
        $event = new LandingPageIndexerEvent($ids, Context::createDefaultContext());

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with(LandingPageStoreApiUrlRoute::ROUTE_NAME, $ids);

        $this->listener->updateLandingPageUrls($event);
    }

    public function testUpdateLandingPageUrlsSkipped(): void
    {
        $event = new LandingPageIndexerEvent([Uuid::randomHex()], Context::createDefaultContext(), ['landing_page.seo-url']);

        $this->seoUrlUpdater->expects($this->never())->method('update');

        $this->listener->updateLandingPageUrls($event);
    }
}

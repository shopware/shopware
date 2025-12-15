<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Event\CategoryIndexerEvent;
use Shopware\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlUpdateListener;

/**
 * @internal
 */
#[CoversClass(SeoUrlUpdateListener::class)]
class SeoUrlUpdateListenerTest extends TestCase
{
    private SeoUrlUpdater&MockObject $seoUrlUpdater;

    private SeoUrlUpdateListener $listener;

    protected function setUp(): void
    {
        $this->seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $this->listener = new SeoUrlUpdateListener($this->seoUrlUpdater);
    }

    public function testUpdateCategoryUrls(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $event = new CategoryIndexerEvent([$parentUuid, $childUuid], Context::createDefaultContext());

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with(
                NavigationPageSeoUrlRoute::ROUTE_NAME,
                [$parentUuid, $childUuid]
            );

        $this->listener->updateCategoryUrls($event);
    }

    public function testUpdateCategoryUrlsSkipped(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $event = new CategoryIndexerEvent([$parentUuid, $childUuid], Context::createDefaultContext(), [SeoUrlUpdateListener::CATEGORY_SEO_URL_UPDATER]);

        $this->seoUrlUpdater->expects($this->never())
            ->method('update');

        $this->listener->updateCategoryUrls($event);
    }

    public function testUpdateProductUrls(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $event = new ProductIndexerEvent([$parentUuid, $childUuid], Context::createDefaultContext());

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with(
                ProductPageSeoUrlRoute::ROUTE_NAME,
                [$parentUuid, $childUuid]
            );

        $this->listener->updateProductUrls($event);
    }

    public function testUpdateProductUrlsSkips(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $event = new ProductIndexerEvent([$parentUuid, $childUuid], Context::createDefaultContext(), [SeoUrlUpdateListener::PRODUCT_SEO_URL_UPDATER]);

        $this->seoUrlUpdater->expects($this->never())
            ->method('update');

        $this->listener->updateProductUrls($event);
    }

    public function testUpdateLandingPageUrls(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $event = new LandingPageIndexerEvent([$parentUuid, $childUuid], Context::createDefaultContext());

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with(
                LandingPageSeoUrlRoute::ROUTE_NAME,
                [$parentUuid, $childUuid]
            );

        $this->listener->updateLandingPageUrls($event);
    }

    public function testUpdateLandingPageUrlsSkips(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $event = new LandingPageIndexerEvent([$parentUuid, $childUuid], Context::createDefaultContext(), [SeoUrlUpdateListener::LANDING_PAGE_SEO_URL_UPDATER]);

        $this->seoUrlUpdater->expects($this->never())
            ->method('update');

        $this->listener->updateLandingPageUrls($event);
    }
}

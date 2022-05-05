<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Product\QuickView;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPage;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageCriteriaEvent;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoadedEvent;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Shopware\Storefront\Test\Page\StorefrontPageTestConstants;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class MinimalQuickViewPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItRequiresProductParam(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContext();

        $this->expectParamMissingException('productId');
        $this->getPageLoader()->load($request, $context);
    }

    public function testItRequiresAValidProductParam(): void
    {
        $request = new Request([], [], ['productId' => '99999911ffff4fffafffffff19830531']);
        $context = $this->createSalesChannelContext();

        $this->expectException(ProductNotFoundException::class);
        $this->getPageLoader()->load($request, $context);
    }

    public function testItFailsWithANonExistingProduct(): void
    {
        $context = $this->createSalesChannelContext();
        $request = new Request([], [], ['productId' => Uuid::randomHex()]);

        $event = null;
        $this->catchEvent(MinimalQuickViewPageLoadedEvent::class, $event);

        $this->expectException(ProductNotFoundException::class);
        $this->getPageLoader()->load($request, $context);
    }

    public function testItDoesLoadATestProduct(): void
    {
        $context = $this->createSalesChannelContext();
        $product = $this->getRandomProduct($context);

        $request = new Request([], [], ['productId' => $product->getId()]);

        $event = null;
        $this->catchEvent(MinimalQuickViewPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(MinimalQuickViewPage::class, $page);
        static::assertSame(StorefrontPageTestConstants::PRODUCT_NAME, $page->getProduct()->getName());
        self::assertPageEvent(MinimalQuickViewPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItDispatchPageCriteriaEvent(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();
        $product = $this->getRandomProduct($context);

        $request = new Request([], [], ['productId' => $product->getId()]);

        /** @var ProductPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(MinimalQuickViewPageCriteriaEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(MinimalQuickViewPage::class, $page);
    }

    protected function getPageLoader(): MinimalQuickViewPageLoader
    {
        return $this->getContainer()->get(MinimalQuickViewPageLoader::class);
    }
}

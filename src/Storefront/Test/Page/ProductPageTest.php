<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Page\Product\ProductPage;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Shopware\Storefront\Page\Product\ProductPageLoader;
use Symfony\Component\HttpFoundation\Request;

class ProductPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItRequiresProductParam(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();

        $this->expectParamMissingException('productId');
        $this->getPageLoader()->load($request, $context);
    }

    public function testItRequiresAValidProductParam(): void
    {
        $request = new Request([], [], ['productId' => '99999911ffff4fffafffffff19830531']);
        $context = $this->createSalesChannelContextWithNavigation();

        $this->expectException(ProductNotFoundException::class);
        $this->getPageLoader()->load($request, $context);
    }

    public function testItFailsWithANonExistingProduct(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();
        $request = new Request([], [], ['productId' => Uuid::randomHex()]);

        /** @var ProductPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(ProductPageLoadedEvent::class, $event);

        $this->expectException(ProductNotFoundException::class);
        $this->getPageLoader()->load($request, $context);
    }

    public function testItDoesLoadATestProduct(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();
        $product = $this->getRandomProduct($context);

        $request = new Request([], [], ['productId' => $product->getId()]);

        /** @var ProductPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(ProductPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(ProductPage::class, $page);
        static::assertSame(StorefrontPageTestConstants::PRODUCT_NAME, $page->getProduct()->getName());
        self::assertPageEvent(ProductPageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return ProductPageLoader
     */
    protected function getPageLoader()
    {
        return $this->getContainer()->get(ProductPageLoader::class);
    }
}

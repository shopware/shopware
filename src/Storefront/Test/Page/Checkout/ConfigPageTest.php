<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Checkout;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Checkout\Config\CheckoutConfigPage;
use Shopware\Storefront\Page\Checkout\Config\CheckoutConfigPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Config\CheckoutConfigPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Shopware\Storefront\Test\Page\StorefrontPageTestConstants;

class ConfigPageTest extends TestCase
{
    use IntegrationTestBehaviour,
        StorefrontPageTestBehaviour;

    public function testItLoadsTheConfigPage(): void
    {
        $request = new InternalRequest();
        $context = $this->createCheckoutContextWithNavigation();

        /** @var CheckoutConfigPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(CheckoutConfigPageLoadedEvent::NAME, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(CheckoutConfigPage::class, $page);
        static::assertSame(StorefrontPageTestConstants::PAYMENT_METHOD_COUNT, $page->getPaymentMethods()->count());
        static::assertSame(StorefrontPageTestConstants::SHIPPING_METHOD_COUNT, $page->getShippingMethods()->count());
        self::assertPageEvent(CheckoutConfigPageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return CheckoutConfigPageLoader
     */
    protected function getPageLoader(): PageLoaderInterface
    {
        return $this->getContainer()->get(CheckoutConfigPageLoader::class);
    }
}

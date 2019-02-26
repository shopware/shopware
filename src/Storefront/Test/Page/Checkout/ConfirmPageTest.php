<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Checkout;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;

class ConfirmPageTest extends TestCase
{
    use IntegrationTestBehaviour,
        StorefrontPageTestBehaviour;

    public function testItThrowsWithoutNavigation(): void
    {
        $this->assertFailsWithoutNavigation();
    }

    public function testItLoadsTheConfirmPage(): void
    {
        $request = new InternalRequest();
        $context = $this->createCheckoutContextWithNavigation();

        /** @var CheckoutConfirmPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(CheckoutConfirmPageLoadedEvent::NAME, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(CheckoutConfirmPage::class, $page);
        static::assertSame(0.0, $page->getCart()->getPrice()->getNetPrice());
        static::assertSame($context->getToken(), $page->getCart()->getToken());
        self::assertPageEvent(CheckoutConfirmPageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return CheckoutConfirmPageLoader
     */
    protected function getPageLoader(): PageLoaderInterface
    {
        return $this->getContainer()->get(CheckoutConfirmPageLoader::class);
    }
}

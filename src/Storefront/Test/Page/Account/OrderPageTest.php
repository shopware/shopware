<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Account\Order\AccountOrderPage;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class OrderPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItLoadsOrders(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        /** @var AccountOrderPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(AccountOrderPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(AccountOrderPage::class, $page);
        static::assertSame(0, $page->getOrders()->count());
        self::assertPageEvent(AccountOrderPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testSalesChannelRestriction(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $testContext = $this->createSalesChannelContext();

        $this->placeRandomOrder($context);
        $order = $this->placeRandomOrder($context);
        $this->getContainer()->get('order.repository')->update([
            [
                'id' => $order,
                'salesChannelId' => $testContext->getSalesChannel()->getId(),
            ],
        ], $context->getContext());

        /** @var AccountOrderPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(AccountOrderPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(AccountOrderPage::class, $page);
        static::assertSame(1, $page->getOrders()->count());
        self::assertPageEvent(AccountOrderPageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return AccountOrderPageLoader
     */
    protected function getPageLoader()
    {
        return $this->getContainer()->get(AccountOrderPageLoader::class);
    }
}

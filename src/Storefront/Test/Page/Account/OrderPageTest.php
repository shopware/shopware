<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Account\Order\AccountOrderPage;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class OrderPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testLoginRequirement(): void
    {
        $this->assertLoginRequirement();
    }

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

    /**
     * @return AccountOrderPageLoader
     */
    protected function getPageLoader()
    {
        return $this->getContainer()->get(AccountOrderPageLoader::class);
    }
}

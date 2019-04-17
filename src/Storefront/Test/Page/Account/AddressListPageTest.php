<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Account\AddressList\AccountAddressListPage;
use Shopware\Storefront\Page\Account\AddressList\AccountAddressListPageLoadedEvent;
use Shopware\Storefront\Page\Account\AddressList\AccountAddressListPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class AddressListPageTest extends TestCase
{
    use IntegrationTestBehaviour,
        StorefrontPageTestBehaviour;

    public function testItThrowsWithoutNavigation(): void
    {
        $this->assertFailsWithoutNavigation();
    }

    public function testLoginRequirement(): void
    {
        $this->assertLoginRequirement();
    }

    public function testItLoadsAnAddresseslist(): void
    {
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $request = new Request(['addressId' => $context->getCustomer()->getActiveBillingAddress()->getId()]);

        /** @var AccountAddressListPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(AccountAddressListPageLoadedEvent::NAME, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(AccountAddressListPage::class, $page);
        static::assertSame(1, $page->getAddresses()->count());
        static::assertPageEvent(AccountAddressListPageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return AccountAddressListPageLoader
     */
    protected function getPageLoader(): PageLoaderInterface
    {
        return $this->getContainer()->get(AccountAddressListPageLoader::class);
    }
}

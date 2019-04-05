<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Account\Address\AccountAddressPage;
use Shopware\Storefront\Page\Account\Address\AccountAddressPageLoadedEvent;
use Shopware\Storefront\Page\Account\Address\AccountAddressPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Shopware\Storefront\Test\Page\StorefrontPageTestConstants;

class AddressPageTest extends TestCase
{
    use IntegrationTestBehaviour,
        StorefrontPageTestBehaviour;

    public function testItThrowsWithoutNavigation(): void
    {
        $this->assertFailsWithoutNavigation();
    }

    public function testItLoadsAddressesAndCountriesForACustomer(): void
    {
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $request = new InternalRequest();

        /** @var AccountAddressPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(AccountAddressPageLoadedEvent::NAME, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(AccountAddressPage::class, $page);
        static::assertSame(StorefrontPageTestConstants::COUNTRY_COUNT, $page->getCountries()->count());
        static::assertNull($page->getAddress());
        self::assertPageEvent(AccountAddressPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItFiltersByAnAddressId(): void
    {
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $request = new InternalRequest(['addressId' => $context->getCustomer()->getActiveBillingAddress()->getId()]);

        /** @var AccountAddressPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(AccountAddressPageLoadedEvent::NAME, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(AccountAddressPage::class, $page);
        static::assertSame(StorefrontPageTestConstants::COUNTRY_COUNT, $page->getCountries()->count());
        static::assertNotNull($page->getAddress());
        self::assertPageEvent(AccountAddressPageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return AccountAddressPageLoader
     */
    protected function getPageLoader(): PageLoaderInterface
    {
        return $this->getContainer()->get(AccountAddressPageLoader::class);
    }
}

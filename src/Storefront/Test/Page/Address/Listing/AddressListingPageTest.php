<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Address\Listing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Test\Country\Helpers\Traits\CountryAddressFormattingTestBehaviour;
use Shopware\Storefront\Page\Address\Listing\AddressListingPage;
use Shopware\Storefront\Page\Address\Listing\AddressListingPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class AddressListingPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CountryAddressFormattingTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItDoesLoadATestAddress(): void
    {
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $request = new Request();

        /** @var CustomerEntity */
        $customer = $context->getCustomer();
        $page = $this->getPageLoader()->load($request, $context, $customer);

        static::assertInstanceOf(AddressListingPage::class, $page);
    }

    public function testItDoesSetFormattingAddress(): void
    {
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $request = new Request();

        /** @var CustomerEntity */
        $customer = $context->getCustomer();
        $page = $this->getPageLoader()->load($request, $context, $customer);

        static::assertIsArray($page->getFormattingCustomerAddresses());
        static::assertCount(0, $page->getFormattingCustomerAddresses());
    }

    public function testItDoesRenderFormattingAddress(): void
    {
        /** @var Connection $connection */
        $connection = $this->getContainer()->get(Connection::class);
        $this->setUseAdvancedFormatForCountry($connection);
        $this->setAdvancedAddressFormatPlainForCountry($connection, "{{firstName}}\n{{lastName}}");
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $request = new Request();

        /** @var CustomerEntity */
        $customer = $context->getCustomer();
        $page = $this->getPageLoader()->load($request, $context, $customer);

        static::assertIsArray($page->getFormattingCustomerAddresses());
        static::assertCount(1, $page->getFormattingCustomerAddresses());
    }

    /**
     * @return AddressListingPageLoader
     */
    protected function getPageLoader()
    {
        return $this->getContainer()->get(AddressListingPageLoader::class);
    }
}

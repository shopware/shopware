<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Page\Account;

use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoadedEvent;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Shopware\Tests\Integration\Storefront\Page\StorefrontPageTestConstants;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class LoginPageTest extends \Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestCase
{
    use StorefrontPageTestBehaviour;

    public function testItLoadsWithACustomer(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $event = null;
        $this->catchEvent(AccountLoginPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertCount(StorefrontPageTestConstants::COUNTRY_COUNT, $page->getCountries());
        static::assertInstanceOf(CountryStateCollection::class, $page->getCountries()->first()?->getStates());
        self::assertPageEvent(AccountLoginPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItLoadsWithoutACustomer(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();
        $page = $this->getPageLoader()->load($request, $context);

        static::assertCount(StorefrontPageTestConstants::COUNTRY_COUNT, $page->getCountries());
        static::assertInstanceOf(CountryStateCollection::class, $page->getCountries()->first()?->getStates());
    }

    protected function getPageLoader(): AccountLoginPageLoader
    {
        return static::getContainer()->get(AccountLoginPageLoader::class);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Page\Account;

use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoadedEvent;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ProfilePageTest extends \Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestCase
{
    use StorefrontPageTestBehaviour;

    public function testItLoadsTheProfilePage(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $event = null;
        $this->catchEvent(AccountProfilePageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        self::assertPageEvent(AccountProfilePageLoadedEvent::class, $event, $context, $request, $page);
    }

    protected function getPageLoader(): AccountProfilePageLoader
    {
        return static::getContainer()->get(AccountProfilePageLoader::class);
    }
}

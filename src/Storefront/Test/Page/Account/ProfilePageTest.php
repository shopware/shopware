<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePage;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoadedEvent;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class ProfilePageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItLoadsTheProfilePage(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        /** @var AccountProfilePageLoadedEvent $event */
        $event = null;
        $this->catchEvent(AccountProfilePageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(AccountProfilePage::class, $page);
        self::assertPageEvent(AccountProfilePageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return AccountProfilePageLoader
     */
    protected function getPageLoader()
    {
        return $this->getContainer()->get(AccountProfilePageLoader::class);
    }
}

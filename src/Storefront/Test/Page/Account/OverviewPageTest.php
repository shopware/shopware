<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPage;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;

class OverviewPageTest extends TestCase
{
    use IntegrationTestBehaviour,
        StorefrontPageTestBehaviour;

    public function testItThrowsWithoutNavigation(): void
    {
        $this->assertFailsWithoutNavigation();
    }

    public function testLoginRequirement(): void
    {
        static::markTestSkipped('Not working as expected');
        $this->assertLoginRequirement();
    }

    public function testItloadsTheRequestedACustomer(): void
    {
        $request = new InternalRequest(['search' => 'foo']);
        $context = $this->createCheckoutContextWithLoggedInCustomerAndWithNavigation();

        /** @var AccountOverviewPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(AccountOverviewPageLoadedEvent::NAME, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(AccountOverviewPage::class, $page);
        static::assertEquals('Max', $page->getCustomer()->getFirstName());
        self::assertPageEvent(AccountOverviewPageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return AccountOverviewPageLoader
     */
    protected function getPageLoader(): PageLoaderInterface
    {
        return $this->getContainer()->get(AccountOverviewPageLoader::class);
    }
}

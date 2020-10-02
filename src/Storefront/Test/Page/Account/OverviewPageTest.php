<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPage;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class OverviewPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testLoginRequirement(): void
    {
        $this->assertLoginRequirement();
    }

    public function testItLoadsTheOverview(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        /** @var AccountOverviewPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(AccountOverviewPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(AccountOverviewPage::class, $page);
        self::assertPageEvent(AccountOverviewPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testSalesChannelRestriction(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $testContext = $this->createSalesChannelContext();

        $order = $this->placeRandomOrder($context);
        $this->getContainer()->get('order.repository')->update([
            [
                'id' => $order,
                'salesChannelId' => $testContext->getSalesChannel()->getId(),
            ],
        ], $context->getContext());

        /** @var AccountOverviewPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(AccountOverviewPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(AccountOverviewPage::class, $page);
        static::assertNull($page->getNewestOrder());
        self::assertPageEvent(AccountOverviewPageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return AccountOverviewPageLoader
     */
    protected function getPageLoader()
    {
        return $this->getContainer()->get(AccountOverviewPageLoader::class);
    }
}

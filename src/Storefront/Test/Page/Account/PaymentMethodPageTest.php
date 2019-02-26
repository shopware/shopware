<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPage;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedEvent;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;

class PaymentMethodPageTest extends TestCase
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

    public function testItlLoadsTheRequestedCustomersData(): void
    {
        $request = new InternalRequest();
        $context = $this->createCheckoutContextWithLoggedInCustomerAndWithNavigation();

        /** @var AccountPaymentMethodPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(AccountPaymentMethodPageLoadedEvent::NAME, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(AccountPaymentMethodPage::class, $page);
        static::assertSame('Max', $page->getCustomer()->getFirstName());
        static::assertSame(4, $page->getPaymentMethods()->count());
        self::assertPageEvent(AccountPaymentMethodPageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return AccountPaymentMethodPageLoader
     */
    protected function getPageLoader(): PageLoaderInterface
    {
        return $this->getContainer()->get(AccountPaymentMethodPageLoader::class);
    }
}

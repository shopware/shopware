<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPage;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedEvent;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Shopware\Storefront\Test\Page\StorefrontPageTestConstants;
use Symfony\Component\HttpFoundation\Request;

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
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        /** @var AccountPaymentMethodPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(AccountPaymentMethodPageLoadedEvent::NAME, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(AccountPaymentMethodPage::class, $page);
        static::assertSame(StorefrontPageTestConstants::CUSTOMER_FIRSTNAME, $page->getCustomer()->getFirstName());
        static::assertSame(StorefrontPageTestConstants::PAYMENT_METHOD_COUNT, $page->getPaymentMethods()->count());
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

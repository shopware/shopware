<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPage;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedEvent;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Shopware\Storefront\Test\Page\StorefrontPageTestConstants;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class PaymentMethodPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItlLoadsTheRequestedCustomersData(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        /** @var AccountPaymentMethodPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(AccountPaymentMethodPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(AccountPaymentMethodPage::class, $page);
        static::assertSame(StorefrontPageTestConstants::PAYMENT_METHOD_COUNT, $page->getPaymentMethods()->count());
        self::assertPageEvent(AccountPaymentMethodPageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return AccountPaymentMethodPageLoader
     */
    protected function getPageLoader()
    {
        return $this->getContainer()->get(AccountPaymentMethodPageLoader::class);
    }
}

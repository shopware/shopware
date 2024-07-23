<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedEvent;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoader;
use Shopware\Tests\Integration\Storefront\Page\StorefrontPageTestBehaviour;
use Shopware\Tests\Integration\Storefront\Page\StorefrontPageTestConstants;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.7.0 - will be removed
 *
 * @internal
 */
class PaymentMethodPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);
    }

    public function testItlLoadsTheRequestedCustomersData(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $event = null;
        $this->catchEvent(AccountPaymentMethodPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertCount(StorefrontPageTestConstants::PAYMENT_METHOD_COUNT, $page->getPaymentMethods());
        self::assertPageEvent(AccountPaymentMethodPageLoadedEvent::class, $event, $context, $request, $page);
    }

    protected function getPageLoader(): AccountPaymentMethodPageLoader
    {
        return $this->getContainer()->get(AccountPaymentMethodPageLoader::class);
    }
}

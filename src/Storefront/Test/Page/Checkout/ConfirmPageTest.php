<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Checkout;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Shopware\Storefront\Test\Page\StorefrontPageTestConstants;

class ConfirmPageTest extends TestCase
{
    use IntegrationTestBehaviour,
        StorefrontPageTestBehaviour;

    public function testItThrowsWithoutNavigation(): void
    {
        $this->assertFailsWithoutNavigation();
    }

    public function testItLoadsTheConfirmPage(): void
    {
        $request = new InternalRequest();
        $context = $this->createCheckoutContextWithNavigation();

        /** @var CheckoutConfirmPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(CheckoutConfirmPageLoadedEvent::NAME, $event);

        /** @var CheckoutConfirmPage $page */
        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(CheckoutConfirmPage::class, $page);
        static::assertSame(0.0, $page->getCart()->getPrice()->getNetPrice());
        static::assertSame($context->getToken(), $page->getCart()->getToken());
        static::assertSame(StorefrontPageTestConstants::AVAILABLE_SHIPPING_METHOD_COUNT, $page->getShippingMethods()->count());
        static::assertSame(StorefrontPageTestConstants::AVAILABLE_PAYMENT_METHOD_COUNT, $page->getPaymentMethods()->count());
        static::assertNotEmpty($page->getPaymentMethods());
        self::assertPageEvent(CheckoutConfirmPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItIgnoresUnavailableShippingMethods(): void
    {
        $request = new InternalRequest();
        $context = $this->createCheckoutContextWithNavigation();

        /** @var EntityRepositoryInterface $shippingMethodRepository */
        $shippingMethodRepository = $this->getContainer()->get('shipping_method.repository');
        $shippingMethodRuleRepository = $this->getContainer()->get('shipping_method_rule.repository');
        /** @var ShippingMethodEntity $shippingMethod */
        $shippingMethod = $shippingMethodRepository->search(new Criteria([Defaults::SHIPPING_METHOD]), $context->getContext())->get(Defaults::SHIPPING_METHOD);

        $ruleToDelete = [];

        foreach ($shippingMethod->getAvailabilityRuleIds() as $availabilityRuleId) {
            $ruleToDelete[] = [
                'shippingMethodId' => Defaults::SHIPPING_METHOD,
                'ruleId' => $availabilityRuleId,
            ];
        }

        $shippingMethodRuleRepository->delete($ruleToDelete, $context->getContext());

        /** @var CheckoutConfirmPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(CheckoutConfirmPageLoadedEvent::NAME, $event);

        $context = $this->createCheckoutContextWithNavigation();
        /** @var CheckoutConfirmPage $page */
        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(CheckoutConfirmPage::class, $page);
        static::assertSame(0, $page->getShippingMethods()->count());
        self::assertPageEvent(CheckoutConfirmPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItIgnoresUnavailablePaymentMethods(): void
    {
        $request = new InternalRequest();
        $context = $this->createCheckoutContextWithNavigation();

        $paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $paymentMethodRuleRepository = $this->getContainer()->get('payment_method_rule.repository');
        /** @var PaymentMethodEntity $paymentMethod */
        $paymentMethod = $paymentMethodRepository->search(new Criteria([Defaults::PAYMENT_METHOD_DEBIT]), $context->getContext())->get(Defaults::PAYMENT_METHOD_DEBIT);

        $ruleToDelete = [];

        foreach ($paymentMethod->getAvailabilityRuleIds() as $availabilityRuleId) {
            $ruleToDelete[] = [
                'paymentMethodId' => Defaults::PAYMENT_METHOD_DEBIT,
                'ruleId' => $availabilityRuleId,
            ];
        }

        $paymentMethodRuleRepository->delete($ruleToDelete, $context->getContext());

        /** @var CheckoutConfirmPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(CheckoutConfirmPageLoadedEvent::NAME, $event);

        $context = $this->createCheckoutContextWithNavigation();

        /** @var CheckoutConfirmPage $page */
        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(CheckoutConfirmPage::class, $page);
        static::assertSame(StorefrontPageTestConstants::PAYMENT_METHOD_COUNT - 1, $page->getPaymentMethods()->count());
        self::assertPageEvent(CheckoutConfirmPageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return CheckoutConfirmPageLoader
     */
    protected function getPageLoader(): PageLoaderInterface
    {
        return $this->getContainer()->get(CheckoutConfirmPageLoader::class);
    }
}

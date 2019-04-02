<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Checkout;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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
        $shippingMethods = $shippingMethodRepository->search(new Criteria(), $context->getContext())->getEntities();

        $ruleToDelete = [];

        /** @var ShippingMethodEntity $shippingMethod */
        foreach ($shippingMethods as $shippingMethod) {
            foreach ($shippingMethod->getAvailabilityRuleIds() as $availabilityRuleId) {
                $ruleToDelete[] = [
                    'shippingMethodId' => $shippingMethod->getId(),
                    'ruleId' => $availabilityRuleId,
                ];
            }
        }

        if (\count($ruleToDelete) > 0) {
            $shippingMethodRuleRepository->delete($ruleToDelete, $context->getContext());
        }

        /** @var CheckoutConfirmPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(CheckoutConfirmPageLoadedEvent::NAME, $event);

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

        /** @var EntityRepositoryInterface $paymentMethodRepository */
        $paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $paymentMethodRuleRepository = $this->getContainer()->get('payment_method_rule.repository');
        $criteria = (new Criteria())->addFilter(new EqualsFilter('active', true));
        /** @var PaymentMethodEntity $paymentMethod */
        $paymentMethod = $paymentMethodRepository->search($criteria, $context->getContext())->first();

        $ruleToDelete = [];

        foreach ($paymentMethod->getAvailabilityRuleIds() as $availabilityRuleId) {
            $ruleToDelete[] = [
                'paymentMethodId' => $paymentMethod->getId(),
                'ruleId' => $availabilityRuleId,
            ];
        }

        $paymentMethodRuleRepository->delete($ruleToDelete, $context->getContext());

        $paymentMethodRepository->update(
            [
                ['id' => $paymentMethod->getId(), 'availabilityRules' => [['name' => 'invalid', 'priority' => 0]]],
            ],
            $context->getContext()
        );

        /** @var CheckoutConfirmPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(CheckoutConfirmPageLoadedEvent::NAME, $event);

        $context = $this->createCheckoutContextWithNavigation();

        /** @var CheckoutConfirmPage $page */
        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(CheckoutConfirmPage::class, $page);
        static::assertSame(StorefrontPageTestConstants::AVAILABLE_PAYMENT_METHOD_COUNT - 1, $page->getPaymentMethods()->count());
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

<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Checkout;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\AddressValidationError;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Shopware\Storefront\Test\Page\StorefrontPageTestConstants;
use Symfony\Component\HttpFoundation\Request;

class ConfirmPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItLoadsTheConfirmPage(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        /** @var CheckoutConfirmPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(CheckoutConfirmPageLoadedEvent::class, $event);

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
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        /** @var EntityRepositoryInterface $shippingMethodRepository */
        $shippingMethodRepository = $this->getContainer()->get('shipping_method.repository');
        $shippingMethods = $shippingMethodRepository->search(new Criteria(), $context->getContext())->getEntities();

        $updates = [];

        /** @var ShippingMethodEntity $shippingMethod */
        foreach ($shippingMethods as $shippingMethod) {
            $updates[] = [
                'id' => $shippingMethod->getId(),
                'availabilityRule' => [
                    'name' => 'test',
                    'priority' => 0,
                ],
            ];
        }

        $shippingMethodRepository->update($updates, $context->getContext());

        /** @var CheckoutConfirmPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(CheckoutConfirmPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(CheckoutConfirmPage::class, $page);
        static::assertSame(1, $page->getShippingMethods()->count());
        self::assertPageEvent(CheckoutConfirmPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItIgnoresUnavailablePaymentMethods(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        /** @var EntityRepositoryInterface $paymentMethodRepository */
        $paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $criteria = (new Criteria())->addFilter(new EqualsFilter('active', true));
        /** @var PaymentMethodEntity $paymentMethod */
        $paymentMethod = $paymentMethodRepository->search($criteria, $context->getContext())->first();

        $paymentMethodRepository->update(
            [
                ['id' => $paymentMethod->getId(), 'availabilityRule' => ['name' => 'invalid', 'priority' => 0]],
            ],
            $context->getContext()
        );

        /** @var CheckoutConfirmPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(CheckoutConfirmPageLoadedEvent::class, $event);

        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(CheckoutConfirmPage::class, $page);
        static::assertSame(StorefrontPageTestConstants::AVAILABLE_PAYMENT_METHOD_COUNT - 1, $page->getPaymentMethods()->count());
        self::assertPageEvent(CheckoutConfirmPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testCartErrorAddedOnInvalidAddress(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $customer = $context->getCustomer();
        static::assertNotNull($customer);
        $activeBillingAddress = $customer->getActiveBillingAddress();
        static::assertNotNull($activeBillingAddress);

        $newShippingAddress = clone $activeBillingAddress;

        $activeBillingAddress->setFirstName('');
        $newShippingAddress->setId(Uuid::randomHex());
        $newShippingAddress->setLastName('');
        $customer->setActiveShippingAddress($newShippingAddress);

        $cartErrors = $this->getPageLoader()->load($request, $context)->getCart()->getErrors();
        static::assertCount(2, $cartErrors);
        $errors = $cartErrors->getElements();
        static::assertArrayHasKey('billing-address-invalid', $errors);
        static::assertArrayHasKey('shipping-address-invalid', $errors);

        /** @var AddressValidationError $billingAddressViolation */
        $billingAddressViolation = $errors['billing-address-invalid'];
        $violation = $billingAddressViolation->getViolations()[0];
        static::assertSame('/firstName', $violation->getPropertyPath());

        /** @var AddressValidationError $shippingAddressViolation */
        $shippingAddressViolation = $errors['shipping-address-invalid'];
        $violation = $shippingAddressViolation->getViolations()[0];
        static::assertSame('/lastName', $violation->getPropertyPath());
    }

    protected function getPageLoader(): CheckoutConfirmPageLoader
    {
        return $this->getContainer()->get(CheckoutConfirmPageLoader::class);
    }
}

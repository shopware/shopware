<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Checkout\Confirm;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\AddressValidationError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\AddressValidationFactory;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(CheckoutConfirmPageLoader::class)]
class CheckoutConfirmPageLoaderTest extends TestCase
{
    public function testRobotsMetaSetIfGiven(): void
    {
        $page = new CheckoutConfirmPage();
        $page->setMetaInformation(new MetaInformation());

        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn($page);

        $checkoutConfirmPageLoader = new CheckoutConfirmPageLoader(
            $this->createMock(EventDispatcher::class),
            $this->createMock(StorefrontCartFacade::class),
            $this->createMock(ShippingMethodRoute::class),
            $this->createMock(PaymentMethodRoute::class),
            $pageLoader,
            $this->createMock(AddressValidationFactory::class),
            $this->createMock(DataValidator::class)
        );

        $page = $checkoutConfirmPageLoader->load(
            new Request(),
            $this->getContextWithDummyCustomer()
        );

        static::assertNotNull($page->getMetaInformation());
        static::assertSame('noindex,follow', $page->getMetaInformation()->getRobots());
    }

    public function testRobotsMetaNotSetIfGiven(): void
    {
        $page = new CheckoutConfirmPage();

        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn($page);

        $checkoutConfirmPageLoader = new CheckoutConfirmPageLoader(
            $this->createMock(EventDispatcher::class),
            $this->createMock(StorefrontCartFacade::class),
            $this->createMock(ShippingMethodRoute::class),
            $this->createMock(PaymentMethodRoute::class),
            $pageLoader,
            $this->createMock(AddressValidationFactory::class),
            $this->createMock(DataValidator::class)
        );

        $page = $checkoutConfirmPageLoader->load(
            new Request(),
            $this->getContextWithDummyCustomer()
        );

        static::assertNull($page->getMetaInformation());
    }

    public function testPaymentAndShippingMethodsAreSetToPage(): void
    {
        $paymentMethods = new PaymentMethodCollection([
            (new PaymentMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
            (new PaymentMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
        ]);

        $shippingMethods = new ShippingMethodCollection([
            (new ShippingMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
            (new ShippingMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
        ]);

        $paymentMethodResponse = new PaymentMethodRouteResponse(
            new EntitySearchResult(
                PaymentMethodDefinition::ENTITY_NAME,
                2,
                $paymentMethods,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $shippingMethodResponse = new ShippingMethodRouteResponse(
            new EntitySearchResult(
                ShippingMethodDefinition::ENTITY_NAME,
                2,
                $shippingMethods,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $paymentMethodRoute = $this->createMock(PaymentMethodRoute::class);
        $paymentMethodRoute
            ->method('load')
            ->withAnyParameters()
            ->willReturn($paymentMethodResponse);

        $shippingMethodRoute = $this->createMock(ShippingMethodRoute::class);
        $shippingMethodRoute
            ->method('load')
            ->withAnyParameters()
            ->willReturn($shippingMethodResponse);

        $checkoutConfirmPageLoader = new CheckoutConfirmPageLoader(
            $this->createMock(EventDispatcher::class),
            $this->createMock(StorefrontCartFacade::class),
            $shippingMethodRoute,
            $paymentMethodRoute,
            $this->createMock(GenericPageLoader::class),
            $this->createMock(AddressValidationFactory::class),
            $this->createMock(DataValidator::class)
        );

        $page = $checkoutConfirmPageLoader->load(
            new Request(),
            $this->getContextWithDummyCustomer()
        );

        static::assertSame($paymentMethods, $page->getPaymentMethods());
        static::assertSame($shippingMethods, $page->getShippingMethods());
    }

    public function testCustomerNotLoggedInException(): void
    {
        $checkoutConfirmPageLoader = new CheckoutConfirmPageLoader(
            $this->createMock(EventDispatcher::class),
            $this->createMock(StorefrontCartFacade::class),
            $this->createMock(ShippingMethodRoute::class),
            $this->createMock(PaymentMethodRoute::class),
            $this->createMock(GenericPageLoader::class),
            $this->createMock(AddressValidationFactory::class),
            $this->createMock(DataValidator::class)
        );

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getCustomer')
            ->willReturn(null);

        $expected = CartException::customerNotLoggedIn()::class;

        static::expectException($expected);
        static::expectExceptionMessage('Customer is not logged in');

        $checkoutConfirmPageLoader->load(new Request(), $context);
    }

    public function testViolationsAreAddedAsCartErrorsWithSameAddress(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                'Test error',
                null,
                [],
                'root',
                null,
                'invalidValue'
            ),
        ]);

        $validator = $this->createMock(DataValidator::class);
        $validator
            ->method('getViolations')
            ->willReturn($violations);

        $cart = new Cart('test');

        $cartService = $this->createMock(StorefrontCartFacade::class);
        $cartService
            ->method('get')
            ->willReturn($cart);

        $checkoutConfirmPageLoader = new CheckoutConfirmPageLoader(
            $this->createMock(EventDispatcher::class),
            $cartService,
            $this->createMock(ShippingMethodRoute::class),
            $this->createMock(PaymentMethodRoute::class),
            $this->createMock(GenericPageLoader::class),
            $this->createMock(AddressValidationFactory::class),
            $validator
        );

        $page = $checkoutConfirmPageLoader->load(new Request(), $this->getContextWithDummyCustomer());

        static::assertCount(1, $page->getCart()->getErrors());
        static::assertArrayHasKey('billing-address-invalid', $page->getCart()->getErrors()->getElements());

        $error = $page->getCart()->getErrors()->first();

        static::assertNotNull($error);
        static::assertInstanceOf(AddressValidationError::class, $error);
        static::assertTrue($error->isBillingAddress());

        static::assertCount(1, $error->getViolations());

        $violation = $error->getViolations()->get(0);

        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame('Test error', $violation->getMessage());
        static::assertSame('root', $violation->getRoot());
        static::assertSame('invalidValue', $violation->getInvalidValue());
    }

    public function testViolationsAreAddedAsCartErrorsWithDifferentAddresses(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                'Test error',
                null,
                [],
                'root',
                null,
                'invalidValue'
            ),
        ]);

        $validator = $this->createMock(DataValidator::class);
        $validator
            ->method('getViolations')
            ->willReturn($violations);

        $cart = new Cart('test');

        $cartService = $this->createMock(StorefrontCartFacade::class);
        $cartService
            ->method('get')
            ->willReturn($cart);

        $checkoutConfirmPageLoader = new CheckoutConfirmPageLoader(
            $this->createMock(EventDispatcher::class),
            $cartService,
            $this->createMock(ShippingMethodRoute::class),
            $this->createMock(PaymentMethodRoute::class),
            $this->createMock(GenericPageLoader::class),
            $this->createMock(AddressValidationFactory::class),
            $validator
        );

        $context = $this->getContextWithDummyCustomer();

        static::assertNotNull($context->getCustomer());

        // different shipping address
        $context->getCustomer()->assign([
            'activeShippingAddress' => (new CustomerAddressEntity())->assign(['id' => Uuid::randomHex(), 'countryId' => Uuid::randomHex()]),
        ]);

        $page = $checkoutConfirmPageLoader->load(new Request(), $context);

        static::assertCount(2, $page->getCart()->getErrors());
        static::assertArrayHasKey('billing-address-invalid', $page->getCart()->getErrors()->getElements());
        static::assertArrayHasKey('shipping-address-invalid', $page->getCart()->getErrors()->getElements());

        $billingAddressError = $page->getCart()->getErrors()->first();

        static::assertNotNull($billingAddressError);
        static::assertInstanceOf(AddressValidationError::class, $billingAddressError);
        static::assertTrue($billingAddressError->isBillingAddress());

        static::assertCount(1, $billingAddressError->getViolations());

        $violation = $billingAddressError->getViolations()->get(0);

        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame('Test error', $violation->getMessage());
        static::assertSame('root', $violation->getRoot());
        static::assertSame('invalidValue', $violation->getInvalidValue());

        $shippingAddressError = $page->getCart()->getErrors()->first();

        static::assertNotNull($shippingAddressError);
        static::assertInstanceOf(AddressValidationError::class, $shippingAddressError);
        static::assertTrue($shippingAddressError->isBillingAddress());

        static::assertCount(1, $shippingAddressError->getViolations());

        $violation = $shippingAddressError->getViolations()->get(0);

        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame('Test error', $violation->getMessage());
        static::assertSame('root', $violation->getRoot());
        static::assertSame('invalidValue', $violation->getInvalidValue());
    }

    public function testValidatorNotCalledIfNoAddressGiven(): void
    {
        $validator = $this->createMock(DataValidator::class);
        $validator
            ->expects(static::never())
            ->method('getViolations');

        $checkoutConfirmPageLoader = new CheckoutConfirmPageLoader(
            $this->createMock(EventDispatcher::class),
            $this->createMock(StorefrontCartFacade::class),
            $this->createMock(ShippingMethodRoute::class),
            $this->createMock(PaymentMethodRoute::class),
            $this->createMock(GenericPageLoader::class),
            $this->createMock(AddressValidationFactory::class),
            $validator
        );

        $context = $this->getContextWithDummyCustomer();

        static::assertNotNull($context->getCustomer());

        $context->getCustomer()->assign([
            'activeBillingAddress' => null,
            'activeShippingAddress' => null,
        ]);

        $checkoutConfirmPageLoader->load(new Request(), $context);
    }

    public function testValidationEventIsDispatched(): void
    {
        $eventDispatcher = new CollectingEventDispatcher();

        $checkoutConfirmPageLoader = new CheckoutConfirmPageLoader(
            $eventDispatcher,
            $this->createMock(StorefrontCartFacade::class),
            $this->createMock(ShippingMethodRoute::class),
            $this->createMock(PaymentMethodRoute::class),
            $this->createMock(GenericPageLoader::class),
            $this->createMock(AddressValidationFactory::class),
            $this->createMock(DataValidator::class)
        );

        $checkoutConfirmPageLoader->load(new Request(), $this->getContextWithDummyCustomer());

        $events = $eventDispatcher->getEvents();
        static::assertCount(3, $events);

        static::assertInstanceOf(BuildValidationEvent::class, $events[0]);
        static::assertInstanceOf(BuildValidationEvent::class, $events[1]);
        static::assertInstanceOf(CheckoutConfirmPageLoadedEvent::class, $events[2]);
    }

    public function testCartServiceIsCalledTaxedAndWithNoCaching(): void
    {
        $cartService = static::createMock(StorefrontCartFacade::class);
        $cartService
            ->expects(static::once())
            ->method('get')
            ->with(null, static::isInstanceOf(SalesChannelContext::class), false, true);

        $checkoutConfirmPageLoader = new CheckoutConfirmPageLoader(
            $this->createMock(EventDispatcher::class),
            $cartService,
            $this->createMock(ShippingMethodRoute::class),
            $this->createMock(PaymentMethodRoute::class),
            $this->createMock(GenericPageLoader::class),
            $this->createMock(AddressValidationFactory::class),
            $this->createMock(DataValidator::class)
        );

        $checkoutConfirmPageLoader->load(new Request(), $this->getContextWithDummyCustomer());
    }

    public function testValidationEventIsDispatchedWithZipcodeDefinition(): void
    {
        $countryId = Uuid::randomHex();

        $cart = new Cart('test');

        $cartService = $this->createMock(StorefrontCartFacade::class);
        $cartService
            ->method('get')
            ->willReturn($cart);

        $addressValidation = $this->createMock(DataValidationFactoryInterface::class);
        $addressValidation->method('create')->willReturn(new DataValidationDefinition('address.create'));

        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->method('dispatch')->willReturnCallback(function ($validationEvent) use ($countryId) {
            if (!$validationEvent instanceof BuildValidationEvent) {
                return $validationEvent;
            }

            $definition = $validationEvent->getDefinition();

            static::assertArrayHasKey('zipcode', $definition->getProperties());
            static::assertNotNull($definition->getProperties()['zipcode'][0]);
            static::assertInstanceOf(CustomerZipCode::class, $definition->getProperties()['zipcode'][0]);

            $message = $definition->getProperties()['zipcode'][0]->getMessage();

            static::assertSame($message, (new CustomerZipCode(['countryId' => $countryId]))->getMessage());

            return $validationEvent;
        });

        $checkoutConfirmPageLoader = new CheckoutConfirmPageLoader(
            $dispatcher,
            $cartService,
            $this->createMock(ShippingMethodRoute::class),
            $this->createMock(PaymentMethodRoute::class),
            $this->createMock(GenericPageLoader::class),
            $addressValidation,
            $this->createMock(DataValidator::class),
        );

        $context = $this->getContextWithDummyCustomer();

        $checkoutConfirmPageLoader->load(new Request(), $context);
    }

    private function getContextWithDummyCustomer(?string $countryId = null): SalesChannelContext
    {
        $address = (new CustomerAddressEntity())->assign(['id' => Uuid::randomHex(), 'countryId' => $countryId ?? Uuid::randomHex()]);

        $customer = new CustomerEntity();
        $customer->assign([
            'activeBillingAddress' => $address,
            'activeShippingAddress' => $address,
        ]);

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getCustomer')
            ->willReturn($customer);

        return $context;
    }
}

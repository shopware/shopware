<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Checkout\Confirm;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\AddressValidationError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\AddressValidationFactory;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartGatewayResult;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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

        $page = $this->createLoader(pageLoader: $pageLoader)->load(
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

        $page = $this->createLoader(pageLoader: $pageLoader)->load(
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

        $response = new CheckoutGatewayRouteResponse(
            $paymentMethods,
            $shippingMethods,
            new ErrorCollection()
        );

        $cartService = $this->createMock(StorefrontCartFacade::class);
        $cartService
            ->method('getWithCheckoutGateway')
            ->withAnyParameters()
            ->willReturn(new StorefrontCartGatewayResult(new Cart('test'), $response));

        $page = $this->createLoader(cartService: $cartService)->load(
            new Request(),
            $this->getContextWithDummyCustomer()
        );

        static::assertSame($paymentMethods, $page->getPaymentMethods());
        static::assertSame($shippingMethods, $page->getShippingMethods());
    }

    public function testCustomerNotLoggedInException(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getCustomer')
            ->willReturn(null);
        $context
            ->method('getToken')
            ->willReturn('token');

        $expected = CartException::customerNotLoggedIn()::class;

        static::expectException($expected);
        static::expectExceptionMessage('Customer is not logged in');

        $this->createLoader()->load(new Request(), $context);
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
            ->method('getWithCheckoutGateway')
            ->willReturn($this->createCartGatewayResult($cart));

        $page = $this->createLoader(cartService: $cartService, validator: $validator)->load(new Request(), $this->getContextWithDummyCustomer());

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
            ->method('getWithCheckoutGateway')
            ->willReturn($this->createCartGatewayResult($cart));

        $context = $this->getContextWithDummyCustomer();

        static::assertNotNull($context->getCustomer());

        $context->getCustomer()->assign([
            'activeShippingAddress' => (new CustomerAddressEntity())->assign(['id' => Uuid::randomHex(), 'countryId' => Uuid::randomHex()]),
        ]);

        $page = $this->createLoader(cartService: $cartService, validator: $validator)->load(new Request(), $context);

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
            ->expects($this->never())
            ->method('getViolations');

        $context = $this->getContextWithDummyCustomer();

        static::assertNotNull($context->getCustomer());

        $context->getCustomer()->assign([
            'activeBillingAddress' => null,
            'activeShippingAddress' => null,
        ]);

        $this->createLoader(validator: $validator)->load(new Request(), $context);
    }

    public function testValidationEventIsDispatched(): void
    {
        $eventDispatcher = new CollectingEventDispatcher();

        $addressValidationMock = $this->createMock(AddressValidationFactory::class);
        $addressValidationMock->expects($this->exactly(2))->method('create')->willReturnOnConsecutiveCalls(
            new DataValidationDefinition('address.create'),
            new DataValidationDefinition('address.update'),
        );

        $this->createLoader(
            eventDispatcher: $eventDispatcher,
            addressValidationFactory: $addressValidationMock
        )->load(new Request(), $this->getContextWithDummyCustomer());

        $events = $eventDispatcher->getEvents();
        static::assertCount(3, $events);

        static::assertInstanceOf(BuildValidationEvent::class, $events['framework.validation.address.create']);
        static::assertInstanceOf(BuildValidationEvent::class, $events['framework.validation.address.update']);
        static::assertInstanceOf(CheckoutConfirmPageLoadedEvent::class, $events[0]);
    }

    public function testCartServiceIsCalledTaxedAndWithNoCaching(): void
    {
        $cartService = static::createMock(StorefrontCartFacade::class);
        $cartService
            ->expects($this->once())
            ->method('getWithCheckoutGateway')
            ->with(static::isInstanceOf(Request::class), 'token', static::isInstanceOf(SalesChannelContext::class), false, true)
            ->willReturn($this->createCartGatewayResult());

        $this->createLoader(cartService: $cartService)->load(new Request(), $this->getContextWithDummyCustomer());
    }

    public function testValidationEventIsDispatchedWithZipcodeDefinition(): void
    {
        $countryId = Uuid::randomHex();

        $cart = new Cart('test');

        $cartService = $this->createMock(StorefrontCartFacade::class);
        $cartService
            ->method('getWithCheckoutGateway')
            ->willReturn($this->createCartGatewayResult($cart));

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

        $context = $this->getContextWithDummyCustomer($countryId);

        $this->createLoader(
            eventDispatcher: $dispatcher,
            cartService: $cartService,
            addressValidationFactory: $addressValidation
        )->load(new Request(), $context);
    }

    private function createLoader(
        ?EventDispatcherInterface $eventDispatcher = null,
        ?StorefrontCartFacade $cartService = null,
        ?GenericPageLoader $pageLoader = null,
        ?DataValidationFactoryInterface $addressValidationFactory = null,
        ?DataValidator $validator = null
    ): CheckoutConfirmPageLoader {
        return new CheckoutConfirmPageLoader(
            $eventDispatcher ?? new EventDispatcher(),
            $cartService ?? $this->createCartService(),
            $pageLoader ?? $this->createPageLoader(),
            $addressValidationFactory ?? $this->createAddressValidationFactory(),
            $validator ?? $this->createValidator()
        );
    }

    private function createCartService(): StorefrontCartFacade
    {
        $cartService = $this->createMock(StorefrontCartFacade::class);
        $cartService
            ->method('getWithCheckoutGateway')
            ->willReturn($this->createCartGatewayResult());

        return $cartService;
    }

    private function createPageLoader(): GenericPageLoader
    {
        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn(new CheckoutConfirmPage());

        return $pageLoader;
    }

    private function createAddressValidationFactory(): DataValidationFactoryInterface
    {
        $addressValidationFactory = $this->createMock(DataValidationFactoryInterface::class);
        $addressValidationFactory
            ->method('create')
            ->willReturn(new DataValidationDefinition('address.create'));

        return $addressValidationFactory;
    }

    private function createValidator(): DataValidator
    {
        $validator = $this->createMock(DataValidator::class);
        $validator
            ->method('getViolations')
            ->willReturn(new ConstraintViolationList());

        return $validator;
    }

    private function createCartGatewayResult(?Cart $cart = null): StorefrontCartGatewayResult
    {
        return new StorefrontCartGatewayResult(
            $cart ?? new Cart('test'),
            new CheckoutGatewayRouteResponse(
                new PaymentMethodCollection(),
                new ShippingMethodCollection(),
                new ErrorCollection()
            )
        );
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
        $context
            ->method('getToken')
            ->willReturn('token');
        $context
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        return $context;
    }
}

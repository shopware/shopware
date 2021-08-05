<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Confirm;

use Shopware\Core\Checkout\Cart\Address\Error\AddressValidationError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class CheckoutConfirmPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var AbstractShippingMethodRoute
     */
    private $shippingMethodRoute;

    /**
     * @var AbstractPaymentMethodRoute
     */
    private $paymentMethodRoute;

    /**
     * @var GenericPageLoaderInterface
     */
    private $genericPageLoader;

    /**
     * @var DataValidationFactoryInterface
     */
    private $addressValidationFactory;

    /**
     * @var DataValidator
     */
    private $validator;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService,
        AbstractShippingMethodRoute $shippingMethodRoute,
        AbstractPaymentMethodRoute $paymentMethodRoute,
        GenericPageLoaderInterface $genericPageLoader,
        DataValidationFactoryInterface $addressValidationFactory,
        DataValidator $validator
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->cartService = $cartService;
        $this->shippingMethodRoute = $shippingMethodRoute;
        $this->paymentMethodRoute = $paymentMethodRoute;
        $this->genericPageLoader = $genericPageLoader;
        $this->addressValidationFactory = $addressValidationFactory;
        $this->validator = $validator;
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     */
    public function load(Request $request, SalesChannelContext $context): CheckoutConfirmPage
    {
        $page = $this->genericPageLoader->load($request, $context);
        $page = CheckoutConfirmPage::createFrom($page);

        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        $page->setPaymentMethods($this->getPaymentMethods($context));
        $page->setShippingMethods($this->getShippingMethods($context));

        $cart = $this->cartService->getCart($context->getToken(), $context);
        $this->validateCustomerAddresses($cart, $context);
        $page->setCart($cart);

        $this->eventDispatcher->dispatch(
            new CheckoutConfirmPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function getPaymentMethods(SalesChannelContext $context): PaymentMethodCollection
    {
        $request = new Request();
        $request->query->set('onlyAvailable', '1');

        return $this->paymentMethodRoute->load($request, $context, new Criteria())->getPaymentMethods();
    }

    private function getShippingMethods(SalesChannelContext $context): ShippingMethodCollection
    {
        $request = new Request();
        $request->query->set('onlyAvailable', '1');

        $shippingMethods = $this->shippingMethodRoute
            ->load($request, $context, new Criteria())
            ->getShippingMethods();

        if (!$shippingMethods->has($context->getShippingMethod()->getId())) {
            $shippingMethods->add($context->getShippingMethod());
        }

        return $shippingMethods;
    }

    /**
     * @throws CustomerNotLoggedInException
     */
    private function validateCustomerAddresses(Cart $cart, SalesChannelContext $context): void
    {
        $customer = $context->getCustomer();
        if ($customer === null) {
            throw new CustomerNotLoggedInException();
        }

        $billingAddress = $customer->getActiveBillingAddress();
        $shippingAddress = $customer->getActiveShippingAddress();

        $this->validateBillingAddress($billingAddress, $cart, $context);
        $this->validateShippingAddress($shippingAddress, $billingAddress, $cart, $context);
    }

    private function validateBillingAddress(
        ?CustomerAddressEntity $billingAddress,
        Cart $cart,
        SalesChannelContext $context
    ): void {
        $validation = $this->addressValidationFactory->create($context);
        $validationEvent = new BuildValidationEvent($validation, new DataBag(), $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent);

        if ($billingAddress === null) {
            return;
        }

        $violations = $this->validator->getViolations($billingAddress->jsonSerialize(), $validation);

        if ($violations->count() > 0) {
            $cart->getErrors()->add(new AddressValidationError(true, $violations));
        }
    }

    private function validateShippingAddress(
        ?CustomerAddressEntity $shippingAddress,
        ?CustomerAddressEntity $billingAddress,
        Cart $cart,
        SalesChannelContext $context
    ): void {
        $validation = $this->addressValidationFactory->create($context);
        $validationEvent = new BuildValidationEvent($validation, new DataBag(), $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent);

        if ($shippingAddress === null) {
            return;
        }

        if ($billingAddress !== null && $shippingAddress->getId() === $billingAddress->getId()) {
            return;
        }

        $violations = $this->validator->getViolations($shippingAddress->jsonSerialize(), $validation);
        if ($violations->count() > 0) {
            $cart->getErrors()->add(new AddressValidationError(false, $violations));
        }
    }
}

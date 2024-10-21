<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Confirm;

use Shopware\Core\Checkout\Cart\Address\Error\AddressValidationError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('storefront')]
class CheckoutConfirmPageLoader
{
    /**
     * @internal
     *
     * @deprecated tag:v6.7.0 - translator will be mandatory from 6.7
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly StorefrontCartFacade $cartService,
        private readonly AbstractCheckoutGatewayRoute $checkoutGatewayRoute,
        private readonly GenericPageLoaderInterface $genericPageLoader,
        private readonly DataValidationFactoryInterface $addressValidationFactory,
        private readonly DataValidator $validator,
        private readonly ?AbstractTranslator $translator = null
    ) {
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     */
    public function load(Request $request, SalesChannelContext $context): CheckoutConfirmPage
    {
        $page = $this->genericPageLoader->load($request, $context);
        $page = CheckoutConfirmPage::createFrom($page);
        $this->setMetaInformation($page);

        $cart = $this->cartService->get($context->getToken(), $context, false, true);

        $response = $this->checkoutGatewayRoute->load($request, $cart, $context);

        $page->setPaymentMethods($response->getPaymentMethods());
        $page->setShippingMethods($response->getShippingMethods());

        $this->validateCustomerAddresses($cart, $context);
        $page->setCart($cart);

        $page->setShowRevocation($cart->getLineItems()->hasLineItemWithState(State::IS_DOWNLOAD));
        $page->setHideShippingAddress(!$cart->getLineItems()->hasLineItemWithState(State::IS_PHYSICAL));

        $this->eventDispatcher->dispatch(
            new CheckoutConfirmPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    protected function setMetaInformation(CheckoutConfirmPage $page): void
    {
        /**
         * @deprecated tag:v6.7.0 - Remove condtion in 6.7.
         */
        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        /**
         * @deprecated tag:v6.7.0 - Remove condition with body in 6.7.
         */
        if ($this->translator !== null && $page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        if ($this->translator !== null) {
            $page->getMetaInformation()?->setMetaTitle(
                $this->translator->trans('checkout.confirmMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
            );
        }
    }

    /**
     * @throws CustomerNotLoggedInException
     */
    private function validateCustomerAddresses(Cart $cart, SalesChannelContext $context): void
    {
        $customer = $context->getCustomer();
        if ($customer === null) {
            throw CartException::customerNotLoggedIn();
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
        if ($billingAddress) {
            $validation->set('zipcode', new CustomerZipCode(['countryId' => $billingAddress->getCountryId()]));
        }

        $validationEvent = new BuildValidationEvent($validation, new DataBag(), $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

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
        if ($shippingAddress) {
            $validation->set('zipcode', new CustomerZipCode(['countryId' => $shippingAddress->getCountryId()]));
        }

        $validationEvent = new BuildValidationEvent($validation, new DataBag(), $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

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

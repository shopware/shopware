<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Address;

use Shopware\Core\Checkout\Cart\Address\Error\AddressValidationError;
use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressBlockedError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressBlockedError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AddressValidator implements CartValidatorInterface
{
    /**
     * @var DataValidationFactoryInterface
     */
    private $addressValidationFactory;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var DataValidator
     */
    private $validator;

    public function __construct(
        DataValidationFactoryInterface $addressValidationFactory,
        EventDispatcherInterface $eventDispatcher,
        DataValidator $validator
    ) {
        $this->addressValidationFactory = $addressValidationFactory;
        $this->eventDispatcher = $eventDispatcher;
        $this->validator = $validator;
    }

    public function validate(
        Cart $cart,
        ErrorCollection $errors,
        SalesChannelContext $salesChannelContext
    ): void {
        $salesChannelCountries = $salesChannelContext->getSalesChannel()->getCountries();
        $this->validateShippingCountry($salesChannelCountries, $errors, $salesChannelContext);

        $customer = $salesChannelContext->getCustomer();
        if ($customer === null) {
            return;
        }

        $billingAddress = $customer->getActiveBillingAddress();
        $shippingAddress = $customer->getActiveShippingAddress();

        $this->validateBillingAddress($billingAddress, $salesChannelCountries, $errors, $salesChannelContext);
        $this->validateShippingAddress($shippingAddress, $billingAddress, $errors, $salesChannelContext);
    }

    private function validateShippingCountry(
        ?CountryCollection $salesChannelCountries,
        ErrorCollection $errors,
        SalesChannelContext $salesChannelContext
    ): void {
        $shippingCountry = $salesChannelContext->getShippingLocation()->getCountry();
        if (!$shippingCountry->getActive()
            || !$shippingCountry->getShippingAvailable()
            || ($salesChannelCountries !== null && !$salesChannelCountries->has($shippingCountry->getId()))
        ) {
            $errors->add(new ShippingAddressBlockedError($shippingCountry->getTranslation('name')));
        }
    }

    private function validateBillingAddress(
        ?CustomerAddressEntity $billingAddress,
        ?CountryCollection $salesChannelCountries,
        ErrorCollection $errors,
        SalesChannelContext $salesChannelContext
    ): void {
        $validation = $this->addressValidationFactory->create($salesChannelContext);
        $validationEvent = new BuildValidationEvent($validation, $salesChannelContext->getContext());
        $this->eventDispatcher->dispatch($validationEvent);

        if ($billingAddress !== null) {
            $violations = $this->validator->getViolations($billingAddress->jsonSerialize(), $validation);
            $billingCountry = $billingAddress->getCountry();

            if ($violations->count()) {
                $errors->add(new AddressValidationError(true, $violations));
            }

            if ($billingCountry !== null && (!$billingCountry->getActive() || ($salesChannelCountries !== null && !$salesChannelCountries->has($billingAddress->getCountryId())))) {
                $errors->add(new BillingAddressBlockedError($billingCountry->getTranslation('name')));
            }
        }
    }

    private function validateShippingAddress(
        ?CustomerAddressEntity $shippingAddress,
        ?CustomerAddressEntity $billingAddress,
        ErrorCollection $errors,
        SalesChannelContext $salesChannelContext
    ): void {
        $validation = $this->addressValidationFactory->create($salesChannelContext);
        $validationEvent = new BuildValidationEvent($validation, $salesChannelContext->getContext());
        $this->eventDispatcher->dispatch($validationEvent);

        if ($shippingAddress !== null && ($billingAddress === null || $shippingAddress->getId() !== $billingAddress->getId())) {
            $violations = $this->validator->getViolations($shippingAddress->jsonSerialize(), $validation);
            if ($violations->count()) {
                $errors->add(new AddressValidationError(false, $violations));
            }
        }
    }
}

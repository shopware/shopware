<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Address;

use Shopware\Core\Checkout\Cart\Address\Error\AddressValidationError;
use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressBlockedError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressBlockedError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
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

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    public function __construct(
        DataValidationFactoryInterface $addressValidationFactory,
        EventDispatcherInterface $eventDispatcher,
        DataValidator $validator,
        EntityRepositoryInterface $repository
    ) {
        $this->addressValidationFactory = $addressValidationFactory;
        $this->eventDispatcher = $eventDispatcher;
        $this->validator = $validator;
        $this->repository = $repository;
    }

    public function validate(
        Cart $cart,
        ErrorCollection $errors,
        SalesChannelContext $context
    ): void {
        $this->validateShippingCountry($errors, $context);

        $customer = $context->getCustomer();
        if ($customer === null) {
            return;
        }

        $billingAddress = $customer->getActiveBillingAddress();
        $shippingAddress = $customer->getActiveShippingAddress();

        $this->validateBillingAddress($billingAddress, $errors, $context);
        $this->validateShippingAddress($shippingAddress, $billingAddress, $errors, $context);
    }

    private function validateShippingCountry(
        ErrorCollection $errors,
        SalesChannelContext $context
    ): void {
        $country = $context->getShippingLocation()->getCountry();

        if (!$country->getActive()) {
            $errors->add(new ShippingAddressBlockedError((string) $country->getTranslation('name')));

            return;
        }

        if (!$country->getShippingAvailable()) {
            $errors->add(new ShippingAddressBlockedError((string) $country->getTranslation('name')));

            return;
        }

        if (!$this->isSalesChannelCountry($country->getId(), $context)) {
            $errors->add(new ShippingAddressBlockedError((string) $country->getTranslation('name')));

            return;
        }
    }

    private function validateBillingAddress(
        ?CustomerAddressEntity $billingAddress,
        ErrorCollection $errors,
        SalesChannelContext $context
    ): void {
        $validation = $this->addressValidationFactory->create($context);
        $validationEvent = new BuildValidationEvent($validation, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent);

        if ($billingAddress === null) {
            return;
        }

        $violations = $this->validator->getViolations($billingAddress->jsonSerialize(), $validation);
        $country = $billingAddress->getCountry();

        if ($violations->count()) {
            $errors->add(new AddressValidationError(true, $violations));
        }

        if ($country === null) {
            return;
        }

        if (!$country->getActive()) {
            $errors->add(new BillingAddressBlockedError((string) $country->getTranslation('name')));

            return;
        }

        if (!$this->isSalesChannelCountry($country->getId(), $context)) {
            $errors->add(new BillingAddressBlockedError((string) $country->getTranslation('name')));

            return;
        }
    }

    private function isSalesChannelCountry(string $countryId, SalesChannelContext $context): bool
    {
        $criteria = new Criteria([$countryId]);
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $context->getSalesChannelId()));

        $available = $this->repository->searchIds($criteria, $context->getContext());

        return $available->has($countryId);
    }

    private function validateShippingAddress(
        ?CustomerAddressEntity $shippingAddress,
        ?CustomerAddressEntity $billingAddress,
        ErrorCollection $errors,
        SalesChannelContext $context
    ): void {
        $validation = $this->addressValidationFactory->create($context);
        $validationEvent = new BuildValidationEvent($validation, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent);

        if ($shippingAddress !== null && ($billingAddress === null || $shippingAddress->getId() !== $billingAddress->getId())) {
            $violations = $this->validator->getViolations($shippingAddress->jsonSerialize(), $validation);
            if ($violations->count()) {
                $errors->add(new AddressValidationError(false, $violations));
            }
        }
    }
}

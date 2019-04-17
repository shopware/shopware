<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\Framework\Validation\ValidationServiceInterface;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class AccountRegistrationService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var NumberRangeValueGeneratorInterface
     */
    private $numberRangeValueGenerator;

    /**
     * @var ValidationServiceInterface
     */
    private $addressValidationService;

    /**
     * @var DataValidator
     */
    private $validator;

    /**
     * @var ValidationServiceInterface
     */
    private $accountValidationService;

    public function __construct(
        EntityRepositoryInterface $customerRepository,
        EventDispatcherInterface $eventDispatcher,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        DataValidator $validator,
        ValidationServiceInterface $accountValidationService,
        ValidationServiceInterface $addressValidationService
    ) {
        $this->customerRepository = $customerRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
        $this->validator = $validator;
        $this->accountValidationService = $accountValidationService;
        $this->addressValidationService = $addressValidationService;
    }

    public function register(DataBag $data, bool $isGuest, SalesChannelContext $context): string
    {
        $this->validateRegistrationData($data, $isGuest, $context->getContext());

        $customer = $this->mapCustomerData($data, $isGuest, $context);

        $billingAddress = $this->mapBillingAddress($data->get('billingAddress'), $context->getContext());
        $billingAddress['id'] = Uuid::randomHex();
        $billingAddress['customerId'] = $customer['id'];

        // if no shipping address is provided, use the billing address
        $customer['defaultShippingAddressId'] = $billingAddress['id'];
        $customer['defaultBillingAddressId'] = $billingAddress['id'];
        $customer['addresses'][] = $billingAddress;

        if ($shipping = $data->get('shippingAddress')) {
            $shippingAddress = $this->mapShippingAddress($shipping, $context->getContext());
            $shippingAddress['id'] = Uuid::randomHex();
            $shippingAddress['customerId'] = $customer['id'];

            $customer['defaultShippingAddressId'] = $shippingAddress['id'];
            $customer['addresses'][] = $shippingAddress;
        }

        $this->customerRepository->create([$customer], $context->getContext());

        return $customer['id'];
    }

    private function validateRegistrationData(DataBag $data, bool $isGuest, Context $context): void
    {
        /** @var DataBag $addressData */
        $addressData = $data->get('billingAddress');
        $addressData->set('firstName', $data->get('firstName'));
        $addressData->set('lastName', $data->get('lastName'));
        $addressData->set('salutationId', $data->get('salutationId'));

        $definition = $this->getCustomerCreateValidationDefinition($isGuest, $context);

        $definition->addSub('billingAddress', $this->getCreateAddressValidationDefinition($context));

        if ($data->has('shippingAddress')) {
            $definition->addSub('shippingAddress', $this->getCreateAddressValidationDefinition($context));
        }

        $violations = $this->validator->getViolations($data->all(), $definition);
        if (!$violations->count()) {
            return;
        }

        throw new ConstraintViolationException($violations, $data->all());
    }

    private function getBirthday(DataBag $data): ?\DateTimeInterface
    {
        $birthdayDay = $data->get('birthdayDay');
        $birthdayMonth = $data->get('birthdayMonth');
        $birthdayYear = $data->get('birthdayYear');

        if (!$birthdayDay || !$birthdayMonth || !$birthdayYear) {
            return null;
        }

        return new \DateTime(sprintf(
            '%s-%s-%s',
            $birthdayYear,
            $birthdayMonth,
            $birthdayDay
        ));
    }

    private function mapBillingAddress(DataBag $billing, Context $context): array
    {
        $billingAddress = $billing->only(
            'firstName',
            'lastName',
            'salutationId',
            'street',
            'zipcode',
            'city',
            'vatId',
            'countryStateId',
            'countryId',
            'additionalAddressLine1',
            'additionalAddressLine2',
            'phoneNumber'
        );

        $event = new DataMappingEvent(CustomerEvents::MAPPING_REGISTER_ADDRESS_BILLING, $billing, $billingAddress, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $event->getOutput();
    }

    private function mapShippingAddress(DataBag $shipping, Context $context): array
    {
        $shippingAddress = $shipping->only(
            'firstName',
            'lastName',
            'salutationId',
            'street',
            'zipcode',
            'city',
            'vatId',
            'countryStateId',
            'countryId',
            'additionalAddressLine1',
            'additionalAddressLine2',
            'phoneNumber'
        );

        $event = new DataMappingEvent(CustomerEvents::MAPPING_REGISTER_ADDRESS_SHIPPING, $shipping, $shippingAddress, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $event->getOutput();
    }

    private function mapCustomerData(DataBag $data, bool $isGuest, SalesChannelContext $context): array
    {
        $customer = [
            'customerNumber' => $this->numberRangeValueGenerator->getValue(
                CustomerDefinition::getEntityName(), $context->getContext(),
                $context->getSalesChannel()->getId()
            ),
            'salesChannelId' => $context->getSalesChannel()->getId(),
            'languageId' => $context->getContext()->getLanguageId(),
            'groupId' => $context->getCurrentCustomerGroup()->getId(),
            'defaultPaymentMethodId' => $context->getPaymentMethod()->getId(),
            'salutationId' => $data->get('salutationId'),
            'firstName' => $data->get('firstName'),
            'lastName' => $data->get('lastName'),
            'email' => $data->get('email'),
            'title' => $data->get('title'),
            'active' => true,
            'birthday' => $this->getBirthday($data),
            'guest' => $isGuest,
            'firstLogin' => new \DateTimeImmutable(),
            'addresses' => [],
        ];

        if (!$isGuest) {
            $customer['password'] = $data->get('password');
        }

        $event = new DataMappingEvent(CustomerEvents::MAPPING_REGISTER_CUSTOMER, $data, $customer, $context->getContext());
        $this->eventDispatcher->dispatch($event->getName(), $event);

        $customer = $event->getOutput();
        $customer['id'] = Uuid::randomHex();

        return $customer;
    }

    private function getCreateAddressValidationDefinition(Context $context): DataValidationDefinition
    {
        $validation = $this->addressValidationService->buildCreateValidation($context);

        $validationEvent = new BuildValidationEvent($validation, $context);
        $this->eventDispatcher->dispatch($validationEvent->getName(), $validationEvent);

        return $validation;
    }

    private function getCustomerCreateValidationDefinition(bool $isGuest, Context $context): DataValidationDefinition
    {
        $validation = $this->accountValidationService->buildCreateValidation($context);

        if (!$isGuest) {
            $validation->add('password', new NotBlank());
            $validation->add('email', new CustomerEmailUnique(['context' => $context]));
        }

        $validationEvent = new BuildValidationEvent($validation, $context);
        $this->eventDispatcher->dispatch($validationEvent->getName(), $validationEvent);

        return $validation;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\CannotDeleteDefaultAddressException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\Framework\Validation\ValidationServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class AddressService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $countryRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerAddressRepository;

    /**
     * @var ValidationServiceInterface|DataValidationFactoryInterface
     */
    private $addressValidationFactory;

    /**
     * @var DataValidator
     */
    private $validator;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @param ValidationServiceInterface|DataValidationFactoryInterface $addressValidationFactory
     */
    public function __construct(
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $customerAddressRepository,
        $addressValidationFactory,
        DataValidator $validator,
        EventDispatcherInterface $eventDispatcher,
        SystemConfigService $systemConfigService
    ) {
        $this->countryRepository = $countryRepository;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->addressValidationFactory = $addressValidationFactory;
        $this->validator = $validator;
        $this->eventDispatcher = $eventDispatcher;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @throws AddressNotFoundException
     * @throws InvalidUuidException
     */
    public function getById(string $addressId, SalesChannelContext $context): CustomerAddressEntity
    {
        return $this->validateAddressId($addressId, $context);
    }

    /**
     * @throws CustomerNotLoggedInException
     */
    public function getAddressByContext(SalesChannelContext $context): array
    {
        $this->validateCustomerIsLoggedIn($context);
        $customer = $context->getCustomer();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customer_address.customerId', $context->getCustomer()->getId()));

        /** @var CustomerAddressCollection $addresses */
        $addresses = $this->customerAddressRepository->search($criteria, $context->getContext())->getEntities();

        return $addresses->sortByDefaultAddress($customer)->getElements();
    }

    /**
     * @throws AddressNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws ConstraintViolationException
     */
    public function upsert(DataBag $data, SalesChannelContext $context): string
    {
        $this->validateCustomerIsLoggedIn($context);

        if ($id = $data->get('id')) {
            $this->validateAddressId((string) $id, $context);
            $isCreate = false;
        } else {
            $id = Uuid::randomHex();
            $isCreate = true;
        }

        $accountType = $data->get('accountType', CustomerEntity::ACCOUNT_TYPE_PRIVATE);
        $definition = $this->getValidationDefinition($accountType, $isCreate, $context);
        $this->validator->validate(array_merge(['id' => $id], $data->all()), $definition);

        $addressData = [
            'salutationId' => $data->get('salutationId'),
            'firstName' => $data->get('firstName'),
            'lastName' => $data->get('lastName'),
            'street' => $data->get('street'),
            'city' => $data->get('city'),
            'zipcode' => $data->get('zipcode'),
            'countryId' => $data->get('countryId'),
            'countryStateId' => $data->get('countryStateId') ? $data->get('countryStateId') : null,
            'company' => $data->get('company'),
            'department' => $data->get('department'),
            'title' => $data->get('title'),
            'vatId' => $data->get('vatId'),
            'phoneNumber' => $data->get('phoneNumber'),
            'additionalAddressLine1' => $data->get('additionalAddressLine1'),
            'additionalAddressLine2' => $data->get('additionalAddressLine2'),
        ];

        $mappingEvent = new DataMappingEvent($data, $addressData, $context->getContext());
        $this->eventDispatcher->dispatch($mappingEvent, CustomerEvents::MAPPING_ADDRESS_CREATE);

        $addressData = $mappingEvent->getOutput();
        $addressData['id'] = $id;
        $addressData['customerId'] = $context->getCustomer()->getId();

        $this->customerAddressRepository->upsert([$addressData], $context->getContext());

        return $id;
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     * @throws CannotDeleteDefaultAddressException
     */
    public function delete(string $addressId, SalesChannelContext $context): void
    {
        $this->validateCustomerIsLoggedIn($context);
        $this->validateAddressId($addressId, $context);

        /** @var CustomerEntity $customer */
        $customer = $context->getCustomer();

        if ($addressId === $customer->getDefaultBillingAddressId()
            || $addressId === $customer->getDefaultShippingAddressId()) {
            throw new CannotDeleteDefaultAddressException($addressId);
        }

        $this->customerAddressRepository->delete([['id' => $addressId]], $context->getContext());
    }

    /**
     * @throws CustomerNotLoggedInException
     */
    private function validateCustomerIsLoggedIn(SalesChannelContext $context): void
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }
    }

    /**
     * @throws AddressNotFoundException
     * @throws InvalidUuidException
     */
    private function validateAddressId(string $addressId, SalesChannelContext $context): CustomerAddressEntity
    {
        if (!Uuid::isValid($addressId)) {
            throw new InvalidUuidException($addressId);
        }

        $this->validateCustomerIsLoggedIn($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $addressId));
        $criteria->addFilter(new EqualsFilter('customerId', $context->getCustomer()->getId()));

        /** @var CustomerAddressEntity|null $address */
        $address = $this->customerAddressRepository
            ->search($criteria, $context->getContext())
            ->get($addressId);

        if (!$address) {
            throw new AddressNotFoundException($addressId);
        }

        return $address;
    }

    private function getValidationDefinition(string $accountType, bool $isCreate, SalesChannelContext $context): DataValidationDefinition
    {
        if ($this->addressValidationFactory instanceof DataValidationFactoryInterface) {
            if ($isCreate) {
                $validation = $this->addressValidationFactory->create($context);
            } else {
                $validation = $this->addressValidationFactory->update($context);
            }
        } else {
            if ($isCreate) {
                $validation = $this->addressValidationFactory->buildCreateValidation($context->getContext());
            } else {
                $validation = $this->addressValidationFactory->buildUpdateValidation($context->getContext());
            }
        }

        if ($accountType === CustomerEntity::ACCOUNT_TYPE_BUSINESS && $this->systemConfigService->get('core.loginRegistration.showAccountTypeSelection')) {
            $validation->add('company', new NotBlank());
        }

        $validationEvent = new BuildValidationEvent($validation, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validation;
    }
}

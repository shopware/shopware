<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Storefront;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundException;
use Shopware\Core\Checkout\Customer\Validation\CustomerValidationService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\Salutation\SalutationCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Validator\Constraints\NotBlank;

class AccountService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $customerAddressRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $salutationRepository;

    /**
     * @var CheckoutContextPersister
     */
    private $contextPersister;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var DataValidator
     */
    private $validator;

    /**
     * @var CustomerValidationService
     */
    private $customerValidationService;

    public function __construct(
        EntityRepositoryInterface $customerAddressRepository,
        EntityRepositoryInterface $customerRepository,
        EntityRepositoryInterface $salutationRepository,
        CheckoutContextPersister $contextPersister,
        EventDispatcherInterface $eventDispatcher,
        DataValidator $validator,
        CustomerValidationService $customerValidationService
    ) {
        $this->customerAddressRepository = $customerAddressRepository;
        $this->customerRepository = $customerRepository;
        $this->salutationRepository = $salutationRepository;
        $this->contextPersister = $contextPersister;
        $this->eventDispatcher = $eventDispatcher;
        $this->validator = $validator;
        $this->customerValidationService = $customerValidationService;
    }

    /**
     * @throws CustomerNotFoundException
     * @throws BadCredentialsException
     */
    public function getCustomerByLogin(string $email, string $password, CheckoutContext $context): CustomerEntity
    {
        $customer = $this->getCustomerByEmail($email, $context);

        if (!password_verify($password, $customer->getPassword())) {
            throw new BadCredentialsException();
        }

        return $customer;
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function getCustomerByEmail(string $email, CheckoutContext $context, bool $includeGuest = false): CustomerEntity
    {
        $customers = $this->getCustomersByEmail($email, $context, $includeGuest);

        if ($customers->count() !== 1) {
            throw new CustomerNotFoundException($email);
        }

        /** @var CustomerEntity $customer */
        $customer = $customers->first();

        return $customer;
    }

    public function getCustomersByEmail(string $email, CheckoutContext $context, bool $includeGuests = true): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customer.email', $email));
        if (!$includeGuests) {
            $criteria->addFilter(new EqualsFilter('customer.guest', 0));
        }
        // TODO NEXT-389 we have to check an option like "bind customer to salesChannel"
        // todo in this case we have to filter "customer.salesChannelId is null or salesChannelId = :current"

        return $this->customerRepository->search($criteria, $context->getContext());
    }

    /**
     * @throws CustomerNotLoggedInException
     */
    public function getCustomerByContext(CheckoutContext $context): CustomerEntity
    {
        $this->validateCustomer($context);

        return $context->getCustomer();
    }

    public function saveProfile(DataBag $data, CheckoutContext $context): void
    {
        $validation = $this->getUpdateValidationDefinition($context->getContext());
        $this->validator->validate($data->all(), $validation);

        $customer = $data->only('firstName', 'lastName', 'salutationId', 'title');

        if ($birthday = $this->getBirthday($data)) {
            $customer['birthday'] = $birthday;
        }

        $mappingEvent = new DataMappingEvent(CustomerEvents::MAPPING_CUSTOMER_PROFILE_SAVE, $data, $customer, $context->getContext());
        $this->eventDispatcher->dispatch($mappingEvent->getName(), $mappingEvent);

        $customer = $mappingEvent->getOutput();
        $customer['id'] = $context->getCustomer()->getId();

        $this->customerRepository->update([$customer], $context->getContext());
    }

    public function getSalutationList(CheckoutContext $context): SalutationCollection
    {
        $criteria = new Criteria([]);
        $criteria->addSorting(new FieldSorting('salutationKey', 'DESC'));

        /** @var SalutationCollection $salutations */
        $salutations = $this->salutationRepository->search($criteria, $context->getContext())
            ->getEntities();

        return $salutations;
    }

    public function savePassword(DataBag $data, CheckoutContext $context): void
    {
        $this->validateCustomer($context);

        $definition = new DataValidationDefinition('customer.change_password');
        $definition->add('password', new NotBlank());

        $this->validator->validate($data->only('password'), $definition);

        $customerData = [
            'id' => $context->getCustomer()->getId(),
            'password' => $data->get('password'),
        ];

        $this->customerRepository->update([$customerData], $context->getContext());
    }

    public function saveEmail(DataBag $data, CheckoutContext $context): void
    {
        $this->validateCustomer($context);

        $this->validator->validate(
            $data->only('email'),
            $this->getUpdateValidationDefinition($context->getContext())
        );

        $customerData = [
            'id' => $context->getCustomer()->getId(),
            'email' => $data->get('email'),
        ];

        $this->customerRepository->update([$customerData], $context->getContext());
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function setDefaultBillingAddress(string $addressId, CheckoutContext $context): void
    {
        $this->validateCustomer($context);
        $this->validateAddressId($addressId, $context);

        $data = [
            'id' => $context->getCustomer()->getId(),
            'defaultBillingAddressId' => $addressId,
        ];
        $this->customerRepository->update([$data], $context->getContext());
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function setDefaultShippingAddress(string $addressId, CheckoutContext $context): void
    {
        $this->validateCustomer($context);
        $this->validateAddressId($addressId, $context);

        $data = [
            'id' => $context->getCustomer()->getId(),
            'defaultShippingAddressId' => $addressId,
        ];
        $this->customerRepository->update([$data], $context->getContext());
    }

    /**
     * @throws BadCredentialsException
     * @throws UnauthorizedHttpException
     */
    public function login(string $email, CheckoutContext $context): string
    {
        if (empty($email)) {
            throw new BadCredentialsException();
        }

        try {
            $user = $this->getCustomerByEmail($email, $context);
        } catch (CustomerNotFoundException | BadCredentialsException $exception) {
            throw new UnauthorizedHttpException('json', $exception->getMessage());
        }

        $this->contextPersister->save(
            $context->getToken(),
            [
                'customerId' => $user->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ]
        );

        $event = new CustomerLoginEvent($context->getContext(), $user);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $context->getToken();
    }

    /**
     * @throws BadCredentialsException
     * @throws UnauthorizedHttpException
     */
    public function loginWithPassword(DataBag $data, CheckoutContext $context): string
    {
        if (empty($data->get('username')) || empty($data->get('password'))) {
            throw new BadCredentialsException();
        }

        try {
            $user = $this->getCustomerByLogin(
                $data->get('username'),
                $data->get('password'),
                $context
            );
        } catch (CustomerNotFoundException | BadCredentialsException $exception) {
            throw new UnauthorizedHttpException('json', $exception->getMessage());
        }

        $this->contextPersister->save(
            $context->getToken(),
            [
                'customerId' => $user->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ]
        );

        $this->customerRepository->update([
            [
                'id' => $user->getId(),
                'lastLogin' => new \DateTimeImmutable(),
            ],
        ], $context->getContext());

        $event = new CustomerLoginEvent($context->getContext(), $user);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $context->getToken();
    }

    public function logout(CheckoutContext $context): void
    {
        $this->contextPersister->save(
            $context->getToken(),
            [
                'customerId' => null,
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ]
        );

        $event = new CustomerLogoutEvent($context->getContext(), $context->getCustomer());
        $this->eventDispatcher->dispatch($event->getName(), $event);
    }

    /**
     * @throws CustomerNotLoggedInException
     */
    private function validateCustomer(CheckoutContext $context): void
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }
    }

    /**
     * @throws AddressNotFoundException
     * @throws InvalidUuidException
     */
    private function validateAddressId(string $addressId, CheckoutContext $context): CustomerAddressEntity
    {
        if (!Uuid::isValid($addressId)) {
            throw new InvalidUuidException($addressId);
        }

        /** @var CustomerAddressEntity|null $address */
        $address = $this->customerAddressRepository->search(new Criteria([$addressId]), $context->getContext())->get($addressId);

        if (!$address || $address->getCustomerId() !== $context->getCustomer()->getId()) {
            throw new AddressNotFoundException($addressId);
        }

        return $address;
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

    private function getUpdateValidationDefinition(Context $context): DataValidationDefinition
    {
        $validation = $this->customerValidationService->buildUpdateValidation($context);

        $validationEvent = new BuildValidationEvent($validation, $context);
        $this->eventDispatcher->dispatch($validationEvent->getName(), $validationEvent);

        return $validation;
    }
}

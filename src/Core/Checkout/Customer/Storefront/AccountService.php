<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Storefront;

use Shopware\Core\Checkout\Cart\Exception\CustomerAccountExistsException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Exception\CustomerNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Exception\InvalidUuidException;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AccountService
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
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CheckoutContextPersister
     */
    private $contextPersister;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $customerAddressRepository,
        EntityRepositoryInterface $customerRepository,
        CheckoutContextPersister $contextPersister,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->countryRepository = $countryRepository;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->customerRepository = $customerRepository;
        $this->contextPersister = $contextPersister;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws \Shopware\Core\Checkout\Exception\CustomerNotFoundException
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
     * @throws \Shopware\Core\Checkout\Exception\CustomerNotFoundException
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

    public function saveProfile(InternalRequest $request, CheckoutContext $context): void
    {
        $data = [
            'id' => $context->getCustomer()->getId(),
            'firstName' => $request->requirePost('firstName'),
            'lastName' => $request->requirePost('lastName'),
            'salutation' => $request->requirePost('salutation'),
            'title' => $request->optionalPost('title'),
            'birthday' => $this->getBirthday($request),
        ];

        $this->customerRepository->update([$data], $context->getContext());
    }

    public function savePassword(InternalRequest $request, CheckoutContext $context): void
    {
        $data = [
            'id' => $context->getCustomer()->getId(),
            'password' => $request->requirePost('password'),
        ];

        $this->customerRepository->update([$data], $context->getContext());
    }

    public function saveEmail(InternalRequest $request, CheckoutContext $context): void
    {
        $data = [
            'id' => $context->getCustomer()->getId(),
            'email' => $request->requirePost('email'),
        ];

        $this->customerRepository->update([$data], $context->getContext());
    }

    /**
     * @throws \Shopware\Core\Checkout\Exception\AddressNotFoundException
     * @throws InvalidUuidException
     */
    public function getAddressById(string $addressId, CheckoutContext $context): CustomerAddressEntity
    {
        return $this->validateAddressId($addressId, $context);
    }

    public function getCountryList(CheckoutContext $context): CountryCollection
    {
        $criteria = new Criteria([]);
        $criteria->addFilter(new EqualsFilter('country.active', true));

        /** @var CountryCollection $countries */
        $countries = $this->countryRepository->search($criteria, $context->getContext())
            ->getEntities();

        $countries->sortCountryAndStates();

        return $countries;
    }

    /**
     * @throws CustomerNotLoggedInException
     */
    public function getAddressesByCustomer(CheckoutContext $context): array
    {
        $this->validateCustomer($context);
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
     */
    public function saveAddress(InternalRequest $request, CheckoutContext $context): string
    {
        $this->validateCustomer($context);

        $id = $request->optionalPost('addressId');

        if (!$id) {
            $id = Uuid::uuid4()->getHex();
        } else {
            $this->validateAddressId((string) $id, $context)->getId();
        }

        $data = [
            'id' => $id,
            'customerId' => $context->getCustomer()->getId(),
            'salutation' => $request->requirePost('salutation'),
            'firstName' => $request->requirePost('firstName'),
            'lastName' => $request->requirePost('lastName'),
            'street' => $request->requirePost('street'),
            'city' => $request->requirePost('city'),
            'zipcode' => $request->requirePost('zipcode'),
            'countryId' => $request->requirePost('countryId'),
            'countryStateId' => $request->optionalPost('countryStateId'),
            'company' => $request->optionalPost('company'),
            'department' => $request->optionalPost('department'),
            'title' => $request->optionalPost('title'),
            'vatId' => $request->optionalPost('vatId'),
            'additionalAddressLine1' => $request->optionalPost('additionalAddressLine1'),
            'additionalAddressLine2' => $request->optionalPost('additionalAddressLine2'),
        ];

        $this->customerAddressRepository->upsert([$data], $context->getContext());

        return $id;
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws \Shopware\Core\Checkout\Exception\AddressNotFoundException
     */
    public function deleteAddress(string $addressId, CheckoutContext $context): void
    {
        $this->validateCustomer($context);
        $this->validateAddressId($addressId, $context);
        $this->customerAddressRepository->delete([['id' => $addressId]], $context->getContext());
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws \Shopware\Core\Checkout\Exception\AddressNotFoundException
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
     * @throws \Shopware\Core\Checkout\Exception\AddressNotFoundException
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

    public function createNewCustomer(InternalRequest $request, CheckoutContext $context): string
    {
        $this->validateRegistrationRequest($request, $context);

        $customerId = Uuid::uuid4()->getHex();
        $billingAddressId = Uuid::uuid4()->getHex();

        $addresses = [];

        $addresses[] = array_filter([
            'id' => $billingAddressId,
            'customerId' => $customerId,
            'firstName' => $request->requirePost('firstName'),
            'lastName' => $request->requirePost('lastName'),

            'salutation' => $request->optionalPost('salutation'),

            'street' => $request->requirePost('billingAddress.street'),
            'zipcode' => $request->requirePost('billingAddress.zipcode'),
            'city' => $request->requirePost('billingAddress.city'),
            'vatId' => $request->optionalPost('billingAddress.vatId'),
            'countryStateId' => $request->optionalPost('billingAddress.countryStateId'),
            'countryId' => $request->requirePost('billingAddress.country'),
            'additionalAddressLine1' => $request->optionalPost('billingAddress.additionalAddressLine1'),
            'additionalAddressLine2' => $request->optionalPost('billingAddress.additionalAddressLine2'),
            'phoneNumber' => $request->optionalPost('billingAddress.phone'),
        ]);

        if ($request->optionalPost('shippingAddress.country')) {
            $shippingAddressId = Uuid::uuid4()->getHex();

            $addresses[] = array_filter([
                'id' => $shippingAddressId,
                'customerId' => $customerId,
                'countryId' => $request->requirePost('shippingAddress.country'),
                'salutation' => $request->requirePost('shippingAddress.salutation'),
                'firstName' => $request->requirePost('shippingAddress.firstName'),
                'lastName' => $request->requirePost('shippingAddress.lastName'),
                'street' => $request->requirePost('shippingAddress.street'),
                'zipcode' => $request->requirePost('shippingAddress.zipcode'),
                'city' => $request->requirePost('shippingAddress.city'),
                'phoneNumber' => $request->optionalPost('shippingAddress.phone'),
                'vatId' => $request->optionalPost('shippingAddress.vatId'),
                'additionalAddressLine1' => $request->optionalPost('shippingAddress.additionalAddressLine1'),
                'additionalAddressLine2' => $request->optionalPost('shippingAddress.additionalAddressLine2'),
                'countryStateId' => $request->optionalPost('shippingAddress.countryStateId'),
            ]);
        }

        $guest = $request->getParam('guest');

        $data = [
            'id' => $customerId,
            'salesChannelId' => $context->getSalesChannel()->getId(),
            'groupId' => $context->getCurrentCustomerGroup()->getId(),
            'defaultPaymentMethodId' => $context->getPaymentMethod()->getId(),
            'customerNumber' => '123',
            'salutation' => $request->optionalPost('salutation'),
            'firstName' => $request->requirePost('firstName'),
            'lastName' => $request->requirePost('lastName'),
            'email' => $request->requirePost('email'),
            'title' => $request->optionalPost('title'),
            'encoder' => 'bcrypt',
            'active' => true,
            'defaultBillingAddressId' => $billingAddressId,
            'defaultShippingAddressId' => $shippingAddressId ?? $billingAddressId,
            'addresses' => $addresses,
            'birthday' => $this->getBirthday($request),
            'guest' => $guest,
        ];

        if (!$guest) {
            $data['password'] = $request->requirePost('password');
        }

        $data = array_filter($data, function ($element) {
            return $element !== null;
        });

        $this->customerRepository->create([$data], $context->getContext());

        return $customerId;
    }

    /**
     * @throws \Shopware\Core\Checkout\Exception\BadCredentialsException
     * @throws UnauthorizedHttpException
     */
    public function login(InternalRequest $request, CheckoutContext $context): string
    {
        if (empty($request->optionalPost('username')) || empty($request->optionalPost('password'))) {
            throw new BadCredentialsException();
        }

        try {
            $user = $this->getCustomerByLogin(
                $request->requirePost('username'),
                $request->requirePost('password'),
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
    }

    private function validateRegistrationRequest(InternalRequest $request, CheckoutContext $context): void
    {
        if ($request->getParam('guest')) {
            return;
        }

        $customers = $this->getCustomersByEmail($request->requirePost('email'), $context, false);
        if ($customers->getTotal() > 0) {
            throw new CustomerAccountExistsException($request->requirePost('email'));
        }
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
     * @throws \Shopware\Core\Checkout\Exception\AddressNotFoundException
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

    private function getBirthday(InternalRequest $request): ?\DateTime
    {
        $birthdayDay = $request->optionalPost('birthdayDay');
        $birthdayMonth = $request->optionalPost('birthdayMonth');
        $birthdayYear = $request->optionalPost('birthdayYear');

        if (!$birthdayDay ||
            !$birthdayMonth ||
            !$birthdayYear) {
            return null;
        }

        return new \DateTime(sprintf(
            '%s-%s-%s',
            $birthdayYear,
            $birthdayMonth,
            $birthdayDay
        ));
    }
}

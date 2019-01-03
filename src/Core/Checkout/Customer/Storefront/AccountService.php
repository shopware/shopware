<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Storefront;

use Shopware\Core\Checkout\Cart\Exception\CustomerAccountExistsException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Exception\InvalidUuidException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Storefront\Action\AccountAddress\AddressSaveRequest;
use Shopware\Storefront\Action\AccountEmail\EmailSaveRequest;
use Shopware\Storefront\Action\AccountLogin\LoginRequest;
use Shopware\Storefront\Action\AccountPassword\PasswordSaveRequest;
use Shopware\Storefront\Action\AccountProfile\ProfileSaveRequest;
use Shopware\Storefront\Action\AccountRegistration\RegistrationRequest;
use Shopware\Storefront\Exception\AccountAddress\AddressNotFoundException;
use Shopware\Storefront\Exception\AccountLogin\CustomerNotFoundException;
use Shopware\Storefront\Framework\Exception\BadCredentialsException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AccountService
{
    /**
     * @var RepositoryInterface
     */
    private $countryRepository;

    /**
     * @var RepositoryInterface
     */
    private $customerAddressRepository;

    /**
     * @var RepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CheckoutContextPersister
     */
    private $contextPersister;

    public function __construct(
        RepositoryInterface $countryRepository,
        RepositoryInterface $customerAddressRepository,
        RepositoryInterface $customerRepository,
        CheckoutContextPersister $contextPersister
    ) {
        $this->countryRepository = $countryRepository;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->customerRepository = $customerRepository;
        $this->contextPersister = $contextPersister;
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
     * @throws \Shopware\Storefront\Exception\AccountLogin\CustomerNotFoundException
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

    public function saveProfile(ProfileSaveRequest $profileSaveRequest, CheckoutContext $context): void
    {
        $data = [
            'id' => $context->getCustomer()->getId(),
            'firstName' => $profileSaveRequest->getFirstName(),
            'lastName' => $profileSaveRequest->getLastName(),
            'title' => $profileSaveRequest->getTitle(),
            'salutation' => $profileSaveRequest->getSalutation(),
            'birthday' => $profileSaveRequest->getBirthday(),
        ];

        foreach ($profileSaveRequest->getExtensions() as $key => $value) {
            $data[$key] = $value;
        }
        $data = array_filter($data);

        $this->customerRepository->update([$data], $context->getContext());
    }

    public function savePassword(PasswordSaveRequest $passwordSaveRequest, CheckoutContext $context): void
    {
        $data = [
            'id' => $context->getCustomer()->getId(),
            'password' => $passwordSaveRequest->getPassword(),
        ];

        foreach ($passwordSaveRequest->getExtensions() as $key => $value) {
            $data[$key] = $value;
        }
        $data = array_filter($data);

        $this->customerRepository->update([$data], $context->getContext());
    }

    public function saveEmail(EmailSaveRequest $emailSaveRequest, CheckoutContext $context): void
    {
        $data = [
            'id' => $context->getCustomer()->getId(),
            'email' => $emailSaveRequest->getEmail(),
        ];

        foreach ($emailSaveRequest->getExtensions() as $key => $value) {
            $data[$key] = $value;
        }
        $data = array_filter($data);

        $this->customerRepository->update([$data], $context->getContext());
    }

    /**
     * @throws \Shopware\Storefront\Exception\AccountAddress\AddressNotFoundException
     * @throws InvalidUuidException
     */
    public function getAddressById(string $addressId, CheckoutContext $context): CustomerAddressEntity
    {
        return $this->validateAddressId($addressId, $context);
    }

    public function getCountryList(CheckoutContext $context): array
    {
        $criteria = new ReadCriteria([]);
        $criteria->addFilter(new EqualsFilter('country.active', true));

        /** @var CountryCollection $countries */
        $countries = $this->countryRepository->read($criteria, $context->getContext());

        $countries->sortCountryAndStates();

        return $countries->getElements();
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
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws \Shopware\Storefront\Exception\AccountAddress\AddressNotFoundException     */
    public function saveAddress(AddressSaveRequest $addressSaveRequest, CheckoutContext $context): string
    {
        $this->validateCustomer($context);

        if (!$addressSaveRequest->getId()) {
            $addressSaveRequest->setId(Uuid::uuid4()->getHex());
        } else {
            $this->validateAddressId($addressSaveRequest->getId(), $context)->getId();
        }

        $data = [
            'id' => $addressSaveRequest->getId(),
            'customerId' => $context->getCustomer()->getId(),
            'salutation' => $addressSaveRequest->getSalutation(),
            'firstName' => $addressSaveRequest->getFirstName(),
            'lastName' => $addressSaveRequest->getLastName(),
            'street' => $addressSaveRequest->getStreet(),
            'city' => $addressSaveRequest->getCity(),
            'zipcode' => $addressSaveRequest->getZipcode(),
            'countryId' => $addressSaveRequest->getCountryId(),
            'countryStateId' => $addressSaveRequest->getCountryStateId(),
            'company' => $addressSaveRequest->getCompany(),
            'department' => $addressSaveRequest->getDepartment(),
            'title' => $addressSaveRequest->getTitle(),
            'vatId' => $addressSaveRequest->getVatId(),
            'additionalAddressLine1' => $addressSaveRequest->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $addressSaveRequest->getAdditionalAddressLine2(),
        ];

        /* TODO pretty dangerous since extensions could not only overwrite all properties above
        *  but also could write all sub entities.
        */
        foreach ($addressSaveRequest->getExtensions() as $key => $value) {
            $data[$key] = $value;
        }
        $data = array_filter($data);

        $this->customerAddressRepository->upsert([$data], $context->getContext());

        return $addressSaveRequest->getId();
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws \Shopware\Storefront\Exception\AccountAddress\AddressNotFoundException
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
     * @throws \Shopware\Storefront\Exception\AccountAddress\AddressNotFoundException
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
     * @throws \Shopware\Storefront\Exception\AccountAddress\AddressNotFoundException
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

    public function createNewCustomer(RegistrationRequest $registrationRequest, CheckoutContext $context): string
    {
        $this->validateRegistrationRequest($registrationRequest, $context);

        $customerId = Uuid::uuid4()->getHex();
        $billingAddressId = Uuid::uuid4()->getHex();

        $addresses = [];

        $addresses[] = array_filter([
            'id' => $billingAddressId,
            'customerId' => $customerId,
            'countryId' => $registrationRequest->getBillingCountry(),
            'salutation' => $registrationRequest->getSalutation(),
            'firstName' => $registrationRequest->getFirstName(),
            'lastName' => $registrationRequest->getLastName(),
            'street' => $registrationRequest->getBillingStreet(),
            'zipcode' => $registrationRequest->getBillingZipcode(),
            'city' => $registrationRequest->getBillingCity(),
            'phoneNumber' => $registrationRequest->getBillingPhone(),
            'vatId' => $registrationRequest->getBillingVatId(),
            'additionalAddressLine1' => $registrationRequest->getBillingAdditionalAddressLine1(),
            'additionalAddressLine2' => $registrationRequest->getBillingAdditionalAddressLine2(),
            'countryStateId' => $registrationRequest->getBillingCountryState(),
        ]);

        if ($registrationRequest->hasDifferentShippingAddress()) {
            $shippingAddressId = Uuid::uuid4()->getHex();
            $addresses[] = array_filter([
                'id' => $shippingAddressId,
                'customerId' => $customerId,
                'countryId' => $registrationRequest->getShippingCountry(),
                'salutation' => $registrationRequest->getShippingSalutation(),
                'firstName' => $registrationRequest->getShippingFirstName(),
                'lastName' => $registrationRequest->getShippingLastName(),
                'street' => $registrationRequest->getShippingStreet(),
                'zipcode' => $registrationRequest->getShippingZipcode(),
                'city' => $registrationRequest->getShippingCity(),
                'phoneNumber' => $registrationRequest->getShippingPhone(),
                'additionalAddressLine1' => $registrationRequest->getShippingAdditionalAddressLine1(),
                'additionalAddressLine2' => $registrationRequest->getShippingAdditionalAddressLine2(),
                'countryStateId' => $registrationRequest->getShippingCountryState(),
            ]);
        }

        // todo implement customer number generator
        $data = [
            'id' => $customerId,
            'salesChannelId' => $context->getSalesChannel()->getId(),
            'groupId' => $context->getCurrentCustomerGroup()->getId(),
            'defaultPaymentMethodId' => $context->getPaymentMethod()->getId(),
            'customerNumber' => '123',
            'salutation' => $registrationRequest->getSalutation(),
            'firstName' => $registrationRequest->getFirstName(),
            'lastName' => $registrationRequest->getLastName(),
            'email' => $registrationRequest->getEmail(),
            'title' => $registrationRequest->getTitle(),
            'encoder' => 'bcrypt',
            'active' => true,
            'defaultBillingAddressId' => $billingAddressId,
            'defaultShippingAddressId' => $shippingAddressId ?? $billingAddressId,
            'addresses' => $addresses,
            'birthday' => $registrationRequest->getBirthday(),
            'guest' => $registrationRequest->getGuest(),
        ];

        if (!$registrationRequest->getGuest()) {
            $data['password'] = $registrationRequest->getPassword();
        }

        $data = array_filter($data, function ($element) {
            return $element !== null;
        });

        $this->customerRepository->create([$data], $context->getContext());

        return $customerId;
    }

    /**
     * @throws BadCredentialsException
     * @throws UnauthorizedHttpException
     */
    public function login(LoginRequest $loginRequest, CheckoutContext $context): string
    {
        if (empty($loginRequest->getUsername()) || empty($loginRequest->getPassword())) {
            throw new BadCredentialsException();
        }

        try {
            $user = $this->getCustomerByLogin(
                $loginRequest->getUsername(),
                $loginRequest->getPassword(),
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

    private function validateRegistrationRequest(RegistrationRequest $registrationRequest, CheckoutContext $context): void
    {
        if ($registrationRequest->getGuest()) {
            return;
        }

        $customers = $this->getCustomersByEmail($registrationRequest->getEmail(), $context, false);
        if ($customers->getTotal() > 0) {
            throw new CustomerAccountExistsException($registrationRequest->getEmail());
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
     * @throws \Shopware\Storefront\Exception\AccountAddress\AddressNotFoundException
     * @throws InvalidUuidException
     */
    private function validateAddressId(string $addressId, CheckoutContext $context): CustomerAddressEntity
    {
        if (!Uuid::isValid($addressId)) {
            throw new InvalidUuidException($addressId);
        }

        /** @var CustomerAddressEntity|null $address */
        $address = $this->customerAddressRepository->read(new ReadCriteria([$addressId]), $context->getContext())->get($addressId);

        if (!$address || $address->getCustomerId() !== $context->getCustomer()->getId()) {
            throw new AddressNotFoundException($addressId);
        }

        return $address;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Application\Context\Util\StorefrontContextPersister;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressRepository;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Struct\CustomerAddressBasicStruct;
use Shopware\Core\Checkout\Customer\CustomerRepository;
use Shopware\Core\Checkout\Customer\Struct\CustomerBasicStruct;
use Shopware\Core\Checkout\Order\Exception\NotLoggedInCustomerException;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Framework\Routing\Firewall\CustomerUser;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Storefront\Exception\AddressNotFoundHttpException;
use Shopware\Storefront\Exception\CustomerNotFoundException;
use Shopware\Core\System\Country\CountryRepository;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class AccountService
{
    /**
     * @var \Shopware\Core\System\Country\CountryRepository
     */
    private $countryRepository;

    /**
     * @var \Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressRepository
     */
    private $customerAddressRepository;

    /**
     * @var \Shopware\Core\Checkout\Customer\CustomerRepository
     */
    private $customerRepository;

    /**
     * @var AuthenticationManagerInterface
     */
    private $authenticationManager;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var StorefrontContextPersister
     */
    private $contextPersister;

    public function __construct(
        CountryRepository $countryRepository,
        CustomerAddressRepository $customerAddressRepository,
        CustomerRepository $customerRepository,
        AuthenticationManagerInterface $authenticationManager,
        TokenStorageInterface $tokenStorage,
        StorefrontContextPersister $contextPersister
    ) {
        $this->countryRepository = $countryRepository;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->customerRepository = $customerRepository;
        $this->authenticationManager = $authenticationManager;
        $this->tokenStorage = $tokenStorage;
        $this->contextPersister = $contextPersister;
    }

    public function getCustomerByLogin(string $email, string $password, CheckoutContext $context): CustomerBasicStruct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('customer.email', $email));

        $customers = $this->customerRepository->search($criteria, $context->getContext());

        if ($customers->count() === 0) {
            throw new CustomerNotFoundException($email);
        }

        /** @var CustomerBasicStruct $customer */
        $customer = $customers->first();

        if (!password_verify($password, $customer->getPassword())) {
            throw new BadCredentialsException();
        }

        return $customer;
    }

    /**
     * @throws \Shopware\Core\Checkout\Order\Exception\NotLoggedInCustomerException
     */
    public function getCustomerByContext(CheckoutContext $context): CustomerBasicStruct
    {
        $this->validateCustomer($context);

        return $context->getCustomer();
    }

    public function saveProfile(ProfileSaveRequest $profileSaveRequest, CheckoutContext $context)
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

    public function changePassword(PasswordSaveRequest $passwordSaveRequest, CheckoutContext $context)
    {
        $data = [
            'id' => $context->getCustomer()->getId(),
            'password' => password_hash($passwordSaveRequest->getPassword(), PASSWORD_BCRYPT, ['cost' => 13]),
            'encoder' => 'bcrypt',
        ];

        foreach ($passwordSaveRequest->getExtensions() as $key => $value) {
            $data[$key] = $value;
        }
        $data = array_filter($data);

        $this->customerRepository->update([$data], $context->getContext());
    }

    public function changeEmail(EmailSaveRequest $emailSaveRequest, CheckoutContext $context)
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

    public function getAddressById(string $addressId, CheckoutContext $context): CustomerAddressBasicStruct
    {
        return $this->validateAddressId($addressId, $context);
    }

    public function getCountryList(CheckoutContext $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('country.active', true));
        $countries = $this->countryRepository->readDetail(
            $this->countryRepository->searchIds($criteria, $context->getContext())->getIds(),
            $context->getContext()
        );
        $countries->sortCountryAndStates();

        return $countries->getElements();
    }

    /**
     * @throws NotLoggedInCustomerException
     */
    public function getAddressesByCustomer(CheckoutContext $context): array
    {
        $this->validateCustomer($context);
        $customer = $context->getCustomer();
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('customer_address.customerId', $context->getCustomer()->getId()));

        $addresses = $this->customerAddressRepository->search($criteria, $context->getContext());

        return $addresses->sortByDefaultAddress($customer)->getElements();
    }

    /**
     * @throws \Shopware\Core\Checkout\Order\Exception\NotLoggedInCustomerException
     */
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
     * @throws \Shopware\Core\Checkout\Order\Exception\NotLoggedInCustomerException
     */
    public function deleteAddress(string $addressId, CheckoutContext $context)
    {
        $this->validateCustomer($context);
        $this->validateAddressId($addressId, $context);
        $this->customerAddressRepository->delete([['id' => $addressId]], $context->getContext());
    }

    /**
     * @throws \Shopware\Core\Checkout\Order\Exception\NotLoggedInCustomerException
     */
    public function setDefaultBillingAddress(string $addressId, CheckoutContext $context)
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
     * @throws \Shopware\Core\Checkout\Order\Exception\NotLoggedInCustomerException
     */
    public function setDefaultShippingAddress(string $addressId, CheckoutContext $context)
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
                'additionalAddressLine1' => $registrationRequest->getShippingAdditionalAddressLine1(),
                'additionalAddressLine2' => $registrationRequest->getShippingAdditionalAddressLine2(),
                'countryStateId' => $registrationRequest->getShippingCountryState(),
            ]);
        }

        // todo implement customer number generator
        $data = [
            'id' => $customerId,
            'touchpointId' => $context->getTouchpoint()->getId(),
            'customerGroupId' => $context->getCurrentCustomerGroup()->getId(),
            'defaultPaymentMethodId' => $context->getPaymentMethod()->getId(),
            'applicationId' => $context->getApplication()->getId(),
            'number' => '123',
            'salutation' => $registrationRequest->getSalutation(),
            'firstName' => $registrationRequest->getFirstName(),
            'lastName' => $registrationRequest->getLastName(),
            'password' => password_hash($registrationRequest->getPassword(), PASSWORD_BCRYPT, ['cost' => 13]),
            'email' => $registrationRequest->getEmail(),
            'title' => $registrationRequest->getTitle(),
            'encoder' => 'bcrypt',
            'active' => true,
            'defaultBillingAddressId' => $billingAddressId,
            'defaultShippingAddressId' => $shippingAddressId ?? $billingAddressId,
            'addresses' => $addresses,
            'birthday' => $registrationRequest->getBirthday(),
        ];

        $data = array_filter($data);
        $this->customerRepository->create([$data], $context->getContext());

        return $customerId;
    }

    /**
     * @throws \Shopware\Core\Checkout\Order\Exception\NotLoggedInCustomerException
     */
    private function validateCustomer(CheckoutContext $context)
    {
        if (!$context->getCustomer()) {
            throw new NotLoggedInCustomerException();
        }
    }

    private function validateAddressId(string $addressId, CheckoutContext $context): CustomerAddressBasicStruct
    {
        $addresses = $this->customerAddressRepository->readBasic([$addressId], $context->getContext());
        $address = $addresses->get($addressId);

        if (!$address) {
            throw new AddressNotFoundHttpException($addressId);
        }

        if ($address->getCustomerId() !== $context->getCustomer()->getId()) {
            throw new AddressNotFoundHttpException($addressId);
        }

        return $address;
    }
}

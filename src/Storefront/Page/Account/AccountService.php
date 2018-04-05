<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Api\Country\Repository\CountryRepository;
use Shopware\Api\Country\Repository\CountryStateRepository;
use Shopware\Api\Customer\Repository\CustomerAddressRepository;
use Shopware\Api\Customer\Repository\CustomerRepository;
use Shopware\Api\Customer\Struct\CustomerAddressBasicStruct;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\CartBridge\Exception\NotLoggedInCustomerException;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\Uuid;
use Shopware\StorefrontApi\Context\StorefrontContextPersister;
use Shopware\StorefrontApi\Exception\AddressNotFoundHttpException;
use Shopware\StorefrontApi\Firewall\CustomerUser;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class AccountService
{
    /**
     * @var CountryRepository
     */
    private $countryRepository;

    /**
     * @var CountryStateRepository
     */
    private $countryStateRepository;

    /**
     * @var CustomerAddressRepository
     */
    private $customerAddressRepository;

    /**
     * @var CustomerRepository
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

    public function changeProfile(array $data, StorefrontContext $context)
    {
        $data = [
            'id' => $context->getCustomer()->getId(),
            'firstName' => $data['firstname'],
            'lastName' => $data['lastname'],
            'title' => $data['title'] ?? null,
            'salutation' => $data['salutation'] ?? null,
            'birthday' => array_key_exists('birthday', $data) ? $this->formatBirthday($data['birthday']) : null,
        ];

        $data = array_filter($data);
        $this->customerRepository->update([$data], $context->getShopContext());
    }

    public function changePassword(string $password, StorefrontContext $context)
    {
        $data = [
            'id' => $context->getCustomer()->getId(),
            'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 13]),
            'encoder' => 'bcrypt',
        ];
        $this->customerRepository->update([$data], $context->getShopContext());
    }

    public function changeEmail(string $email, StorefrontContext $context)
    {
        $data = [
            'id' => $context->getCustomer()->getId(),
            'email' => $email,
        ];
        $this->customerRepository->update([$data], $context->getShopContext());
    }

    public function getAddressById(string $addressId, StorefrontContext $context): CustomerAddressBasicStruct
    {
        return $this->validateAddressId($addressId, $context);
    }

    public function getCountryList(StorefrontContext $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('country.active', true));
        $countries = $this->countryRepository->readDetail(
            $this->countryRepository->searchIds($criteria, $context->getShopContext())->getIds(),
            $context->getShopContext()
        );
        $countries->sortCountryAndStates();

        return $countries->getElements();
    }

    /**
     * @throws NotLoggedInCustomerException
     */
    public function getAddressesByCustomer(StorefrontContext $context): array
    {
        $this->validateCustomer($context);
        $customer = $context->getCustomer();
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('customer_address.customerId', $context->getCustomer()->getId()));

        $addresses = $this->customerAddressRepository->search($criteria, $context->getShopContext());

        return $addresses->sortByDefaultAddress($customer)->getElements();
    }

    /**
     * @throws NotLoggedInCustomerException
     */
    public function saveAddress(array $formData, StorefrontContext $context): string
    {
        $this->validateCustomer($context);
        if (!array_key_exists('addressId', $formData)) {
            $id = Uuid::optimize(Uuid::uuid4()->toString());
        } else {
            $id = $this->validateAddressId($formData['addressId'], $context)->getId();
        }

        $data = [
            'id' => $id,
            'customerId' => $context->getCustomer()->getId(),
            'salutation' => $formData['salutation'],
            'firstName' => $formData['firstname'],
            'lastName' => $formData['lastname'],
            'street' => $formData['street'],
            'city' => $formData['city'],
            'zipcode' => $formData['zipcode'],
            'countryId' => $formData['country'],
            'countryStateId' => $formData['state'] ?? null,
            'company' => $formData['company'] ?? null,
            'department' => $formData['department'] ?? null,
            'title' => $formData['title'] ?? null,
            'vatId' => $formData['vatId'] ?? null,
            'additionalAddressLine1' => $formData['additionalAddressLine1'] ?? null,
            'additionalAddressLine2' => $formData['additionalAddressLine2'] ?? null,
        ];
        $data = array_filter($data);

        $this->customerAddressRepository->upsert([$data], $context->getShopContext());

        return $id;
    }

    /**
     * @throws NotLoggedInCustomerException
     */
    public function deleteAddress(string $addressId, StorefrontContext $context)
    {
        $this->validateCustomer($context);
        $this->validateAddressId($addressId, $context);
        $this->customerAddressRepository->delete([['id' => $addressId]], $context->getShopContext());
    }

    /**
     * @throws NotLoggedInCustomerException
     */
    public function setDefaultBillingAddress(string $addressId, StorefrontContext $context)
    {
        $this->validateCustomer($context);
        $this->validateAddressId($addressId, $context);

        $data = [
            'id' => $context->getCustomer()->getId(),
            'defaultBillingAddressId' => $addressId,
        ];
        $this->customerRepository->update([$data], $context->getShopContext());
    }

    /**
     * @throws NotLoggedInCustomerException
     */
    public function setDefaultShippingAddress(string $addressId, StorefrontContext $context)
    {
        $this->validateCustomer($context);
        $this->validateAddressId($addressId, $context);

        $data = [
            'id' => $context->getCustomer()->getId(),
            'defaultShippingAddressId' => $addressId,
        ];
        $this->customerRepository->update([$data], $context->getShopContext());
    }

    public function createNewCustomer(array $formData, StorefrontContext $context)
    {
        $customerId = Uuid::uuid4()->toString();
        $billingAddressId = Uuid::uuid4()->toString();

        $personal = $formData['personal'];
        $billing = $formData['billing'];
        $shipping = $formData['shipping'];

        $addresses = [];

        $addresses[] = array_filter([
            'id' => $billingAddressId,
            'customerId' => $customerId,
            'countryId' => $billing['country'],
            'salutation' => $billing['salutation'] ?? $personal['salutation'],
            'firstName' => $billing['firstname'] ?? $personal['firstname'],
            'lastName' => $billing['lastname'] ?? $personal['lastname'],
            'street' => $billing['street'],
            'zipcode' => $billing['zipcode'],
            'city' => $billing['city'],
            'phoneNumber' => $billing['phone'] ?? null,
            'vatId' => $billing['vatId'] ?? null,
            'additionalAddressLine1' => $billing['additionalAddressLine1'] ?? null,
            'additionalAddressLine2' => $billing['additionalAddressLine2'] ?? null,
            'countryStateId' => $billing['country_state'] ?? null,
        ]);

        if (array_key_exists('shippingAddress', $billing) && $billing['shippingAddress'] === '1') {
            $shippingAddressId = Uuid::uuid4()->toString();
            $addresses[] = array_filter([
                'id' => $shippingAddressId,
                'customerId' => $customerId,
                'countryId' => $shipping['country'],
                'salutation' => $shipping['salutation'] ?? $personal['salutation'],
                'firstName' => $shipping['firstname'] ?? $personal['firstname'],
                'lastName' => $shipping['lastname'] ?? $personal['lastname'],
                'street' => $shipping['street'],
                'zipcode' => $shipping['zipcode'],
                'city' => $shipping['city'],
                'countryStateId' => $shipping['country_state'] ?? null,
            ]);
        }

        // todo implement customer number generator
        $data = [
            'id' => $customerId,
            'customerGroupId' => $context->getShop()->getCustomerGroupId(),
            'defaultPaymentMethodId' => $context->getShop()->getPaymentMethodId(),
            'groupId' => $context->getShop()->getCustomerGroupId(),
            'shopId' => $context->getShop()->getId(),
            'number' => '123',
            'salutation' => $personal['salutation'],
            'firstName' => $personal['firstname'],
            'lastName' => $personal['lastname'],
            'password' => password_hash($personal['password'], PASSWORD_BCRYPT, ['cost' => 13]),
            'email' => $personal['email'],
            'title' => $personal['title'] ?? null,
            'encoder' => 'bcrypt',
            'active' => true,
            'defaultBillingAddressId' => $billingAddressId,
            'defaultShippingAddressId' => $shippingAddressId ?? $billingAddressId,
            'addresses' => $addresses,
            'birthday' => sprintf('%s-%s-%s',
                    (int) $personal['birthday']['year'],
                    (int) $personal['birthday']['month'],
                    (int) $personal['birthday']['day']
                ) ?? null,
        ];

        $data = array_filter($data);
        $this->customerRepository->create([$data], $context->getShopContext());
    }

    public function loginCustomer(string $username, string $password, StorefrontContext $context)
    {
        $unauthenticatedToken = new UsernamePasswordToken($username, $password, 'storefront');

        $authenticatedToken = $this->authenticationManager->authenticate($unauthenticatedToken);

        $this->tokenStorage->setToken($authenticatedToken);

        /** @var CustomerUser $user */
        $user = $authenticatedToken->getUser();

        $this->contextPersister->save(
            $context->getToken(),
            [
                'customerId' => $user->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ]
        );
    }

    /**
     * @throws NotLoggedInCustomerException
     */
    private function validateCustomer(StorefrontContext $context)
    {
        if (!$context->getCustomer()) {
            throw new NotLoggedInCustomerException();
        }
    }

    private function validateAddressId(string $addressId, StorefrontContext $context): CustomerAddressBasicStruct
    {
        $addresses = $this->customerAddressRepository->readBasic([$addressId], $context->getShopContext());
        $address = $addresses->get($addressId);

        if (!$address) {
            throw new AddressNotFoundHttpException($addressId);
        }

        if ($address->getCustomerId() !== $context->getCustomer()->getId()) {
            throw new AddressNotFoundHttpException($addressId);
        }

        return $address;
    }

    private function formatBirthday(array $data): ?string
    {
        if (!array_key_exists('year', $data) or
            !array_key_exists('month', $data) or
            !array_key_exists('day', $data)) {
            return null;
        }

        return sprintf(
            '%s-%s-%s',
            (int) $data['year'],
            (int) $data['month'],
            (int) $data['day']
        );
    }
}

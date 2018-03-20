<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Api\Country\Repository\CountryRepository;
use Shopware\Api\Country\Repository\CountryStateRepository;
use Shopware\Api\Country\Struct\CountrySearchResult;
use Shopware\Api\Country\Struct\CountryStateSearchResult;
use Shopware\Api\Customer\Repository\CustomerAddressRepository;
use Shopware\Api\Customer\Repository\CustomerRepository;
use Shopware\Api\Customer\Struct\CustomerAddressBasicStruct;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Entity\Search\Sorting\FieldSorting;
use Shopware\CartBridge\Exception\NotLoggedInCustomerException;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\Uuid;
use Shopware\StorefrontApi\Exception\AddressNotFoundHttpException;

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

    public function __construct(
        CountryRepository $countryRepository,
        CountryStateRepository $countryStateRepository,
        CustomerAddressRepository $customerAddressRepository,
        CustomerRepository $customerRepository
    ) {
        $this->countryRepository = $countryRepository;
        $this->countryStateRepository = $countryStateRepository;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->customerRepository = $customerRepository;
    }

    public function changeProfile(array $data, StorefrontContext $context)
    {
        $data = [
            'id' => $context->getCustomer()->getId(),
            'firstName' => $data['firstname'],
            'lastName' => $data['lastname'],
            'title' => $data['title'] ?? null,
            'salutation' => $data['salutation'] ?? null,
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
        $countries = $this->getCountries($context);
        $states = $this->getCountryStates($context, $countries->getIds());

        foreach ($countries as $country) {
            $country->addExtension('states', $states->filterByCountryId($country->getId()));
        }

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
            'phoneNumber' => $formData['phone'] ?? null,
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

    private function getCountries(StorefrontContext $context): CountrySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('country.active', true));
        $criteria->addSortings([
            new FieldSorting('country.position', FieldSorting::DESCENDING),
            new FieldSorting('country.name'),
        ]);

        return $this->countryRepository->search($criteria, $context->getShopContext());
    }

    private function getCountryStates(StorefrontContext $context, array $countryIds): CountryStateSearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('country_state.countryId', $countryIds));
        $criteria->addSortings([
            new FieldSorting('country_state.position', FieldSorting::DESCENDING),
            new FieldSorting('country_state.name'),
        ]);

        return $this->countryStateRepository->search($criteria, $context->getShopContext());
    }
}

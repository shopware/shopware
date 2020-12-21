<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\CannotDeleteDefaultAddressException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @deprecated tag:v6.4.0 - use ListAddressRoute / UpsertAddressRoute
 */
class AddressService
{
    /**
     * @var AbstractListAddressRoute
     */
    private $listAddressRoute;

    /**
     * @var AbstractUpsertAddressRoute
     */
    private $updateAddressRoute;

    /**
     * @var AbstractDeleteAddressRoute
     */
    private $deleteAddressRoute;

    public function __construct(
        AbstractListAddressRoute $listAddressRoute,
        AbstractUpsertAddressRoute $updateAddressRoute,
        AbstractDeleteAddressRoute $deleteAddressRoute
    ) {
        $this->listAddressRoute = $listAddressRoute;
        $this->updateAddressRoute = $updateAddressRoute;
        $this->deleteAddressRoute = $deleteAddressRoute;
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
        $customer = $this->validateCustomerIsLoggedIn($context);
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customer_address.customerId', $customer->getId()));

        $addresses = $this->listAddressRoute->load($criteria, $context, $customer)->getAddressCollection();

        return $addresses->sortByDefaultAddress($customer)->getElements();
    }

    /**
     * @throws AddressNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws ConstraintViolationException
     */
    public function upsert(DataBag $data, SalesChannelContext $context, ?CustomerEntity $customer = null): string
    {
        /* @deprecated tag:v6.4.0 - Remove this block, parameter $customer will be mandatory */
        if ($context->getCustomer() === null) {
            throw new CustomerNotLoggedInException();
        }

        /* @deprecated tag:v6.4.0 - Parameter $customer will be mandatory when using with @LoginRequired() */
        if (!$customer) {
            $customer = $context->getCustomer();
        }

        return $this->updateAddressRoute
            ->upsert($data->get('id'), $data->toRequestDataBag(), $context, $customer)
            ->getAddress()
            ->getId();
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     * @throws CannotDeleteDefaultAddressException
     */
    public function delete(string $addressId, SalesChannelContext $context, ?CustomerEntity $customer = null): void
    {
        /* @deprecated tag:v6.4.0 - Remove this block, parameter $customer will be mandatory */
        if ($context->getCustomer() === null) {
            throw new CustomerNotLoggedInException();
        }

        /* @deprecated tag:v6.4.0 - Parameter $customer will be mandatory when using with @LoginRequired() */
        if (!$customer) {
            $customer = $context->getCustomer();
        }

        $this->deleteAddressRoute->delete($addressId, $context, $customer);
    }

    /**
     * @throws CustomerNotLoggedInException
     */
    private function validateCustomerIsLoggedIn(SalesChannelContext $context): CustomerEntity
    {
        if ($context->getCustomer() === null) {
            throw new CustomerNotLoggedInException();
        }

        return $context->getCustomer();
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

        $customer = $this->validateCustomerIsLoggedIn($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $addressId));
        $criteria->addFilter(new EqualsFilter('customerId', $customer->getId()));

        $address = $this->listAddressRoute->load($criteria, $context, $customer)->getAddressCollection()->get($addressId);

        if (!$address) {
            throw new AddressNotFoundException($addressId);
        }

        return $address;
    }
}

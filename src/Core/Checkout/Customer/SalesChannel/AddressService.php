<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
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
        $this->validateCustomerIsLoggedIn($context);
        $customer = $context->getCustomer();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customer_address.customerId', $context->getCustomer()->getId()));

        $addresses = $this->listAddressRoute->load($criteria, $context)->getAddressCollection();

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
        return $this->updateAddressRoute
            ->upsert($data->get('id'), $data->toRequestDataBag(), $context)
            ->getAddress()
            ->getId();
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     * @throws CannotDeleteDefaultAddressException
     */
    public function delete(string $addressId, SalesChannelContext $context): void
    {
        $this->deleteAddressRoute->delete($addressId, $context);
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

        $address = $this->listAddressRoute->load($criteria, $context)->getAddressCollection()->get($addressId);

        if (!$address) {
            throw new AddressNotFoundException($addressId);
        }

        return $address;
    }
}

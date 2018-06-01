<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Checkout\CustomerContext;
use Shopware\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressRepository;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\Query\TermQuery;

class CustomerAddressPageLoader
{
    /**
     * @var \Shopware\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressRepository
     */
    private $customerAddressRepository;

    public function __construct(CustomerAddressRepository $customerAddressRepository)
    {
        $this->customerAddressRepository = $customerAddressRepository;
    }

    public function load(CustomerContext $context): CustomerAddressPageStruct
    {
        $criteria = $this->createCriteria($context->getCustomer()->getId());
        $addresses = $this->customerAddressRepository->search($criteria, $context->getApplicationContext());

        return new CustomerAddressPageStruct($addresses->sortByDefaultAddress($context->getCustomer()));
    }

    private function createCriteria(string $customerId): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('customer_address.customerId', $customerId));

        return $criteria;
    }
}

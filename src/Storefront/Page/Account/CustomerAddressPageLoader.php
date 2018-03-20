<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Api\Customer\Collection\CustomerAddressBasicCollection;
use Shopware\Api\Customer\Repository\CustomerAddressRepository;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Context\Struct\StorefrontContext;

class CustomerAddressPageLoader
{
    /**
     * @var CustomerAddressRepository
     */
    private $customerAddressRepository;

    public function __construct(CustomerAddressRepository $customerAddressRepository)
    {
        $this->customerAddressRepository = $customerAddressRepository;
    }

    public function load(StorefrontContext $context): CustomerAddressPageStruct
    {
        $criteria = $this->createCriteria($context->getCustomer()->getId());
        $addresses = $this->customerAddressRepository->search($criteria, $context->getShopContext());

        return new CustomerAddressPageStruct($addresses->sortByDefaultAddress($context->getCustomer()));
    }

    private function createCriteria(string $customerId): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('customer_address.customerId', $customerId));

        return $criteria;
    }
}

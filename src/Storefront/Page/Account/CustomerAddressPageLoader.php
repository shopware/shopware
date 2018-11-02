<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\TermQuery;

class CustomerAddressPageLoader
{
    /**
     * @var RepositoryInterface
     */
    private $customerAddressRepository;

    public function __construct(RepositoryInterface $customerAddressRepository)
    {
        $this->customerAddressRepository = $customerAddressRepository;
    }

    public function load(CheckoutContext $context): CustomerAddressPageStruct
    {
        $criteria = $this->createCriteria($context->getCustomer()->getId());

        /** @var CustomerAddressCollection $addresses */
        $addresses = $this->customerAddressRepository->search($criteria, $context->getContext())->getEntities();

        return new CustomerAddressPageStruct($addresses->sortByDefaultAddress($context->getCustomer()));
    }

    private function createCriteria(string $customerId): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('customer_address.customerId', $customerId));

        return $criteria;
    }
}

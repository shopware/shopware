<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountAddress;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class AccountAddressPageletLoader
{
    /**
     * @var EntityRepositoryInterface
     */
    private $customerAddressRepository;

    public function __construct(EntityRepositoryInterface $customerAddressRepository)
    {
        $this->customerAddressRepository = $customerAddressRepository;
    }

    public function load(AccountAddressPageletRequest $request, CheckoutContext $context): AccountAddressPageletStruct
    {
        $criteria = $this->createCriteria($context->getCustomer()->getId());

        /** @var CustomerAddressCollection $addresses */
        $addresses = $this->customerAddressRepository->search($criteria, $context->getContext())->getEntities();
        $page = new AccountAddressPageletStruct();
        $page->setAddresses($addresses->sortByDefaultAddress($context->getCustomer()));

        return $page;
    }

    private function createCriteria(string $customerId): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customer_address.customerId', $customerId));

        return $criteria;
    }
}

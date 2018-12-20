<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\PageLoader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Account\Page\CustomerAddressPageletStruct;
use Shopware\Storefront\Framework\Page\PageRequest;
use Shopware\Storefront\Framework\PageLoader\PageLoader;

class AccountAddressPageletLoader implements PageLoader
{
    /**
     * @var RepositoryInterface
     */
    private $customerAddressRepository;

    public function __construct(RepositoryInterface $customerAddressRepository)
    {
        $this->customerAddressRepository = $customerAddressRepository;
    }

    public function load(PageRequest $request, CheckoutContext $context): CustomerAddressPageletStruct
    {
        $criteria = $this->createCriteria($context->getCustomer()->getId());

        /** @var CustomerAddressCollection $addresses */
        $addresses = $this->customerAddressRepository->search($criteria, $context->getContext())->getEntities();

        return new CustomerAddressPageletStruct($addresses->sortByDefaultAddress($context->getCustomer()));
    }

    private function createCriteria(string $customerId): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customer_address.customerId', $customerId));

        return $criteria;
    }
}

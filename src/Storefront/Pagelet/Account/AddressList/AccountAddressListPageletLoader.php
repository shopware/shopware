<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Account\AddressList;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountAddressListPageletLoader implements PageLoaderInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $customerAddressRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(EntityRepositoryInterface $customerAddressRepository, EventDispatcherInterface $eventDispatcher)
    {
        $this->customerAddressRepository = $customerAddressRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(Request $request, SalesChannelContext $context): StorefrontSearchResult
    {
        $customer = $context->getCustomer();
        if ($customer === null) {
            throw new CustomerNotLoggedInException();
        }

        $criteria = $this->createCriteria($customer->getId());

        /** @var CustomerAddressCollection $addresses */
        $addresses = $this->customerAddressRepository->search($criteria, $context->getContext());

        $pagelet = StorefrontSearchResult::createFrom($addresses);

        $this->eventDispatcher->dispatch(
            AccountAddressListPageletLoadedEvent::NAME,
            new AccountAddressListPageletLoadedEvent($pagelet, $context, $request)
        );

        return $pagelet;
    }

    private function createCriteria(string $customerId): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customer_address.customerId', $customerId));

        return $criteria;
    }
}

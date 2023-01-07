<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @package business-ops
 */
class CustomerStorer extends FlowStorer
{
    private EntityRepository $customerRepository;

    /**
     * @internal
     */
    public function __construct(EntityRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof CustomerAware || isset($stored[CustomerAware::CUSTOMER_ID])) {
            return $stored;
        }

        $stored[CustomerAware::CUSTOMER_ID] = $event->getCustomerId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(CustomerAware::CUSTOMER_ID)) {
            return;
        }

        $storable->lazy(
            CustomerAware::CUSTOMER,
            [$this, 'load'],
            [$storable->getStore(CustomerAware::CUSTOMER_ID), $storable->getContext()]
        );
    }

    /**
     * @param array<int, mixed> $args
     */
    public function load(array $args): ?CustomerEntity
    {
        list($id, $context) = $args;
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('salutation');

        $customer = $this->customerRepository->search($criteria, $context)->get($id);

        if ($customer) {
            /** @var CustomerEntity $customer */
            return $customer;
        }

        return null;
    }
}

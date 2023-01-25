<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\CustomerGroupAware;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
class CustomerGroupStorer extends FlowStorer
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $customerGroupRepository)
    {
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof CustomerGroupAware || isset($stored[CustomerGroupAware::CUSTOMER_GROUP_ID])) {
            return $stored;
        }

        $stored[CustomerGroupAware::CUSTOMER_GROUP_ID] = $event->getCustomerGroupId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(CustomerGroupAware::CUSTOMER_GROUP_ID)) {
            return;
        }

        $storable->lazy(
            CustomerGroupAware::CUSTOMER_GROUP,
            $this->load(...),
            [$storable->getStore(CustomerGroupAware::CUSTOMER_GROUP_ID), $storable->getContext()]
        );
    }

    /**
     * @param array<int, mixed> $args
     */
    public function load(array $args): ?CustomerGroupEntity
    {
        [$id, $context] = $args;
        $criteria = new Criteria([$id]);

        $customerGroup = $this->customerGroupRepository->search($criteria, $context)->get($id);

        if ($customerGroup) {
            /** @var CustomerGroupEntity $customerGroup */
            return $customerGroup;
        }

        return null;
    }
}

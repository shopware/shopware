<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\CustomerRecoveryAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @package business-ops
 */
class CustomerRecoveryStorer extends FlowStorer
{
    private EntityRepository $customerRecoveryRepository;

    /**
     * @internal
     */
    public function __construct(EntityRepository $customerRecoveryRepository)
    {
        $this->customerRecoveryRepository = $customerRecoveryRepository;
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof CustomerRecoveryAware || isset($stored[CustomerRecoveryAware::CUSTOMER_RECOVERY_ID])) {
            return $stored;
        }

        $stored[CustomerRecoveryAware::CUSTOMER_RECOVERY_ID] = $event->getCustomerRecoveryId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(CustomerRecoveryAware::CUSTOMER_RECOVERY_ID)) {
            return;
        }

        $storable->lazy(
            CustomerRecoveryAware::CUSTOMER_RECOVERY,
            [$this, 'load'],
            [$storable->getStore(CustomerRecoveryAware::CUSTOMER_RECOVERY_ID), $storable->getContext()]
        );
    }

    /**
     * @param array<int, mixed> $args
     */
    public function load(array $args): ?CustomerRecoveryEntity
    {
        list($id, $context) = $args;
        $criteria = new Criteria([$id]);

        $customerRecovery = $this->customerRecoveryRepository->search($criteria, $context)->get($id);

        if ($customerRecovery) {
            /** @var CustomerRecoveryEntity $customerRecovery */
            return $customerRecovery;
        }

        return null;
    }
}

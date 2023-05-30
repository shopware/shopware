<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\CustomerRecoveryAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('business-ops')]
class CustomerRecoveryStorer extends FlowStorer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $customerRecoveryRepository,
        private readonly EventDispatcherInterface $dispatcher
    ) {
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
            $this->lazyLoad(...)
        );
    }

    /**
     * @param array<int, mixed> $args
     *
     * @deprecated tag:v6.6.0 - Will be removed in v6.6.0.0
     */
    public function load(array $args): ?CustomerRecoveryEntity
    {
        Feature::triggerDeprecationOrThrow(
            'v6_6_0_0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, '6.6.0.0')
        );

        [$id, $context] = $args;
        $criteria = new Criteria([$id]);

        return $this->loadCustomerRecovery($criteria, $context, $id);
    }

    private function lazyLoad(StorableFlow $storableFlow): ?CustomerRecoveryEntity
    {
        $id = $storableFlow->getStore(CustomerRecoveryAware::CUSTOMER_RECOVERY_ID);
        if ($id === null) {
            return null;
        }

        $criteria = new Criteria([$id]);

        return $this->loadCustomerRecovery($criteria, $storableFlow->getContext(), $id);
    }

    private function loadCustomerRecovery(Criteria $criteria, Context $context, string $id): ?CustomerRecoveryEntity
    {
        $criteria->addAssociation('customer.salutation');

        $event = new BeforeLoadStorableFlowDataEvent(
            CustomerRecoveryDefinition::ENTITY_NAME,
            $criteria,
            $context,
        );

        $this->dispatcher->dispatch($event, $event->getName());

        $customerRecovery = $this->customerRecoveryRepository->search($criteria, $context)->get($id);

        if ($customerRecovery) {
            /** @var CustomerRecoveryEntity $customerRecovery */
            return $customerRecovery;
        }

        return null;
    }
}

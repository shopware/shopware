<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('business-ops')]
class CustomerStorer extends FlowStorer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
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

        $storable->setData(CustomerAware::CUSTOMER_ID, $storable->getStore(CustomerAware::CUSTOMER_ID));

        $storable->lazy(
            CustomerAware::CUSTOMER,
            $this->lazyLoad(...)
        );
    }

    /**
     * @param array<int, mixed> $args
     *
     * @deprecated tag:v6.6.0 - Will be removed in v6.6.0.0
     */
    public function load(array $args): ?CustomerEntity
    {
        Feature::triggerDeprecationOrThrow(
            'v6_6_0_0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, '6.6.0.0')
        );

        [$id, $context] = $args;
        $criteria = new Criteria([$id]);

        return $this->loadCustomer($criteria, $context, $id);
    }

    private function lazyLoad(StorableFlow $storableFlow): ?CustomerEntity
    {
        $id = $storableFlow->getStore(CustomerAware::CUSTOMER_ID);
        if ($id === null) {
            return null;
        }

        $criteria = new Criteria([$id]);

        return $this->loadCustomer($criteria, $storableFlow->getContext(), $id);
    }

    private function loadCustomer(Criteria $criteria, Context $context, string $id): ?CustomerEntity
    {
        $criteria->addAssociation('salutation');

        $event = new BeforeLoadStorableFlowDataEvent(
            CustomerDefinition::ENTITY_NAME,
            $criteria,
            $context,
        );

        $this->dispatcher->dispatch($event, $event->getName());

        $customer = $this->customerRepository->search($criteria, $context)->get($id);

        if ($customer) {
            /** @var CustomerEntity $customer */
            return $customer;
        }

        return null;
    }
}

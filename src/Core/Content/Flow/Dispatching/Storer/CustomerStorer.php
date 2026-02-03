<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopware\Core\Content\Flow\Exception\CustomerDeletedException;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\CustomerProvider;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
class CustomerStorer extends FlowStorer
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CustomerProvider $customerProvider,
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

        try {
            $stored[CustomerAware::CUSTOMER_ID] = $event->getCustomerId();
        } catch (CustomerDeletedException) {
        }

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

    private function lazyLoad(StorableFlow $storableFlow): ?CustomerEntity
    {
        $id = $storableFlow->getStore(CustomerAware::CUSTOMER_ID);
        if ($id === null) {
            return null;
        }

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria = $this->customerProvider->getCriteria($id, $storableFlow->getContext());

            $event = new BeforeLoadStorableFlowDataEvent(
                CustomerDefinition::ENTITY_NAME,
                $criteria,
                $storableFlow->getContext(),
            );

            $this->dispatcher->dispatch($event, $event->getName());

            $customer = $this->customerRepository->search($criteria, $storableFlow->getContext())->getEntities()->get($id);

            if ($customer) {
                return $customer;
            }

            return null;
        }

        return $this->customerProvider->getData($id, $storableFlow->getContext());
    }
}

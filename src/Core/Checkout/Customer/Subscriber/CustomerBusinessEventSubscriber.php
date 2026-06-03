<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Event\CustomerCreatedEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerUpdatedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Dispatches the checkout.customer.created/updated business events for every DAL
 * producer of customer writes (Store API routes, Admin API, sync, import). Deletion is
 * already covered by checkout.customer.deleted (CustomerBeforeDeleteSubscriber). The
 * customer entity is loaded lazily.
 *
 * Why a DAL subscriber and not a domain dispatch: customer creation/update has no single
 * domain chokepoint — registration goes through RegisterRoute, but the Admin API, Sync
 * API and imports write the customer directly through the DAL, and profile edits are
 * spread across several routes. Reacting to the entity write is the only way to fire for
 * every producer; dispatching from one path would miss the rest.
 *
 * @todo If a shared customer domain service covering all producers is ever introduced,
 *       move the dispatch there so the moment is stated at the domain action.
 *
 * @internal
 */
#[Package('checkout')]
class CustomerBusinessEventSubscriber implements EventSubscriberInterface
{
    private const IGNORED_UPDATE_FIELDS = [
        'id',
        'createdAt',
        'updatedAt',
    ];

    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     *
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'onEntityWritten',
        ];
    }

    public function onEntityWritten(EntityWrittenContainerEvent $event): void
    {
        $writtenEvent = $event->getEventByEntityName(CustomerDefinition::ENTITY_NAME);
        if ($writtenEvent === null) {
            return;
        }

        $context = $event->getContext();

        foreach ($writtenEvent->getWriteResults() as $writeResult) {
            $customerId = $this->getId($writeResult);
            if ($customerId === null) {
                continue;
            }

            $payload = $writeResult->getPayload();
            $salesChannelId = $this->getStringValue($payload, 'salesChannelId');

            match ($writeResult->getOperation()) {
                EntityWriteResult::OPERATION_INSERT => $this->eventDispatcher->dispatch(new CustomerCreatedEvent(
                    $context,
                    $customerId,
                    $this->createCustomerLoader($customerId, $context),
                    $salesChannelId
                )),
                EntityWriteResult::OPERATION_UPDATE => $this->dispatchUpdated($customerId, $context, $payload, $salesChannelId),
                default => null,
            };
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function dispatchUpdated(string $customerId, Context $context, array $payload, ?string $salesChannelId): void
    {
        $changedFields = array_values(array_diff(array_keys($payload), self::IGNORED_UPDATE_FIELDS));
        if ($changedFields === []) {
            return;
        }

        $this->eventDispatcher->dispatch(new CustomerUpdatedEvent(
            $context,
            $customerId,
            $this->createCustomerLoader($customerId, $context),
            $changedFields,
            $salesChannelId
        ));
    }

    /**
     * Same association set checkout.customer.deleted loads in
     * CustomerBeforeDeleteSubscriber, so the lifecycle events deliver a consistent
     * payload shape to webhook consumers.
     *
     * @return \Closure(): CustomerEntity
     */
    private function createCustomerLoader(string $customerId, Context $context): \Closure
    {
        return function () use ($customerId, $context): CustomerEntity {
            $criteria = (new Criteria([$customerId]))
                ->addAssociations([
                    'salutation',
                    'defaultBillingAddress.country',
                    'defaultBillingAddress.countryState',
                    'defaultBillingAddress.salutation',
                    'defaultShippingAddress.country',
                    'defaultShippingAddress.countryState',
                    'defaultShippingAddress.salutation',
                ]);

            $customer = $this->customerRepository->search($criteria, $context)->getEntities()->get($customerId);
            if (!$customer instanceof CustomerEntity) {
                throw CustomerException::customerNotFoundByIdException($customerId);
            }

            return $customer;
        };
    }

    private function getId(EntityWriteResult $writeResult): ?string
    {
        $primaryKey = $writeResult->getPrimaryKey();
        if (\is_array($primaryKey)) {
            return $this->getStringValue($primaryKey, 'id');
        }

        return $primaryKey;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function getStringValue(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return \is_string($value) ? $value : null;
    }
}

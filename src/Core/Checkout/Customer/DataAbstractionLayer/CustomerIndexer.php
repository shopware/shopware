<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\DataAbstractionLayer;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\Event\CustomerIndexerEvent;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CustomerIndexer extends EntityIndexer
{
    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var CacheClearer
     */
    private $cacheClearer;

    /**
     * @var ManyToManyIdFieldUpdater
     */
    private $manyToManyIdFieldUpdater;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @deprecated tag:v6.4.0 - property $customerVatIdsDeprecationUpdater will be removed in 6.4.0
     *
     * @var CustomerVatIdsDeprecationUpdater
     */
    private $customerVatIdsDeprecationUpdater;

    public function __construct(
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $repository,
        CacheClearer $cacheClearer,
        ManyToManyIdFieldUpdater $manyToManyIdFieldUpdater,
        EventDispatcherInterface $eventDispatcher,
        CustomerVatIdsDeprecationUpdater $customerVatIdsDeprecationUpdater
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->repository = $repository;
        $this->cacheClearer = $cacheClearer;
        $this->manyToManyIdFieldUpdater = $manyToManyIdFieldUpdater;
        $this->eventDispatcher = $eventDispatcher;
        $this->customerVatIdsDeprecationUpdater = $customerVatIdsDeprecationUpdater;
    }

    public function getName(): string
    {
        return 'customer.indexer';
    }

    public function iterate($offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new CustomerIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(CustomerDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        $customerEvent = $event->getEventByEntityName(CustomerDefinition::ENTITY_NAME);

        if ($customerEvent) {
            $this->customerVatIdsDeprecationUpdater->updateByEvent($customerEvent);
        }

        return new CustomerIndexingMessage(array_values($updates), null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        $ids = array_unique(array_filter($ids));

        if (empty($ids)) {
            return;
        }

        $context = $message->getContext();

        $this->manyToManyIdFieldUpdater->update(CustomerDefinition::ENTITY_NAME, $ids, $context);

        $this->eventDispatcher->dispatch(new CustomerIndexerEvent($ids, $context));

        $this->cacheClearer->invalidateIds(
            array_unique(array_merge($ids)),
            CustomerDefinition::ENTITY_NAME
        );
    }
}

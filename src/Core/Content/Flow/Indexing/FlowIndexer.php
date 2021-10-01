<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Flow\Events\FlowIndexerEvent;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal (flag:FEATURE_NEXT_8225)
 */
class FlowIndexer extends EntityIndexer implements EventSubscriberInterface
{
    private IteratorFactory $iteratorFactory;

    private EntityRepositoryInterface $repository;

    private FlowPayloadUpdater $payloadUpdater;

    private EventDispatcherInterface $eventDispatcher;

    private Connection $connection;

    public function __construct(
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $repository,
        FlowPayloadUpdater $payloadUpdater,
        EventDispatcherInterface $eventDispatcher,
        Connection $connection
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->repository = $repository;
        $this->payloadUpdater = $payloadUpdater;
        $this->eventDispatcher = $eventDispatcher;
        $this->connection = $connection;
    }

    public function getName(): string
    {
        return 'flow.indexer';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostInstallEvent::class => 'refreshPlugin',
            PluginPostActivateEvent::class => 'refreshPlugin',
            PluginPostUpdateEvent::class => 'refreshPlugin',
            PluginPostDeactivateEvent::class => 'refreshPlugin',
            PluginPostUninstallEvent::class => 'refreshPlugin',
        ];
    }

    public function refreshPlugin(): void
    {
        // Delete the payload and invalid flag of all rules
        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE `flow` SET `payload` = null, `invalid` = 0')
        );
        $update->execute();
    }

    /**
     * @param array|null $offset
     *
     * @deprecated tag:v6.5.0 The parameter $offset will be native typed
     */
    public function iterate(/*?array */$offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new FlowIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(FlowDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        $this->handle(new FlowIndexingMessage(array_values($updates), null, $event->getContext()));

        return null;
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();

        $ids = array_unique(array_filter($ids));

        if (empty($ids)) {
            return;
        }

        $this->payloadUpdater->update($ids);

        $this->eventDispatcher->dispatch(new FlowIndexerEvent($ids, $message->getContext()));
    }
}

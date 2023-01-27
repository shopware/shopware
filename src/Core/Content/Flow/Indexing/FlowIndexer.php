<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Indexing;

use Shopware\Core\Content\Flow\Events\FlowIndexerEvent;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IterateEntityIndexerMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('business-ops')]
class FlowIndexer extends EntityIndexer implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly FlowPayloadUpdater $payloadUpdater,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MessageBusInterface $messageBus
    ) {
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
            AppInstalledEvent::class => 'refreshPlugin',
            AppUpdatedEvent::class => 'refreshPlugin',
            AppActivatedEvent::class => 'refreshPlugin',
            AppDeletedEvent::class => 'refreshPlugin',
            AppDeactivatedEvent::class => 'refreshPlugin',
        ];
    }

    public function refreshPlugin(): void
    {
        // Schedule indexer to update flows
        $this->messageBus->dispatch(new IterateEntityIndexerMessage($this->getName(), null));
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
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
        $ids = array_unique(array_filter($message->getData()));

        if (empty($ids)) {
            return;
        }

        $this->payloadUpdater->update($ids);

        $this->eventDispatcher->dispatch(new FlowIndexerEvent($ids, $message->getContext()));
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition())->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }
}

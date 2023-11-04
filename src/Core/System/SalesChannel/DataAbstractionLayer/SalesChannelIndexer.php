<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Event\SalesChannelIndexerEvent;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('sales-channel')]
class SalesChannelIndexer extends EntityIndexer
{
    final public const MANY_TO_MANY_UPDATER = 'sales_channel.many-to-many';

    /**
     * @internal
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ManyToManyIdFieldUpdater $manyToManyUpdater
    ) {
    }

    public function getName(): string
    {
        return 'sales_channel.indexer';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new SalesChannelIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(SalesChannelDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        return new SalesChannelIndexingMessage(array_values($updates), null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();

        $ids = array_unique(array_filter($ids));
        if (empty($ids)) {
            return;
        }

        if ($message->allow(self::MANY_TO_MANY_UPDATER)) {
            $this->manyToManyUpdater->update(SalesChannelDefinition::ENTITY_NAME, $ids, $message->getContext());
        }

        $this->eventDispatcher->dispatch(new SalesChannelIndexerEvent($ids, $message->getContext(), $message->getSkip()));
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition())->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }

    public function getOptions(): array
    {
        return [
            self::MANY_TO_MANY_UPDATER,
        ];
    }
}

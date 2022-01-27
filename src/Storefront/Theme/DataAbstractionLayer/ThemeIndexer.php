<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\Event\ThemeIndexerEvent;
use Shopware\Storefront\Theme\ThemeDefinition;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ThemeIndexer extends EntityIndexer
{
    private IteratorFactory $iteratorFactory;

    private EntityRepositoryInterface $repository;

    private EventDispatcherInterface $eventDispatcher;

    private Connection $connection;

    public function __construct(
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $repository,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->repository = $repository;
        $this->eventDispatcher = $eventDispatcher;
        $this->connection = $connection;
    }

    public function getName(): string
    {
        return 'theme.indexer';
    }

    /**
     * @param array|null $offset
     *
     * @deprecated tag:v6.5.0 The parameter $offset will be native typed
     */
    public function iterate(/*?array */$offset): ?EntityIndexingMessage
    {
        $iterator = $this->getIterator($offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new ThemeIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeysWithPropertyChange(ThemeDefinition::ENTITY_NAME, ['parentThemeId']);

        if (empty($updates)) {
            return null;
        }

        return new ThemeIndexingMessage(array_values($updates), null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();

        $ids = array_unique(array_filter($ids));
        if (empty($ids)) {
            return;
        }

        $context = $message->getContext();

        RetryableTransaction::retryable($this->connection, function () use ($ids): void {
            $this->connection->executeStatement(
                'DELETE FROM theme_child WHERE parent_id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList($ids)],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );

            $this->connection->executeStatement(
                'INSERT IGNORE INTO theme_child (child_id, parent_id)
                    (
                        SELECT id as child_id, parent_theme_id as parent_id FROM theme 
                        WHERE parent_theme_id IS NOT NULL AND id IN (:ids) AND technical_name IS NULL
                    )
                ',
                ['ids' => Uuid::fromHexToBytesList($ids)],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
        });

        $this->eventDispatcher->dispatch(new ThemeIndexerEvent($ids, $context, $message->getSkip()));
    }

    public function getTotal(): int
    {
        return $this->getIterator(null)->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }

    private function getIterator(?array $offset): \Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);
    }
}

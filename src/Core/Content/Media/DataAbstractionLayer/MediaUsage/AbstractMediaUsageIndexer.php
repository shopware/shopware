<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Maps the media shown by the indexed entities in a `*_media_usage` table,
 * so replacing a media can invalidate the pages showing it with a single indexed query.
 */
#[Package('discovery')]
abstract class AbstractMediaUsageIndexer extends EntityIndexer
{
    /**
     * @internal
     */
    public function __construct(
        protected readonly IteratorFactory $iteratorFactory,
        protected readonly Connection $connection,
        protected readonly CmsSlotMediaIdResolver $mediaIdResolver,
    ) {
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->createIterator($offset);

        $ids = $iterator->fetch();
        if ($ids === []) {
            return null;
        }

        return new EntityIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function getTotal(): int
    {
        return $this->createIterator(null)->fetchCount();
    }

    /**
     * Iterates the entities for a full reindex, indexers can skip entities which can not show media
     *
     * @param array{offset: int|null}|null $offset
     */
    protected function createIterator(?array $offset): IterableQuery
    {
        return $this->iteratorFactory->createIterator($this->getEntityName(), $offset);
    }

    /**
     * The indexed entity, its id is stored in `<entity>_id` of the usage table
     */
    abstract protected function getEntityName(): string;

    abstract protected function getTableName(): string;

    /**
     * Replaces the rows of the indexed entities
     *
     * @param list<string> $ids binary ids of the indexed entities
     * @param list<array<string, string>> $rows rows to insert, the ids as bytes
     */
    protected function replaceUsages(array $ids, array $rows): void
    {
        RetryableTransaction::retryable($this->connection, function () use ($ids, $rows): void {
            $this->connection->executeStatement(
                \sprintf(
                    'DELETE FROM `%s` WHERE %s IN (:ids)',
                    $this->getTableName(),
                    EntityDefinitionQueryHelper::escape($this->getEntityName() . '_id')
                ),
                ['ids' => $ids],
                ['ids' => ArrayParameterType::BINARY]
            );

            // ids of deleted media can still be referenced in slot configs, their rows fail the foreign key and are ignored
            $queue = new MultiInsertQueryQueue($this->connection, ignoreErrors: true);
            foreach ($rows as $row) {
                $queue->addInsert($this->getTableName(), $row);
            }
            $queue->execute();
        });
    }

    /**
     * @return list<string> binary ids of the entities to index
     */
    protected function getIds(EntityIndexingMessage $message): array
    {
        $ids = $message->getData();

        return \is_array($ids) ? array_values(Uuid::fromHexToBytesList(array_values(array_unique($ids)))) : [];
    }

    /**
     * @param list<array<string, string>|string> $primaryKeys primary keys of written entities, or of their translations
     *
     * @return list<string> hex ids of the entities, or of the entities the translations belong to
     */
    protected function getOwnerIds(array $primaryKeys, string $ownerKey): array
    {
        $ids = [];
        foreach ($primaryKeys as $primaryKey) {
            if (\is_string($primaryKey)) {
                $ids[] = $primaryKey;
            } elseif (isset($primaryKey[$ownerKey])) {
                $ids[] = $primaryKey[$ownerKey];
            }
        }

        return array_values(array_unique($ids));
    }
}

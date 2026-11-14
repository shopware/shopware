<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
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
     * The indexed entity
     */
    abstract protected function getEntityName(): string;

    /**
     * The entity whose id and version are stored in `<entity>_id` and `<entity>_version_id` of the usage table, the indexed entity by default
     */
    protected function getOwnerEntityName(): string
    {
        return $this->getEntityName();
    }

    abstract protected function getTableName(): string;

    /**
     * Replaces the rows of the live versions of the entities, only live versions are shown and cached
     *
     * @param list<string> $ids binary ids of the owner entities
     * @param list<array<string, string>> $rows rows to insert, the ids as bytes
     */
    protected function replaceUsages(array $ids, array $rows): void
    {
        RetryableTransaction::retryable($this->connection, function () use ($ids, $rows): void {
            $this->connection->executeStatement(
                \sprintf(
                    'DELETE FROM `%s` WHERE %s IN (:ids) AND %s = :version',
                    $this->getTableName(),
                    EntityDefinitionQueryHelper::escape($this->getOwnerEntityName() . '_id'),
                    EntityDefinitionQueryHelper::escape($this->getOwnerEntityName() . '_version_id')
                ),
                ['ids' => $ids, 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
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
     * @param list<array<string, string>|string> $primaryKeys primary keys of written translations
     *
     * @return list<string> hex ids of the entities the translations belong to
     */
    protected function getOwnerIds(array $primaryKeys, string $ownerKey): array
    {
        return array_values(array_unique(array_column(array_filter($primaryKeys, \is_array(...)), $ownerKey)));
    }
}

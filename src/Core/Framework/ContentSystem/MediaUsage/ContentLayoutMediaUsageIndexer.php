<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\MediaUsage;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Maps the media a content layout shows in `content_layout_media_usage`, so replacing a media can invalidate the pages
 * rendering the layout with a single indexed query.
 *
 * An element loads a media by a data requirement whose config names the `media` entity, and stores the media id, or a
 * list of ids, under the requirement's `property`. Mapped media are no stored ids and are not mapped here, the renders
 * using them record their own dependency.
 *
 * @internal
 */
#[Package('framework')]
class ContentLayoutMediaUsageIndexer extends EntityIndexer
{
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly Connection $connection,
        private readonly StoredTreeCodec $treeCodec,
    ) {
    }

    public function getName(): string
    {
        return 'content_layout_media_usage.indexer';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator(ContentLayoutDefinition::ENTITY_NAME, $offset);

        $ids = $iterator->fetch();
        if ($ids === []) {
            return null;
        }

        return new EntityIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $ids = $event->getPrimaryKeysWithPropertyChange(ContentLayoutDefinition::ENTITY_NAME, [ContentLayoutDefinition::LAYOUT_FIELD]);

        return $ids === [] ? null : new EntityIndexingMessage(array_values($ids), null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        $ids = \is_array($ids) ? Uuid::fromHexToBytesList(array_values(array_unique($ids))) : [];
        if ($ids === []) {
            return;
        }

        $layouts = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)), layout FROM content_layout WHERE id IN (:ids)',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::BINARY]
        );

        $rows = [];
        foreach ($layouts as $id => $layout) {
            $tree = $this->treeCodec->decode(json_decode((string) $layout, true, 512, \JSON_THROW_ON_ERROR));

            foreach ($tree->getEntityIds(MediaDefinition::ENTITY_NAME) as $mediaId) {
                $rows[] = ['content_layout_id' => Uuid::fromHexToBytes((string) $id), 'media_id' => Uuid::fromHexToBytes($mediaId)];
            }
        }

        RetryableTransaction::retryable($this->connection, function () use ($ids, $rows): void {
            $this->connection->executeStatement(
                'DELETE FROM content_layout_media_usage WHERE content_layout_id IN (:ids)',
                ['ids' => $ids],
                ['ids' => ArrayParameterType::BINARY]
            );

            // ids of deleted media can still be stored in a layout, their rows fail the foreign key and are ignored
            $queue = new MultiInsertQueryQueue($this->connection, ignoreErrors: true);
            foreach ($rows as $row) {
                $queue->addInsert('content_layout_media_usage', $row);
            }
            $queue->execute();
        });
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator(ContentLayoutDefinition::ENTITY_NAME)->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }
}

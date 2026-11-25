<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Infrastructure\Path;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Core\Application\MediaPathUpdater;
use Shopware\Core\Framework\Adapter\Cache\Message\InvalidateMediaCache;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\SynchronousPostUpdateIndexer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;

#[Package('discovery')]
class MediaPathPostUpdater extends SynchronousPostUpdateIndexer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly MediaPathUpdater $updater,
        private readonly Connection $connection,
        private readonly MessageBusInterface $bus
    ) {
    }

    public function getName(): string
    {
        return 'media.path.post_update';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator('media', $offset);

        $ids = $iterator->fetch();

        if ($ids === []) {
            return null;
        }

        return new EntityIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        if (!\is_array($ids)) {
            return;
        }

        $mediaWithMissingPaths = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM media WHERE path IS NULL AND id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );
        $this->updater->updateMedia($mediaWithMissingPaths);

        $thumbnailsWithMissingPaths = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM media_thumbnail WHERE path IS NULL AND media_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $this->updater->updateThumbnails($thumbnailsWithMissingPaths);

        InvalidateMediaCache::dispatch($this->bus, $ids, $message->getContext());
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator('media', null)->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(self::class);
    }
}

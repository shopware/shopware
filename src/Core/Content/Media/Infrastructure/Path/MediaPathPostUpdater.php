<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Infrastructure\Path;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Core\Application\MediaPathUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\PostUpdateIndexer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('core')]
class MediaPathPostUpdater extends PostUpdateIndexer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly MediaPathUpdater $updater,
        private readonly Connection $connection
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

        if (empty($ids)) {
            return null;
        }

        return new EntityIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $this->updater->updateMedia($message->getData());

        $thumbnails = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM media_thumbnail WHERE media_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($message->getData())],
            ['ids' => ArrayParameterType::BINARY]
        );

        $this->updater->updateThumbnails($thumbnails);
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

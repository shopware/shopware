<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Infrastructure\Path;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Core\Path\MediaLocationBuilder;
use Shopware\Core\Content\Media\Domain\Event\MediaLocationEvent;
use Shopware\Core\Content\Media\Domain\Event\ThumbnailLocationEvent;
use Shopware\Core\Content\Media\Domain\Path\Struct\MediaLocationStruct;
use Shopware\Core\Content\Media\Domain\Path\Struct\ThumbnailLocationStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('content')]
class SqlMediaLocationBuilder implements MediaLocationBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly Connection $connection
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function media(array $ids): array
    {
        $ids = \array_unique($ids);
        if (empty($ids)) {
            return [];
        }

        $data = $this->connection->fetchAllAssociativeIndexed(
            'SELECT LOWER(HEX(id)) as array_key,
                    LOWER(HEX(id)) as id,
                    file_extension,
                    file_name,
                    uploaded_at
            FROM media
            WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::STRING]
        );

        $locations = [];

        foreach ($data as $key => $row) {
            $locations[$key] = new MediaLocationStruct(
                $row['id'],
                $row['file_extension'],
                $row['file_name'],
                $row['uploaded_at'] ? new \DateTimeImmutable($row['uploaded_at']) : null
            );
        }

        return $this->mediaEvent($locations);
    }

    /**
     * {@inheritdoc}
     */
    public function thumbnails(array $ids): array
    {
        $ids = \array_unique($ids);
        if (empty($ids)) {
            return [];
        }

        $data = $this->connection->fetchAllAssociativeIndexed(
            'SELECT LOWER(HEX(media_thumbnail.id)) as array_key,
                    LOWER(HEX(media_thumbnail.id)) as id,
                    media.file_extension,
                    media.file_name,
                    LOWER(HEX(media.id)) as media_id,
                    width,
                    height,
                    uploaded_at
            FROM media_thumbnail
                INNER JOIN media ON media.id = media_thumbnail.media_id
            WHERE media_thumbnail.id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::STRING]
        );

        $locations = [];

        foreach ($data as $key => $row) {
            $media = new MediaLocationStruct(
                $row['media_id'],
                $row['file_extension'],
                $row['file_name'],
                $row['uploaded_at'] ? new \DateTimeImmutable($row['uploaded_at']) : null
            );

            $locations[$key] = new ThumbnailLocationStruct(
                $row['id'],
                (int) $row['width'],
                (int) $row['height'],
                $media
            );
        }

        return $this->thumbnailEvent($locations);
    }

    /**
     * @param array<ThumbnailLocationStruct> $locations
     *
     * @return array<ThumbnailLocationStruct>
     */
    private function thumbnailEvent(array $locations): array
    {
        $this->dispatcher->dispatch(
            new ThumbnailLocationEvent($locations)
        );

        return $locations;
    }

    /**
     * @param array<MediaLocationStruct> $locations
     *
     * @return array<MediaLocationStruct>
     */
    private function mediaEvent(array $locations): array
    {
        $this->dispatcher->dispatch(
            new MediaLocationEvent($locations)
        );

        return $locations;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Path\Contract\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Path\Contract\Event\MediaLocationEvent;
use Shopware\Core\Content\Media\Path\Contract\Event\ThumbnailLocationEvent;
use Shopware\Core\Content\Media\Path\Contract\Struct\MediaLocationStruct;
use Shopware\Core\Content\Media\Path\Contract\Struct\ThumbnailLocationStruct;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Location builder for media path strategies
 *
 * Use this class to build the location object. When faking objects (e.g. for rename logic), you should consider to allow data extensions via events
 *
 * @final
 *
 * @public
 */
class MediaLocationBuilder
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly Connection $connection
    ) {
    }

    /**
     * Generates a index list of media location structs
     *
     * These structs are necessary to generate the file paths for media. By default,
     * shopware stores this values inside the database to prevent unnecessary on-demand calculation
     *
     * @param array<string> $ids
     *
     * @return array<string, MediaLocationStruct> indexed by id
     */
    public function media(array $ids): array
    {
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

        $locations = array_map(function (array $data): MediaLocationStruct {
            return new MediaLocationStruct(
                $data['id'],
                $data['file_extension'],
                $data['file_name'],
                $data['uploaded_at'] ? new \DateTimeImmutable($data['uploaded_at']) : null
            );
        }, $data);

        return $this->mediaEvent($locations);
    }

    /**
     * Generates a index list of thumbnail location structs
     *
     * These structs are necessary to generate the file paths for thumbnails. By default
     * shopware stores this values inside the database to prevent unnecessary on-demand calculation
     *
     * @param array<string> $ids
     *
     * @return array<string, ThumbnailLocationStruct> indexed by id
     */
    public function thumbnails(array $ids): array
    {
        $data = $this->connection->fetchAllAssociativeIndexed(
            'SELECT LOWER(HEX(media_thumbnail.id)) as array_key,
                    LOWER(HEX(media_thumbnail.id)) as id,
                    media.file_extension,
                    media.file_name,
                    media_id,
                    width,
                    height,
                    uploaded_at
            FROM media_thumbnail
                INNER JOIN media ON media.id = media_thumbnail.media_id
            WHERE media_thumbnail.id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::STRING]
        );

        $locations = array_map(function (array $data): ThumbnailLocationStruct {
            return new ThumbnailLocationStruct(
                $data['id'],
                (int) $data['width'],
                (int) $data['height'],
                new MediaLocationStruct(
                    $data['media_id'],
                    $data['file_extension'],
                    $data['file_name'],
                    $data['uploaded_at'] ? new \DateTimeImmutable($data['uploaded_at']) : null
                )
            );
        }, $data);

        return $this->thumbnailEvent($locations);
    }

    /**
     * Allows to generate locations based on loaded entities.
     *
     * @param array<Entity> $entities
     *
     * @return array<MediaLocationStruct|ThumbnailLocationStruct>
     */
    public function fromEntities(array $entities): array
    {
        $media = [];

        $thumbnails = [];

        foreach ($entities as $entity) {
            if ($entity->getInternalEntityName() === 'media') {
                $media[] = MediaLocationStruct::fromEntity($entity);

                continue;
            }

            if ($entity->getInternalEntityName() !== 'media_thumbnail') {
                // todo@dr ensure media is loaded?
                $thumbnails[] = ThumbnailLocationStruct::fromEntity($entity, $entity->get('media'));

                continue;
            }
        }

        return array_merge(
            ...$this->mediaEvent($media),
            ...$this->thumbnailEvent($thumbnails)
        );
    }

    private function thumbnailEvent(array $locations): array
    {
        $this->dispatcher->dispatch(
            new ThumbnailLocationEvent($locations)
        );

        return $locations;
    }

    private function mediaEvent(array $locations): array
    {
        $this->dispatcher->dispatch(
            new MediaLocationEvent($locations)
        );

        return $locations;
    }
}

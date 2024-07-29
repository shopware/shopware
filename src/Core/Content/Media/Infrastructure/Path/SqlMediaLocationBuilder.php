<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Infrastructure\Path;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Core\Application\MediaLocationBuilder;
use Shopware\Core\Content\Media\Core\Event\MediaLocationEvent;
use Shopware\Core\Content\Media\Core\Event\ThumbnailLocationEvent;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\Core\Params\ThumbnailLocationStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore (see \Shopware\Tests\Integration\Core\Content\Media\Infrastructure\Path\MediaLocationBuilderTest)
 */
#[Package('buyers-experience')]
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
            ['ids' => ArrayParameterType::BINARY]
        );

        $locations = [];

        foreach ($data as $key => $row) {
            $locations[(string) $key] = new MediaLocationStruct(
                $row['id'],
                $row['file_extension'],
                $row['file_name'],
                $row['uploaded_at'] ? new \DateTimeImmutable($row['uploaded_at']) : null
            );
        }

        $this->dispatcher->dispatch(
            new MediaLocationEvent($locations)
        );

        return $locations;
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
            ['ids' => ArrayParameterType::BINARY]
        );

        $locations = [];

        foreach ($data as $key => $row) {
            $media = new MediaLocationStruct(
                $row['media_id'],
                $row['file_extension'],
                $row['file_name'],
                $row['uploaded_at'] ? new \DateTimeImmutable($row['uploaded_at']) : null
            );

            $locations[(string) $key] = new ThumbnailLocationStruct(
                $row['id'],
                (int) $row['width'],
                (int) $row['height'],
                $media
            );
        }

        $this->dispatcher->dispatch(
            new ThumbnailLocationEvent($locations)
        );

        return $locations;
    }
}

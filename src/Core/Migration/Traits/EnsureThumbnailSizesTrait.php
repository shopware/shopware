<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('core')]
trait EnsureThumbnailSizesTrait
{
    /**
     * @param list<array{width: int, height: int}> $thumbnailSizes
     *
     * @return string[]
     */
    final protected function ensureThumbnailSizes(array $thumbnailSizes, Connection $connection): array
    {
        /** @var list<array{id: string, width: string, height: string}> $allSizes */
        $allSizes = $connection->fetchAllAssociative(
            'SELECT `id`, `width`, `height` FROM `media_thumbnail_size`'
        );

        $insertStatement = $connection->prepare('
                INSERT INTO `media_thumbnail_size` (`id`, `width`, `height`, created_at)
                VALUES (:id, :width, :height, :createdAt)
            ');

        $sizes = [];
        foreach ($thumbnailSizes as $thumbnailSize) {
            $result = array_filter($allSizes, function ($var) use ($thumbnailSize) {
                return (int) $var['width'] === $thumbnailSize['width'] && (int) $var['height'] === $thumbnailSize['height'];
            });

            if (\count($result) > 0) {
                $sizes[] = reset($result)['id'];

                continue;
            }

            $id = Uuid::randomBytes();
            $insertStatement->executeStatement([
                'id' => $id,
                'width' => $thumbnailSize['width'],
                'height' => $thumbnailSize['height'],
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            $sizes[] = $id;
        }

        return $sizes;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * Apps could reference files outside their directory as icon, which stored those files as App icon or as payment and shipping method media.
 * This removes every stored icon that is not an image.
 * The App icons are rebuilt from the App files on the next App update.
 *
 * @internal
 */
#[Package('framework')]
class Migration1793694040ClearInvalidAppIcons extends MigrationStep
{
    private const MEDIA_ICON_MIME_TYPES = [...Filesystem::RASTER_IMAGE_MIME_TYPES, 'image/svg+xml'];

    public function getCreationTimestamp(): int
    {
        return 1793694040;
    }

    public function update(Connection $connection): void
    {
        $this->clearInvalidIconBlobs($connection, 'app');
        $this->clearInvalidIconBlobs($connection, 'app_flow_action');

        $this->unlinkInvalidIconMedia($connection, 'payment_method');
        $this->unlinkInvalidIconMedia($connection, 'shipping_method');
    }

    private function clearInvalidIconBlobs(Connection $connection, string $table): void
    {
        $icons = $connection->fetchAllKeyValue(\sprintf('SELECT `id`, `icon` FROM `%s` WHERE `icon` IS NOT NULL', $table));
        $finfo = new \finfo(\FILEINFO_MIME_TYPE);

        $invalidIds = [];
        foreach ($icons as $id => $icon) {
            if (\strlen((string) $icon) > Filesystem::MAX_IMAGE_SIZE
                || !\in_array($finfo->buffer((string) $icon), Filesystem::RASTER_IMAGE_MIME_TYPES, true)) {
                $invalidIds[] = $id;
            }
        }

        if ($invalidIds === []) {
            return;
        }

        $connection->executeStatement(
            \sprintf('UPDATE `%s` SET `icon` = NULL WHERE `id` IN (:ids)', $table),
            ['ids' => $invalidIds],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * The media is only unlinked, so `bin/console media:delete-unused` removes it together with its file.
     */
    private function unlinkInvalidIconMedia(Connection $connection, string $methodTable): void
    {
        $mediaIds = $connection->fetchFirstColumn(
            \sprintf(
                'SELECT `media`.`id`
                FROM `app_%1$s`
                INNER JOIN `media` ON `media`.`id` = `app_%1$s`.`original_media_id`
                WHERE `media`.`mime_type` IS NULL OR `media`.`mime_type` NOT IN (:mimeTypes)',
                $methodTable
            ),
            ['mimeTypes' => self::MEDIA_ICON_MIME_TYPES],
            ['mimeTypes' => ArrayParameterType::STRING]
        );

        if ($mediaIds === []) {
            return;
        }

        $connection->executeStatement(
            \sprintf(
                'UPDATE `%1$s` SET `media_id` = NULL
                WHERE `media_id` IN (:ids) AND `id` IN (SELECT `%1$s_id` FROM `app_%1$s`)',
                $methodTable
            ),
            ['ids' => $mediaIds],
            ['ids' => ArrayParameterType::BINARY]
        );

        $connection->executeStatement(
            \sprintf('UPDATE `app_%s` SET `original_media_id` = NULL WHERE `original_media_id` IN (:ids)', $methodTable),
            ['ids' => $mediaIds],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}

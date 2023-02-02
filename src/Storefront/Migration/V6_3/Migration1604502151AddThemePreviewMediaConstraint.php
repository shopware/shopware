<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1604502151AddThemePreviewMediaConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1604502151;
    }

    public function update(Connection $connection): void
    {
        // Find themes with missing preview media
        $themeIdsWithInvalidMediaId = $connection->fetchAll(
            'SELECT `theme`.`id` FROM `theme`
            LEFT OUTER JOIN `media` ON `theme`.`preview_media_id` = `media`.`id`
            WHERE `media`.`id` IS NULL;'
        );

        $connection->executeUpdate(
            'UPDATE `theme` SET `preview_media_id` = NULL WHERE `id` IN (:theme_ids)',
            [
                'theme_ids' => array_column($themeIdsWithInvalidMediaId, 'id'),
            ],
            [
                'theme_ids' => Connection::PARAM_STR_ARRAY,
            ]
        );

        $connection->exec(
            'ALTER TABLE `theme`
            ADD FOREIGN KEY `fk.theme.preview_media_id`(preview_media_id) REFERENCES media(id)
                ON UPDATE CASCADE
                ON DELETE SET NULL;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}

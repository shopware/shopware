<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration;

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
        // get all themes where preview_media_id is invalid
        $themeIdsWithInvalidMediaId = $connection->executeQuery('
            SELECT `theme`.`id` FROM `theme`
            LEFT OUTER JOIN `media` ON `theme`.`preview_media_id` = `media`.`id`
            WHERE `media`.`id` IS null
        ')->fetchColumn();

        $connection->executeUpdate('
            UPDATE `theme` SET `theme`.`preview_media_id` = null
            WHERE `theme`.`id` IN (:theme_ids)
        ', ['theme_ids' => $themeIdsWithInvalidMediaId]);

        $connection->exec(
            'ALTER TABLE `theme`
                ADD FOREIGN KEY `fk.theme.preview_media_id`(preview_media_id) REFERENCES media(id)
                    ON UPDATE CASCADE
                    ON DELETE SET NULL;
       ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}

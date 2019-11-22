<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1571059598ChangeGreatBritainToUnitedKingdom extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1571059598;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            UPDATE `country_translation`
            SET `name` = "United Kingdom"
            WHERE `name` = "Great Britain" AND (
                SELECT `locale`.`code`
                FROM `language`
                INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id`
                WHERE `language`.`id` = `country_translation`.`language_id`
            ) = "en-GB"
        ');

        $connection->exec('
            UPDATE `country_translation`
            SET `name` = "Vereinigtes Königreich"
            WHERE `name` = "Großbritannien" AND (
                SELECT `locale`.`code`
                FROM `language`
                INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id`
                WHERE `language`.`id` = `country_translation`.`language_id`
            ) = "de-DE"
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1752229050ChangeDESnippetOfMeterUnit extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1752229050;
    }

    public function update(Connection $connection): void
    {
        $deLanguageId = $this->getDeDeId($connection);

        if (!$deLanguageId) {
            return;
        }

        $meterUnitId = $connection->fetchOne(
            'SELECT id FROM measurement_display_unit WHERE short_name = "m"'
        );

        if (!$meterUnitId) {
            return;
        }

        $connection->executeStatement('
            UPDATE `measurement_display_unit_translation` SET `name` = :name
            WHERE `measurement_display_unit_id` = :unitId AND `language_id` = :languageId AND `updated_at` IS NULL
        ', [
            'name' => 'Meter',
            'unitId' => $meterUnitId,
            'languageId' => $deLanguageId,
        ]);
    }

    private function getDeDeId(Connection $connection): ?string
    {
        $result = $connection->fetchOne(
            '
            SELECT lang.id
            FROM language lang
            INNER JOIN locale loc ON lang.translation_code_id = loc.id
            AND loc.code = "de-DE"'
        );

        if ($result === false || Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM) === $result) {
            return null;
        }

        return (string) $result;
    }
}

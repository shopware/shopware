<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1742199549MeasurementSystemTable;
use Shopware\Core\Migration\V6_7\Migration1742199550MeasurementDisplayUnitTable;
use Shopware\Core\Migration\V6_7\Migration1752229050ChangeDESnippetOfMeterUnit;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1752229050ChangeDESnippetOfMeterUnit::class)]
class Migration1752229050ChangeDESnippetOfMeterUnitTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_display_unit_translation`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_display_unit`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_system_translation`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_system`');

        $systemMigration = new Migration1742199549MeasurementSystemTable();
        $systemMigration->update($this->connection);

        $unitMigration = new Migration1742199550MeasurementDisplayUnitTable();
        $unitMigration->update($this->connection);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1752229050, (new Migration1752229050ChangeDESnippetOfMeterUnit())->getCreationTimestamp());
    }

    public function testMigrationUpdatesGermanTranslation(): void
    {
        $deLanguageId = $this->getDeLanguageId();
        $meterUnitId = $this->getMeterUnitId();

        if (!$deLanguageId || !$meterUnitId) {
            static::markTestSkipped('German language or meter unit not found');
        }

        $this->connection->executeStatement('
            UPDATE `measurement_display_unit_translation`
            SET `name` = :name, `updated_at` = NULL
            WHERE `measurement_display_unit_id` = :unitId AND `language_id` = :languageId
        ', [
            'name' => 'Zähler',
            'unitId' => $meterUnitId,
            'languageId' => $deLanguageId,
        ]);

        $translationBefore = $this->connection->fetchOne('
            SELECT `name` FROM `measurement_display_unit_translation`
            WHERE `measurement_display_unit_id` = :unitId AND `language_id` = :languageId
        ', [
            'unitId' => $meterUnitId,
            'languageId' => $deLanguageId,
        ]);

        static::assertSame('Zähler', $translationBefore);

        $migration = new Migration1752229050ChangeDESnippetOfMeterUnit();
        $migration->update($this->connection);
        $migration->update($this->connection);

        // Verify the translation was updated
        $translationAfter = $this->connection->fetchOne('
            SELECT `name` FROM `measurement_display_unit_translation`
            WHERE `measurement_display_unit_id` = :unitId AND `language_id` = :languageId
        ', [
            'unitId' => $meterUnitId,
            'languageId' => $deLanguageId,
        ]);

        static::assertSame('Meter', $translationAfter);
    }

    public function testMigrationDoesNotUpdateModifiedTranslation(): void
    {
        $deLanguageId = $this->getDeLanguageId();
        $meterUnitId = $this->getMeterUnitId();

        if (!$deLanguageId || !$meterUnitId) {
            static::markTestSkipped('German language or meter unit not found');
        }

        // Set a different German translation with updated_at (simulating manual modification)
        $this->connection->executeStatement('
            UPDATE `measurement_display_unit_translation`
            SET `name` = :name, `updated_at` = :updatedAt
            WHERE `measurement_display_unit_id` = :unitId AND `language_id` = :languageId
        ', [
            'name' => 'Manuell bearbeitet',
            'unitId' => $meterUnitId,
            'languageId' => $deLanguageId,
            'updatedAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $migration = new Migration1752229050ChangeDESnippetOfMeterUnit();
        $migration->update($this->connection);

        $translationAfter = $this->connection->fetchOne('
            SELECT `name` FROM `measurement_display_unit_translation`
            WHERE `measurement_display_unit_id` = :unitId AND `language_id` = :languageId
        ', [
            'unitId' => $meterUnitId,
            'languageId' => $deLanguageId,
        ]);

        static::assertSame('Manuell bearbeitet', $translationAfter);
    }

    private function getDeLanguageId(): ?string
    {
        $result = $this->connection->fetchOne('
            SELECT lang.id
            FROM language lang
            INNER JOIN locale loc ON lang.translation_code_id = loc.id
            AND loc.code = "de-DE"
        ');

        if ($result === false || Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM) === $result) {
            return null;
        }

        return (string) $result;
    }

    private function getMeterUnitId(): ?string
    {
        $result = $this->connection->fetchOne('SELECT id FROM measurement_display_unit WHERE short_name = "m"');

        return $result ?: null;
    }
}

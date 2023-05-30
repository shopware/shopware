<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1631625055AddPositionToImportExportMappings;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1631625055AddPositionToImportExportMappings
 */
class Migration1631625055AddPositionToImportExportMappingsTest extends TestCase
{
    public function testMigration(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $this->removePositionProperty($connection);

        $profiles = $connection->fetchAllAssociative('SELECT * FROM `import_export_profile`');

        foreach ($profiles as $profile) {
            $mappings = json_decode((string) $profile['mapping'], true, 512, \JSON_THROW_ON_ERROR);

            foreach ($mappings as $mapping) {
                static::assertArrayNotHasKey('position', $mapping);
            }
        }

        $migration = new Migration1631625055AddPositionToImportExportMappings();

        $migration->update($connection);

        $profiles = $connection->fetchAllAssociative('SELECT * FROM `import_export_profile`');

        foreach ($profiles as $profile) {
            $mappings = json_decode((string) $profile['mapping'], true, 512, \JSON_THROW_ON_ERROR);

            foreach ($mappings as $index => $mapping) {
                static::assertEquals($index, $mapping['position']);
            }
        }
    }

    private function removePositionProperty(Connection $conn): void
    {
        $profiles = $conn->fetchAllAssociative('SELECT * FROM `import_export_profile`');

        foreach ($profiles as $profile) {
            $mappings = json_decode((string) $profile['mapping'], true, 512, \JSON_THROW_ON_ERROR);

            foreach ($mappings as &$mapping) {
                unset($mapping['position']);
            }

            $mappings = json_encode($mappings, \JSON_THROW_ON_ERROR);

            $conn->update('import_export_profile', ['mapping' => $mappings], ['id' => $profile['id']]);
        }
    }
}

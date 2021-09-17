<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1631625055AddPositionToImportExportMappings;

class Migration1631625055AddPositionToImportExportMappingsTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMigration(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $this->removePositionProperty($connection);

        $profiles = $connection->fetchAllAssociative('SELECT * FROM `import_export_profile`');

        foreach ($profiles as $profile) {
            $mappings = json_decode($profile['mapping'], true);

            foreach ($mappings as $mapping) {
                static::assertArrayNotHasKey('position', $mapping);
            }
        }

        $migration = new Migration1631625055AddPositionToImportExportMappings();

        $migration->update($connection);

        $profiles = $connection->fetchAllAssociative('SELECT * FROM `import_export_profile`');

        foreach ($profiles as $profile) {
            $mappings = json_decode($profile['mapping'], true);

            foreach ($mappings as $index => $mapping) {
                static::assertEquals($index, $mapping['position']);
            }
        }
    }

    private function removePositionProperty(Connection $conn): void
    {
        $profiles = $conn->fetchAllAssociative('SELECT * FROM `import_export_profile`');

        foreach ($profiles as $profile) {
            $mappings = json_decode($profile['mapping'], true);

            foreach ($mappings as &$mapping) {
                unset($mapping['position']);
            }

            $mappings = json_encode($mappings);

            $conn->update('import_export_profile', ['mapping' => $mappings], ['id' => $profile['id']]);
        }
    }
}

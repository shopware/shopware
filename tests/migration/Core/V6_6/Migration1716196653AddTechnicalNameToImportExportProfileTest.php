<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1716196653AddTechnicalNameToImportExportProfile;

/**
 * @internal
 */
#[CoversClass(Migration1716196653AddTechnicalNameToImportExportProfile::class)]
class Migration1716196653AddTechnicalNameToImportExportProfileTest extends TestCase
{
    use KernelTestBehaviour;

    protected Connection $connection;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $this->connection = self::getContainer()->get(Connection::class);
    }

    public function testMigration(): void
    {
        $columns = $this->connection->fetchAllAssociative('SHOW COLUMNS FROM `import_export_profile`');
        $columns = array_column($columns, 'Field');

        if (\in_array('technical_name', $columns, true)) {
            $this->connection->executeStatement('ALTER TABLE `import_export_profile` DROP COLUMN `technical_name`');
        }

        $index = $this->connection->fetchAllAssociative('SHOW INDEX FROM `import_export_profile` WHERE Key_name = \'uniq.import_export_profile.technical_name\'');

        if (!empty($index)) {
            $this->connection->executeStatement('ALTER TABLE `import_export_profile` DROP INDEX `uniq.import_export_profile.technical_name`');
        }

        $m = new Migration1716196653AddTechnicalNameToImportExportProfile();
        $m->update($this->connection);

        $columns = $this->connection->fetchAllAssociative('SHOW COLUMNS FROM `import_export_profile`');
        $columns = array_column($columns, 'Field');

        static::assertContains('technical_name', $columns);

        $index = $this->connection->fetchAllAssociative('SHOW INDEX FROM `import_export_profile` WHERE Key_name = \'uniq.import_export_profile.technical_name\'');

        static::assertNotEmpty($index);
    }

    public function testDoubleExecution(): void
    {
        $m = new Migration1716196653AddTechnicalNameToImportExportProfile();
        $m->update($this->connection);
        $m->update($this->connection);

        $columns = $this->connection->fetchAllAssociative('SHOW COLUMNS FROM `import_export_profile`');
        $columns = array_column($columns, 'Field');

        static::assertContains('technical_name', $columns);

        $index = $this->connection->fetchAllAssociative('SHOW INDEX FROM `import_export_profile` WHERE Key_name = \'uniq.import_export_profile.technical_name\'');

        static::assertNotEmpty($index);
    }

    /**
     * @param array<int, string> $names
     * @param array<int, string> $expectedTechnicalNames
     */
    #[DataProvider('nameProvider')]
    public function testGeneratedTechnicalName(array $names, array $expectedTechnicalNames): void
    {
        foreach ($names as $name) {
            $this->connection->insert('import_export_profile', [
                'id' => Uuid::randomBytes(),
                'name' => $name,
                'source_entity' => 'product',
                'file_type' => 'text/csv',
                'delimiter' => ';',
                'enclosure' => '"',
                'created_at' => '2021-01-01 00:00:00',
            ]);
        }

        $m = new Migration1716196653AddTechnicalNameToImportExportProfile();
        $m->update($this->connection);

        $technicalNames = $this->connection->fetchAllAssociative('SELECT id, technical_name FROM import_export_profile');

        // assert that all expected technical names are present
        foreach ($expectedTechnicalNames as $expectedTechnicalName) {
            $found = false;
            foreach ($technicalNames as $technicalName) {
                if ($technicalName['technical_name'] === $expectedTechnicalName) {
                    $found = true;
                    break;
                }
            }

            static::assertTrue($found, sprintf('Technical name "%s" not found', $expectedTechnicalName));
        }
    }

    public static function nameProvider(): \Generator
    {
        yield 'single profile' => [
            'names' => ['Default Profile'],
            'expectedTechnicalNames' => ['default_profile'],
        ];

        yield 'single profile with null name' => [
            'names' => [null],
            'expectedTechnicalNames' => ['unnamed_profile'],
        ];

        yield 'multiple profiles with different names' => [
            'names' => [
                'Default Profile',
                'Another Profile',
                'Yet Another Profile',
            ],
            'expectedTechnicalNames' => [
                'default_profile',
                'another_profile',
                'yet_another_profile',
            ],
        ];

        yield 'multiple profiles with special characters' => [
            'names' => [
                'Default?? Profile!*@',
                '***Another Profile***',
            ],
            'expectedTechnicalNames' => [
                'default_profile',
                'another_profile',
            ],
        ];

        yield 'multiple profiles with the same name' => [
            'names' => [
                'Default Profile',
                'Default Profile',
                'Default Profile',
                'Default Profile',
            ],
            'expectedTechnicalNames' => [
                'default_profile',
                'default_profile_1',
                'default_profile_2',
                'default_profile_3',
            ],
        ];

        yield 'multiple profiles with the same name and different case' => [
            'names' => [
                'Default Profile',
                'default profile',
            ],
            'expectedTechnicalNames' => [
                'default_profile',
                'default_profile_1',
            ],
        ];

        yield 'multiple profiles with the same name and different special characters' => [
            'names' => [
                'Default Profile',
                'Default Profile!',
                '**Default Profile**',
            ],
            'expectedTechnicalNames' => [
                'default_profile',
                'default_profile_1',
                'default_profile_2',
            ],
        ];
    }
}

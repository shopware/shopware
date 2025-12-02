<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\ImportExportProfileDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1717573310ImportExportTechnicalNameRequired;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(Migration1717573310ImportExportTechnicalNameRequired::class)]
class Migration1717573310ImportExportTechnicalNameRequiredTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private static bool $nameColumnAdded = false;

    private static bool $hasDefaultProfiles = false;

    public static function setUpBeforeClass(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        $columns = $connection->fetchAllAssociative('SHOW COLUMNS FROM `import_export_profile`');
        $columns = array_column($columns, 'Field');

        if (!\in_array('name', $columns, true)) {
            $connection->executeStatement('ALTER TABLE `import_export_profile` ADD COLUMN `name` VARCHAR(255) NULL');
            self::$nameColumnAdded = true;
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$nameColumnAdded) {
            self::getContainer()->get(Connection::class)
                ->executeStatement('ALTER TABLE `import_export_profile` DROP COLUMN `name`');
        }
    }

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->connection
            ->executeStatement('ALTER TABLE `import_export_profile` MODIFY COLUMN `technical_name` VARCHAR(255) NULL');
    }

    protected function tearDown(): void
    {
        // Clean up profiles to ensure a clean state for the tests
        $this->connection->executeStatement('DELETE FROM `import_export_profile` WHERE `system_default` != 1');
    }

    public function testUpdateSetTechnicalNameRequired(): void
    {
        $migration = new Migration1717573310ImportExportTechnicalNameRequired();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $manager = $this->connection->createSchemaManager();
        $columns = $manager->listTableColumns(ImportExportProfileDefinition::ENTITY_NAME);

        static::assertArrayHasKey('technical_name', $columns);
        static::assertTrue($columns['technical_name']->getNotnull());
    }

    /**
     * @param array<int, array{uuid: string, name: string|null, technical_name: string|null, expected_technical_name: string}> $datas
     */
    #[DataProvider('importExportProfilesDataProvider')]
    public function testUpdateGeneratesTechnicalNames(array $datas): void
    {
        // Insert default data at the start to ensure the migration has a clean state
        // a previous migration test already manipulated the data
        if (!self::$hasDefaultProfiles) {
            $this->insertDefaultData();
        }

        foreach ($datas as $data) {
            $this->connection->insert('import_export_profile', [
                'id' => $data['uuid'],
                'name' => $data['name'],
                'technical_name' => $data['technical_name'],
                'source_entity' => 'product',
                'file_type' => 'text/csv',
                'created_at' => '2021-01-01 00:00:00',
            ]);
        }

        $migration = new Migration1717573310ImportExportTechnicalNameRequired();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $results = $this->connection->fetchAllAssociative(
            'SELECT id, technical_name FROM `import_export_profile` WHERE `system_default` != 1'
        );

        foreach ($datas as $expected) {
            $found = false;
            foreach ($results as $result) {
                if ($result['id'] === $expected['uuid']) {
                    static::assertSame($expected['expected_technical_name'], $result['technical_name']);
                    $found = true;
                    break;
                }
            }
            static::assertTrue($found, 'Record with UUID not found');
        }
    }

    public static function importExportProfilesDataProvider(): \Generator
    {
        yield 'single profile' => [
            [
                [
                    'uuid' => Uuid::randomBytes(),
                    'name' => 'Default Profile',
                    'technical_name' => null,
                    'expected_technical_name' => 'default_profile',
                ],
            ],
        ];

        yield 'multiple profiles with existing technical_name' => [
            [
                [
                    'uuid' => Uuid::randomBytes(),
                    'name' => 'Custom Profile',
                    'technical_name' => 'custom_profile_1',
                    'expected_technical_name' => 'custom_profile_1',
                ],
                [
                    'uuid' => Uuid::randomBytes(),
                    'name' => null,
                    'technical_name' => 'custom_profile_2',
                    'expected_technical_name' => 'custom_profile_2',
                ],
            ],
        ];

        yield 'multiple profiles with null name and null technical_name' => [
            [
                [
                    'uuid' => Uuid::randomBytes(),
                    'name' => null,
                    'technical_name' => null,
                    'expected_technical_name' => 'unnamed_profile',
                ],
                [
                    'uuid' => Uuid::randomBytes(),
                    'name' => null,
                    'technical_name' => null,
                    'expected_technical_name' => 'unnamed_profile_1',
                ],
                [
                    'uuid' => Uuid::randomBytes(),
                    'name' => null,
                    'technical_name' => null,
                    'expected_technical_name' => 'unnamed_profile_2',
                ],
            ],
        ];

        yield 'multiple profiles with already existing name' => [
            [
                [
                    'uuid' => Uuid::randomBytes(),
                    'name' => 'Default product',
                    'technical_name' => null,
                    'expected_technical_name' => 'default_product_1',
                ],
                [
                    'uuid' => Uuid::randomBytes(),
                    'name' => 'Default product',
                    'technical_name' => null,
                    'expected_technical_name' => 'default_product_2',
                ],
                [
                    'uuid' => Uuid::randomBytes(),
                    'name' => 'Default category',
                    'technical_name' => null,
                    'expected_technical_name' => 'default_category_1',
                ],
            ],
        ];

        yield 'multiple profiles with empty string and null values for name' => [
            [
                [
                    'uuid' => Uuid::randomBytes(),
                    'name' => '',
                    'technical_name' => null,
                    'expected_technical_name' => 'unnamed_profile',
                ],
                [
                    'uuid' => Uuid::randomBytes(),
                    'name' => null,
                    'technical_name' => null,
                    'expected_technical_name' => 'unnamed_profile_1',
                ],
            ],
        ];
    }

    private function insertDefaultData(): void
    {
        $sql = 'SELECT * FROM `import_export_profile`';

        $this->connection->executeStatement('DELETE FROM `import_export_profile`');
        $rows = $this->connection->fetchAllAssociative($sql);
        static::assertCount(0, $rows);

        $importExportDefaultProfilesSql = file_get_contents(__DIR__ . '/fixtures/import_export_default_profiles.sql');
        static::assertIsString($importExportDefaultProfilesSql);
        $this->connection->executeStatement($importExportDefaultProfilesSql);

        $rows = $this->connection->fetchAllAssociative($sql);
        static::assertCount(12, $rows);

        self::$hasDefaultProfiles = true;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1590758953ProductFeatureSet;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @group slow
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1590758953ProductFeatureSet
 *
 * @phpstan-type DbColumn array{name: string, type: Type, notnull: bool}
 */
class Migration1590758953ProductFeatureSetTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    /**
     * @before
     */
    public function initialise(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1590758953ProductFeatureSet();

        $connection->rollBack();

        if ($this->hasColumn($connection, 'product', 'featureSet')) {
            $connection->executeStatement('ALTER TABLE `product` DROP COLUMN `featureSet`;');
        }

        if ($this->hasColumn($connection, 'product', 'product_feature_set_id')) {
            $connection->executeStatement('ALTER TABLE `product` DROP FOREIGN KEY `fk.product.feature_set_id`;');
            $connection->executeStatement('ALTER TABLE `product` DROP INDEX `fk.product.feature_set_id`;');
            $connection->executeStatement('ALTER TABLE `product` DROP COLUMN `product_feature_set_id`;');
        }

        $connection->executeStatement('DROP TABLE IF EXISTS `product_feature_set_translation`;');
        $connection->executeStatement('DROP TABLE IF EXISTS `product_feature_set`;');

        $migration->update($connection);

        $connection->beginTransaction();
    }

    /**
     * @dataProvider tableInformationProvider
     *
     * @param DbColumn[] $expectedColumns
     */
    public function testTablesAreComplete(string $table, array $expectedColumns): void
    {
        $actualColumns = $this->fetchTableInformation($table);

        sort($actualColumns);
        sort($expectedColumns);

        static::assertEquals($expectedColumns, $actualColumns);
    }

    public function testProductTableExtensionIsComplete(): void
    {
        $columns = array_filter(
            $this->connection->getSchemaManager()->listTableColumns('product'),
            static fn (Column $column): bool => \in_array($column->getName(), ['product_feature_set_id', 'featureSet'], true)
        );

        foreach ($columns as $column) {
            static::assertEquals(new BinaryType(), $column->getType());
            static::assertFalse($column->getNotnull());
        }
    }

    public function testDefaultFeatureSetIsCreated(): void
    {
        $expectedFeature = [
            'id' => 'd45b40f6a99c4c2abe66c410369b9d3c',
            'type' => 'referencePrice',
            'name' => 'referencePrice',
            'position' => 1,
        ];
        $expectedFeatures = [$expectedFeature];

        $actual = $this->fetchDefaultFeatureSet();
        $actualFeatures = json_decode((string) $actual['features'], true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(\count($expectedFeatures), $actualFeatures);

        $actualFeature = array_pop($actualFeatures);

        static::assertCount(\count($expectedFeature), $actualFeature);

        static::assertEquals($expectedFeature['type'], $actualFeature['type']);
        static::assertEquals($expectedFeature['id'], $actualFeature['id']);
        static::assertEquals($expectedFeature['position'], $actualFeature['position']);
    }

    public function testDefaultFeatureSetTranslationIsCreated(): void
    {
        $expectedTranslations = array_values(Migration1590758953ProductFeatureSet::TRANSLATIONS);
        $actual = $this->fetchFeatureSetTranslation();

        foreach ($actual as &$translation) {
            unset(
                $translation['product_feature_set_id'],
                $translation['language_id'],
                $translation['created_at'],
                $translation['updated_at']
            );
        }
        unset($translation);

        $compareByName = static fn (array $a, array $b) => $a['name'] <=> $b['name'];

        usort($expectedTranslations, $compareByName);
        usort($actual, $compareByName);

        static::assertEquals($expectedTranslations, $actual);
    }

    /**
     * @return array{0: string, 1: DbColumn[]}[]
     */
    public function tableInformationProvider(): array
    {
        return [
            [
                ProductFeatureSetDefinition::ENTITY_NAME,
                [
                    self::getColumn('id', new BinaryType(), true),
                    self::getColumn('features', $this->getJsonType()),
                    self::getColumn('created_at', new DateTimeType(), true),
                    self::getColumn('updated_at', new DateTimeType()),
                ],
            ],
            [
                ProductFeatureSetTranslationDefinition::ENTITY_NAME,
                [
                    self::getColumn('product_feature_set_id', new BinaryType(), true),
                    self::getColumn('language_id', new BinaryType(), true),
                    self::getColumn('name', new StringType()),
                    self::getColumn('description', new TextType()),
                    self::getColumn('created_at', new DateTimeType(), true),
                    self::getColumn('updated_at', new DateTimeType()),
                ],
            ],
        ];
    }

    /**
     * @return DbColumn
     */
    private static function getColumn(string $name, Type $type, ?bool $notNull = false): array
    {
        return [
            'name' => $name,
            'type' => $type,
            'notnull' => (bool) $notNull,
        ];
    }

    /**
     * When there's no native JSON-type available, doctrine will fall back to
     * using the text type, so we need to account for that.
     *
     * @see https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/types.html#json
     */
    private function getJsonType(): Type
    {
        return KernelLifecycleManager::getConnection()
            ->getDatabasePlatform()
            ->hasNativeJsonType() ? new JsonType() : new TextType();
    }

    /**
     * @return DbColumn[]
     */
    private function fetchTableInformation(string $name): array
    {
        $columns = $this->connection
            ->getSchemaManager()
            ->listTableDetails($name)
            ->getColumns();

        return array_map(static fn (Column $column): array => self::getColumn(
            $column->getName(),
            $column->getType(),
            $column->getNotnull()
        ), $columns);
    }

    /**
     * @return array<string|int, mixed>
     */
    private function fetchDefaultFeatureSet(): array
    {
        return (array) $this->connection->fetchAssociative(
            'SELECT * FROM `product_feature_set` ORDER BY `created_at` ASC LIMIT 1;'
        );
    }

    /**
     * @return array<string, mixed>[]
     */
    private function fetchFeatureSetTranslation(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT * FROM `product_feature_set_translation` WHERE `product_feature_set_id` = :id;',
            ['id' => $this->fetchDefaultFeatureSet()['id']]
        );
    }

    private function hasColumn(Connection $connection, string $table, string $columnName): bool
    {
        return \count(array_filter(
            $connection->getSchemaManager()->listTableColumns($table),
            static fn (Column $column): bool => $column->getName() === $columnName
        )) > 0;
    }
}

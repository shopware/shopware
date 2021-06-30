<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

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
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\Migration1590758953ProductFeatureSet;

/**
 * @group slow
 */
class Migration1590758953ProductFeatureSetTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
    }

    /**
     * @before
     */
    public function initialise(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $migration = new Migration1590758953ProductFeatureSet();

        $connection->rollBack();

        if ($this->hasColumn($connection, 'product', 'featureSet')) {
            $connection->executeUpdate('ALTER TABLE `product` DROP COLUMN `featureSet`;');
        }

        if ($this->hasColumn($connection, 'product', 'product_feature_set_id')) {
            $connection->executeUpdate('ALTER TABLE `product` DROP FOREIGN KEY `fk.product.feature_set_id`;');
            $connection->executeUpdate('ALTER TABLE `product` DROP INDEX `fk.product.feature_set_id`;');
            $connection->executeUpdate('ALTER TABLE `product` DROP COLUMN `product_feature_set_id`;');
        }

        $connection->executeUpdate('DROP TABLE IF EXISTS `product_feature_set_translation`;');
        $connection->executeUpdate('DROP TABLE IF EXISTS `product_feature_set`;');

        $migration->update($connection);

        $connection->beginTransaction();
    }

    /**
     * @dataProvider tableInformationProvider
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
            static function (Column $column): bool {
                return \in_array($column->getName(), ['product_feature_set_id', 'featureSet'], true);
            }
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
        $actualFeatures = json_decode($actual['features'], true);

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

        $compareByName = static function (array $a, array $b) {
            return $a['name'] <=> $b['name'];
        };

        usort($expectedTranslations, $compareByName);
        usort($actual, $compareByName);

        static::assertEquals($expectedTranslations, $actual);
    }

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

    private static function getColumn(string $name, Type $type, ?bool $notNull = false): array
    {
        return [
            'name' => $name,
            'type' => $type,
            'notnull' => $notNull,
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
        return $this->getContainer()
            ->get(Connection::class)
            ->getDatabasePlatform()
            ->hasNativeJsonType() ? new JsonType() : new TextType();
    }

    private function fetchTableInformation(string $name): array
    {
        $columns = $this->connection
            ->getSchemaManager()
            ->listTableDetails($name)
            ->getColumns();

        return array_map(static function (Column $column): array {
            return self::getColumn(
                $column->getName(),
                $column->getType(),
                $column->getNotnull()
            );
        }, $columns);
    }

    private function fetchDefaultFeatureSet(): array
    {
        return $this->connection->fetchAssoc(
            'SELECT * FROM `product_feature_set` ORDER BY `created_at` ASC LIMIT 1;'
        );
    }

    private function fetchFeatureSetTranslation(): array
    {
        return $this->connection->fetchAll(
            'SELECT * FROM `product_feature_set_translation` WHERE `product_feature_set_id` = :id;',
            $this->fetchDefaultFeatureSet()
        );
    }

    private function hasColumn(Connection $connection, string $table, string $columnName): bool
    {
        return \count(array_filter(
            $connection->getSchemaManager()->listTableColumns($table),
            static function (Column $column) use ($columnName): bool {
                return $column->getName() === $columnName;
            }
        )) > 0;
    }
}

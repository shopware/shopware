<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductFeature\ProductFeatureDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\Migration1590758953ProductFeatureSet;

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

        $connection->executeUpdate('DROP TABLE IF EXISTS `product_feature`;');
        $connection->executeUpdate('DROP TABLE IF EXISTS `product_feature_set_translation`;');
        $connection->executeUpdate('DROP TABLE IF EXISTS `product_feature_set`;');

        $migration->update($connection);
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

    public function testDefaultFeatureSetIsCreated(): void
    {
        $expected = [
            'features' => [
                'type' => 'product',
                'id' => 'referencePrice',
                'position' => 1,
            ],
        ];
        $actual = $this->fetchFeatureSet();
        $actualFeatures = json_decode($actual['features'], true);

        static::assertCount(3, $actualFeatures);
        static::assertEquals($expected['features']['type'], $actualFeatures['type']);
        static::assertEquals($expected['features']['id'], $actualFeatures['id']);
        static::assertEquals($expected['features']['position'], $actualFeatures['position']);
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
            [
                ProductFeatureDefinition::ENTITY_NAME,
                [
                    self::getColumn('product_feature_set_id', new BinaryType(), true),
                    self::getColumn('product_id', new BinaryType(), true),
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

    private function fetchFeatureSet(): array
    {
        return $this->connection->fetchAssoc(
            'SELECT * FROM `product_feature_set` ORDER BY `created_at` ASC LIMIT 1;'
        );
    }
}

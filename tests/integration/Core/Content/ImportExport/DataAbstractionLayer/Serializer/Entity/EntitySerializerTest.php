<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\EntitySerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Processing\Mapping\Mapping;
use Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('services-settings')]
class EntitySerializerTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour;
    use KernelTestBehaviour;

    public function testSupportsAll(): void
    {
        $serializer = new EntitySerializer();

        $definitionRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
        foreach ($definitionRegistry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();
            static::assertTrue(
                $serializer->supports($definition->getEntityName()),
                EntitySerializer::class . ' should support ' . $entity
            );
        }
    }

    public function testEnsureIdFields(): void
    {
        /** @var EntityDefinition $productDefinition */
        $productDefinition = $this->getContainer()->get(ProductDefinition::class);

        [$expectedData, $importData] = require __DIR__ . '/_fixtures/ensure_ids_for_products.php';

        $serializer = new EntitySerializer();
        $serializerRegistry = $this->getContainer()->get(SerializerRegistry::class);
        $serializer->setRegistry($serializerRegistry);
        $return = $serializer->deserialize(new Config([], [], []), $productDefinition, $importData);
        $return = \is_array($return) ? $return : iterator_to_array($return);

        static::assertSame($expectedData, $return);
    }

    public function testEnsureIdFieldsWithMixedContent(): void
    {
        /** @var EntityDefinition $productDefinition */
        $productDefinition = $this->getContainer()->get(ProductDefinition::class);

        [$expectedData, $importData] = require __DIR__ . '/_fixtures/ensure_ids_for_products.php';
        $importData['tax'] = [
            'id' => Uuid::randomHex(),
        ];
        $expectedData['categories'] = [
            [
                'id' => Uuid::randomHex(),
            ],
            [
                'id' => Uuid::randomHex(),
            ],
            [
                'id' => Uuid::randomHex(),
            ],
        ];
        $importData['categories'] = implode('|', array_column($expectedData['categories'], 'id'));
        $expectedData['tax'] = $importData['tax'];

        $serializer = new EntitySerializer();
        $serializerRegistry = $this->getContainer()->get(SerializerRegistry::class);
        $serializer->setRegistry($serializerRegistry);
        $return = $serializer->deserialize(new Config([], [], []), $productDefinition, $importData);
        $return = \is_array($return) ? $return : iterator_to_array($return);

        static::assertSame($expectedData, $return);
    }

    /**
     * @param array<string, string> $value
     * @param string|bool|int|array<string, string>|\DateTimeImmutable $expectedValue
     */
    #[DataProvider('validValues')]
    public function testDeserialize(array $value, string $key, $expectedValue): void
    {
        $mapping = $this->getDefaultMapping();

        $entity = \array_merge($this->getDefaultEntityArray(), $value);

        $productDefinition = $this->getContainer()->get(ProductDefinition::class);

        $entitySerializer = new EntitySerializer();
        $entitySerializer->setRegistry($this->getContainer()->get(SerializerRegistry::class));

        $result = $entitySerializer->deserialize(new Config($mapping, [], []), $productDefinition, $entity);
        $result = \is_array($result) ? $result : iterator_to_array($result);

        static::assertArrayHasKey($key, $result);
        if ($key === 'releaseDate' && $expectedValue instanceof \DateTimeInterface) {
            static::assertInstanceOf(\DateTimeInterface::class, $result[$key]);
            static::assertSame($expectedValue->format('Y-m-d'), $result[$key]->format('Y-m-d'));
        } else {
            static::assertSame($expectedValue, $result[$key]);
        }
    }

    /**
     * @return iterable<array{value: array<string, string>, key: string, expectedValue: string|bool|int|array<string, string>|\DateTimeImmutable}>
     */
    public static function validValues(): iterable
    {
        yield 'valid Uuid' => [
            'value' => ['id' => '2629dd04f6c244698cd6b1cd07b8040a'],
            'key' => 'id',
            'expectedValue' => '2629dd04f6c244698cd6b1cd07b8040a',
        ];

        yield 'valid Boolean' => [
            'value' => ['active' => 'true'],
            'key' => 'active',
            'expectedValue' => true,
        ];

        yield 'valid Integer' => [
            'value' => ['stock' => '12345'],
            'key' => 'stock',
            'expectedValue' => 12345,
        ];

        yield 'valid JSON' => [
            'value' => ['variantRestrictions' => '{"key": "value"}'],
            'key' => 'variantRestrictions',
            'expectedValue' => ['key' => 'value'],
        ];

        yield 'valid Date' => [
            'value' => ['releaseDate' => '2024-02-28'],
            'key' => 'releaseDate',
            'expectedValue' => new \DateTimeImmutable('2024-02-28'),
        ];
    }

    public function testDeserializeWithEmptyDateString(): void
    {
        $mapping = $this->getDefaultMapping();

        $entity = \array_merge($this->getDefaultEntityArray(), ['releaseDate' => ' ']);

        $productDefinition = $this->getContainer()->get(ProductDefinition::class);

        $entitySerializer = new EntitySerializer();
        $entitySerializer->setRegistry($this->getContainer()->get(SerializerRegistry::class));

        $result = $entitySerializer->deserialize(new Config($mapping, [], []), $productDefinition, $entity);
        $result = \is_array($result) ? $result : iterator_to_array($result);

        static::assertArrayNotHasKey('releaseDate', $result);
    }

    /**
     * @param array<string, string> $value
     */
    #[DataProvider('brokenValues')]
    public function testDeserializeShouldAddErrorColumn(array $value, string $expectedErrorMessage): void
    {
        $mapping = $this->getDefaultMapping();

        $entity = \array_merge($this->getDefaultEntityArray(), $value);

        $productDefinition = $this->getContainer()->get(ProductDefinition::class);

        $entitySerializer = new EntitySerializer();
        $entitySerializer->setRegistry($this->getContainer()->get(SerializerRegistry::class));

        $result = $entitySerializer->deserialize(new Config($mapping, [], []), $productDefinition, $entity);
        $result = \is_array($result) ? $result : iterator_to_array($result);

        static::assertArrayHasKey('_error', $result);
        static::assertInstanceOf(ImportExportException::class, $result['_error']);
        static::assertSame($expectedErrorMessage, $result['_error']->getMessage());
    }

    /**
     * @return iterable<array{value: array<string, string>, expectedErrorMessage: string}>
     */
    public static function brokenValues(): iterable
    {
        yield 'invalid Uuid' => [
            'value' => ['id' => '1ab98a64fcb64d|2cb08321a122deacc1'],
            'expectedErrorMessage' => 'Deserialization failed for field "id" with value "1ab98a64fcb64d|2cb08321a122deacc1" to type "uuid"',
        ];

        yield 'invalid Boolean' => [
            'value' => ['active' => 'invalidBoolean'],
            'expectedErrorMessage' => 'Deserialization failed for field "active" with value "invalidBoolean" to type "boolean"',
        ];

        yield 'invalid Integer' => [
            'value' => ['stock' => 'asd12asd'],
            'expectedErrorMessage' => 'Deserialization failed for field "stock" with value "asd12asd" to type "integer"',
        ];

        yield 'invalid JSON' => [
            'value' => ['variantRestrictions' => '{"key": "value"'],
            'expectedErrorMessage' => 'Deserialization failed for field "variantRestrictions" with value "{"key": "value"" to type "json"',
        ];

        yield 'invalid Date' => [
            'value' => ['releaseDate' => '2024-02-39'],
            'expectedErrorMessage' => 'Deserialization failed for field "releaseDate" with value "2024-02-39" to type "date"',
        ];
    }

    public function testEntityExtensionSerialization(): void
    {
        // add temporary db table for the test extension
        $connection = $this->getContainer()->get(Connection::class);
        $migration = new TestExtensionMigration();
        $migration->update($connection);
        $connection->setNestTransactionsWithSavepoints(true);
        $connection->beginTransaction(); // do everything in a transaction

        // setup test extension
        $this->registerDefinition(TestExtensionDefinition::class);
        $this->registerDefinitionWithExtensions(ProductDefinition::class, TestExtension::class);

        // create a product with extension data
        $productRepo = $this->getContainer()->get('product.repository');
        $taxCriteria = new Criteria();
        $taxCriteria->addFilter(new EqualsFilter('taxRate', 19.0));
        $taxId = $this->getContainer()->get('tax.repository')->searchIds($taxCriteria, Context::createDefaultContext())->firstId();
        $productId = Uuid::randomHex();
        $productRepo->create([
            [
                'id' => $productId,
                'name' => 'testProductWithExtension',
                'productNumber' => 'testProductNumberWithExtension',
                'stock' => 42,
                'price' => [
                    [
                        'currencyId' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                        'net' => 42,
                        'linked' => false,
                        'gross' => 64,
                    ],
                ],
                'taxId' => $taxId,
                'testExtension' => [
                    'customString' => 'hello world',
                ],
            ],
        ], Context::createDefaultContext());

        // fetch a product with extension data
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('testExtension');
        $exportData = $productRepo->search($criteria, Context::createDefaultContext())->first();

        // do the serialization
        /** @var EntityDefinition $productDefinition */
        $productDefinition = $this->getContainer()->get(ProductDefinition::class);
        $serializer = new EntitySerializer();
        $serializerRegistry = $this->getContainer()->get(SerializerRegistry::class);
        $serializer->setRegistry($serializerRegistry);
        $return = $serializer->serialize(new Config([], [], []), $productDefinition, $exportData);
        $return = iterator_to_array($return);

        // cleanup test extension db table
        $connection->rollBack(); // rollback the transaction
        $migration->updateDestructive($connection); // remove the extension db table

        // check if the serialization works
        static::assertArrayHasKey('testExtension', $return);
        $testExtension = $return['testExtension'];
        static::assertIsArray($testExtension);
        static::assertSame($productId, $testExtension['productId']);
        static::assertSame('hello world', $testExtension['customString']);
    }

    private function getDefaultMapping(): MappingCollection
    {
        return new MappingCollection([
            new Mapping('id', 'id'),
            new Mapping('active', 'active'),
            new Mapping('stock', 'stock'),
            new Mapping('variant_restrictions', 'variantRestrictions'),
            new Mapping('release_date', 'releaseDate'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function getDefaultEntityArray(): array
    {
        return [
            'id' => Uuid::randomHex(),
            'active' => 'true',
            'stock' => '10',
            'variantRestrictions' => '{}',
            'releaseDate' => '2021-03-05',
        ];
    }
}

/**
 * @internal
 */
class TestExtensionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'test_extension';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new FkField('product_id', 'productId', ProductDefinition::class),
            new StringField('custom_string', 'customString'),

            new OneToOneAssociationField('product', 'product_id', 'id', ProductDefinition::class, false),
        ]);
    }

    public function since(): ?string
    {
        return '6.4.3.0';
    }
}

/**
 * @internal
 */
class TestExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToOneAssociationField('testExtension', 'id', 'product_id', TestExtensionDefinition::class, true)
        );
    }

    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }
}

/**
 * @internal
 */
class TestExtensionMigration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1614903457;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `test_extension` (
    `id` BINARY(16) NOT NULL,
    `product_id` BINARY(16) NULL,
    `custom_string` VARCHAR(255) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        $sql = <<<'SQL'
DROP TABLE IF EXISTS `test_extension`;
SQL;
        $connection->executeStatement($sql);
    }
}

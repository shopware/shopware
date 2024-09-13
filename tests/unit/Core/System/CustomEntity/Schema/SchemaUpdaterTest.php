<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Schema\SchemaUpdater;

/**
 * @internal
 */
#[CoversClass(SchemaUpdater::class)]
class SchemaUpdaterTest extends TestCase
{
    public function testDefaultFields(): void
    {
        $entity = [
            'name' => 'custom_entity_empty_entity',
            'fields' => '[]',
        ];
        $schema = new Schema();

        $updater = new SchemaUpdater();
        $updater->applyCustomEntities($schema, [$entity]);

        $this->assertColumns($schema, 'custom_entity_empty_entity', ['id', 'created_at', 'updated_at']);
    }

    public function testShortPrefix(): void
    {
        $entity = [
            'name' => 'ce_empty_entity',
            'fields' => '[]',
        ];
        $schema = new Schema();

        $updater = new SchemaUpdater();
        $updater->applyCustomEntities($schema, [$entity]);

        $this->assertColumns($schema, 'ce_empty_entity', ['id', 'created_at', 'updated_at']);
    }

    public function testExtendingExistingTables(): void
    {
        $schema = new Schema([
            new Table('product', [
                new Column('id', Type::getType(Types::BINARY)),
                new Column('version_id', Type::getType(Types::BINARY)),
            ]),
        ]);

        $customEntity = [
            'name' => 'custom_entity_extension',
            'fields' => '[{"name":"product","reference":"product","onDelete":"set-null","inherited":true,"type":"one-to-one"}]',
        ];

        $updater = new SchemaUpdater();
        $updater->applyCustomEntities($schema, [$customEntity]);

        $this->assertColumns($schema, 'product', ['customentityextensionproduct']);

        $productTable = $schema->getTable('product');
        $columnComment = $productTable->getColumn('customentityextensionproduct')->getComment();
        static::assertSame('custom-entity-element', $columnComment);
    }

    public function testBlogExample(): void
    {
        $entities = [
            [
                'name' => 'custom_entity_blog',
                'fields' => '[{"name":"position","storeApiAware":true,"type":"int","required":false},{"name":"rating","storeApiAware":true,"type":"float","required":false},{"name":"title","storeApiAware":true,"type":"string","translatable":true,"required":true},{"name":"content","storeApiAware":true,"allowHtml":true,"type":"text","translatable":true,"required":false},{"name":"products","storeApiAware":true,"reference":"product","inherited":false,"onDelete":"cascade","type":"many-to-many"},{"name":"top_seller","storeApiAware":true,"reference":"product","inherited":false,"onDelete":"set-null","type":"many-to-one","required":false},{"name":"comments","storeApiAware":true,"reference":"custom_entity_blog_comment","inherited":false,"onDelete":"set-null","type":"one-to-many","reverseRequired":false},{"name":"author","storeApiAware":false,"reference":"user","inherited":false,"onDelete":"set-null","type":"one-to-one","required":false}]',
            ],
            [
                'name' => 'custom_entity_blog_comment',
                'fields' => '[{"name":"title","storeApiAware":true,"type":"string","translatable":true,"required":true},{"name":"products","storeApiAware":true,"reference":"product","inherited":false,"onDelete":"set-null","type":"one-to-many","reverseRequired":false}]',
            ],
        ];

        $schema = new Schema([
            new Table('product', [
                new Column('id', Type::getType(Types::BINARY)),
                new Column('version_id', Type::getType(Types::BINARY)),
            ]),
            new Table('user', [new Column('id', Type::getType(Types::BINARY))]),
            new Table('language', [new Column('id', Type::getType(Types::BINARY))]),
        ]);

        $updater = new SchemaUpdater();
        $updater->applyCustomEntities($schema, $entities);

        $this->assertColumns($schema, 'custom_entity_blog', ['id', 'top_seller_id', 'author_id', 'created_at', 'updated_at', 'position', 'rating']);
        $this->assertColumns($schema, 'custom_entity_blog_comment', ['id', 'created_at', 'updated_at']);
    }

    /**
     * @param list<array{name: string, fields: string}> $entities
     * @param array<string, list<string>> $expectedSchema
     */
    #[DataProvider('associationPairsProvider')]
    public function testAssociations(array $entities, array $expectedSchema): void
    {
        $schema = new Schema();

        $updater = new SchemaUpdater();
        $updater->applyCustomEntities($schema, $entities);

        foreach ($expectedSchema as $tableName => $columns) {
            $this->assertColumns($schema, $tableName, $columns);
        }
    }

    public static function associationPairsProvider(): \Generator
    {
        $oneToOnePair = [
            [
                'name' => 'custom_entity_right',
                'fields' => '[]',
            ],
            [
                'name' => 'custom_entity_left',
                'fields' => '[{"name":"right","reference":"custom_entity_right","onDelete":"set-null","inherited":false,"type":"one-to-one"}]',
            ],
        ];
        yield 'testOneToOne' => [
            'entities' => $oneToOnePair,
            'expectedSchema' => [
                'custom_entity_left' => ['right_id'],
            ],
        ];
        yield 'testOneToOneReverse' => [
            'entities' => \array_reverse($oneToOnePair),
            'expectedSchema' => [
                'custom_entity_left' => ['right_id'],
            ],
        ];
        $oneToManyPair = [
            [
                'name' => 'custom_entity_one',
                'fields' => '[{"name":"many","reference":"custom_entity_many","onDelete":"cascade","inherited":false,"type":"one-to-many"}]',
            ],
            [
                'name' => 'custom_entity_many',
                'fields' => '[{"name":"one","reference":"custom_entity_one","onDelete":"cascade","inherited":false,"type":"many-to-one"}]',
            ],
        ];
        yield 'testOneToMany' => [
            'entities' => $oneToManyPair,
            'expectedSchema' => [
                'custom_entity_many' => ['one_id'],
            ],
        ];
        yield 'testOneToManyReverse' => [
            'entities' => \array_reverse($oneToManyPair),
            'expectedSchema' => [
                'custom_entity_many' => ['one_id'],
            ],
        ];
        $manyToManyPair = [
            [
                'name' => 'custom_entity_left',
                'fields' => '[{"name":"rights","reference":"custom_entity_right","onDelete":"set-null","inherited":false,"type":"many-to-many"}]',
            ],
            [
                'name' => 'custom_entity_right',
                'fields' => '[]',
            ],
        ];
        yield 'testManyToMany' => [
            'entities' => $manyToManyPair,
            'expectedSchema' => [
                'custom_entity_left_rights' => ['custom_entity_left_id', 'custom_entity_right_id'],
            ],
        ];
        yield 'testManyToManyReverse' => [
            'entities' => \array_reverse($manyToManyPair),
            'expectedSchema' => [
                'custom_entity_left_rights' => ['custom_entity_left_id', 'custom_entity_right_id'],
            ],
        ];
    }

    /**
     * @param list<string> $columns
     */
    private function assertColumns(Schema $schema, string $table, array $columns): void
    {
        static::assertTrue($schema->hasTable($table), \sprintf('Table %s do not exists', $table));

        $existing = \array_keys($schema->getTable($table)->getColumns());

        foreach ($columns as $column) {
            // strtolower required for assertContains
            static::assertContains(\strtolower($column), $existing, 'Column ' . $column . ' not found in table ' . $table . ': ' . \print_r($existing, true));
        }
    }
}

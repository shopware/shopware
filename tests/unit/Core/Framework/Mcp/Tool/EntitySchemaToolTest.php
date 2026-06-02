<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\EntitySchemaTool;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntitySchemaTool::class)]
class EntitySchemaToolTest extends TestCase
{
    public function testReturnsAllFieldTypesCorrectly(): void
    {
        $definition = new RichTestEntityDefinition();
        $definition->compile($this->createMock(DefinitionInstanceRegistry::class));

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->with('rich_test')->willReturn($definition);

        $tool = new EntitySchemaTool($registry);
        $result = json_decode(($tool)('rich_test'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertSame('rich_test', $result['data']['entity']);

        $fields = $result['data']['fields'];
        $fieldMap = [];
        foreach ($fields as $field) {
            $fieldMap[$field['name']] = $field;
        }

        static::assertSame('uuid', $fieldMap['id']['type']);
        static::assertTrue($fieldMap['id']['required']);
        static::assertSame('string', $fieldMap['name']['type']);
        static::assertSame('bool', $fieldMap['active']['type']);
        static::assertSame('int', $fieldMap['position']['type']);
        static::assertSame('float', $fieldMap['price']['type']);
        static::assertSame('datetime', $fieldMap['createdAt']['type']);
        static::assertSame('json', $fieldMap['config']['type']);
        static::assertSame('fk', $fieldMap['parentId']['type']);
    }

    public function testReturnsAssociationsCorrectly(): void
    {
        $definition = new RichTestEntityDefinition();
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $definition->compile($registry);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->with('rich_test')->willReturn($definition);

        $tool = new EntitySchemaTool($registry);
        $result = json_decode(($tool)('rich_test'), true, 512, \JSON_THROW_ON_ERROR);

        $associations = $result['data']['associations'];
        $assocMap = [];
        foreach ($associations as $assoc) {
            $assocMap[$assoc['name']] = $assoc;
        }

        static::assertSame('many-to-one', $assocMap['parent']['type']);
        static::assertSame('one-to-many', $assocMap['children']['type']);
        static::assertSame('many-to-many', $assocMap['tags']['type']);
        static::assertSame('one-to-one', $assocMap['detail']['type']);
    }

    public function testFieldWithoutRequiredFlagIsNotRequired(): void
    {
        $definition = new RichTestEntityDefinition();
        $definition->compile($this->createMock(DefinitionInstanceRegistry::class));

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn($definition);

        $tool = new EntitySchemaTool($registry);
        $result = json_decode(($tool)('rich_test'), true, 512, \JSON_THROW_ON_ERROR);

        $fields = $result['data']['fields'];
        $activeField = null;
        foreach ($fields as $field) {
            if ($field['name'] === 'active') {
                $activeField = $field;
            }
        }

        static::assertNotNull($activeField);
        static::assertFalse($activeField['required']);
    }

    public function testUnknownEntityReturnsError(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(false);
        $registry->expects($this->never())->method('getByEntityName');

        $tool = new EntitySchemaTool($registry);
        $result = json_decode(($tool)('unknown_entity'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($result['success']);
        static::assertStringContainsString('unknown_entity', $result['error']);
        static::assertStringContainsString('shopware://entities', $result['error']);
    }
}

/**
 * @internal
 */
class RichTestEntityDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'rich_test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            new BoolField('active', 'active'),
            new IntField('position', 'position'),
            new FloatField('price', 'price'),
            new DateTimeField('created_at', 'createdAt'),
            new JsonField('config', 'config'),
            new FkField('parent_id', 'parentId', self::class),
            new ManyToOneAssociationField('parent', 'parent_id', self::class),
            new OneToManyAssociationField('children', self::class, 'parent_id'),
            new ManyToManyAssociationField('tags', self::class, self::class, 'source_id', 'target_id'),
            new OneToOneAssociationField('detail', 'detail_id', 'id', self::class),
        ]);
    }
}

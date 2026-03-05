<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
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
    public function testReturnsFieldsAndAssociations(): void
    {
        $definition = new TestEntityDefinition();
        $definition->compile($this->createMock(DefinitionInstanceRegistry::class));

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->with('test_entity')->willReturn($definition);

        $tool = new EntitySchemaTool($registry);
        $result = json_decode(($tool)('test_entity'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertSame('test_entity', $result['data']['entity']);
        static::assertNotEmpty($result['data']['fields']);

        $fieldNames = array_column($result['data']['fields'], 'name');
        static::assertContains('id', $fieldNames);
        static::assertContains('name', $fieldNames);
        static::assertContains('active', $fieldNames);
    }

    public function testOutputIsValidJson(): void
    {
        $definition = new TestEntityDefinition();
        $definition->compile($this->createMock(DefinitionInstanceRegistry::class));

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->with('test_entity')->willReturn($definition);

        $tool = new EntitySchemaTool($registry);
        $result = ($tool)('test_entity');

        static::assertJson($result);
        $data = json_decode($result, true);
        static::assertTrue($data['success']);
        static::assertArrayHasKey('entity', $data['data']);
        static::assertArrayHasKey('fields', $data['data']);
        static::assertArrayHasKey('associations', $data['data']);
    }
}

/**
 * @internal
 */
class TestEntityDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_entity';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            new BoolField('active', 'active'),
        ]);
    }
}

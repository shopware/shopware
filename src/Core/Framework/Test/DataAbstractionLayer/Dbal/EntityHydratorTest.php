<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class EntityHydratorTest extends TestCase
{
    use KernelTestBehaviour;

    public function testFkExtensionFieldHydration(): void
    {
        $definition = new FkExtensionFieldTest();
        $definition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $hydrator = new EntityHydrator();

        $id = Uuid::randomBytes();
        $normal = Uuid::randomBytes();
        $extended = Uuid::randomBytes();

        $rows = [
            [
                'test.id' => $id,
                'test.name' => 'test',
                'test.normalFk' => $normal,
                'test.extendedFk' => $extended,
            ],
        ];

        $structs = $hydrator->hydrate(new EntityCollection(), ArrayEntity::class, $definition, $rows, 'test', Context::createDefaultContext());
        static::assertCount(1, $structs);

        $first = $structs->first();

        static::assertInstanceOf(ArrayEntity::class, $first);

        /** @var ArrayEntity $first */
        static::assertSame('test', $first->get('name'));

        static::assertSame(Uuid::fromBytesToHex($id), $first->get('id'));
        static::assertSame(Uuid::fromBytesToHex($normal), $first->get('normalFk'));

        static::assertTrue($first->hasExtension(EntityReader::FOREIGN_KEYS));
        $foreignKeys = $first->getExtension(EntityReader::FOREIGN_KEYS);

        static::assertInstanceOf(ArrayStruct::class, $foreignKeys);

        /** @var ArrayEntity $foreignKeys */
        static::assertTrue($foreignKeys->has('extendedFk'));
        static::assertSame(Uuid::fromBytesToHex($extended), $foreignKeys->get('extendedFk'));
    }
}

class FkExtensionFieldTest extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'fk_extension_test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            new StringField('name', 'name'),
            new FkField('normal_fk', 'normalFk', ProductDefinition::class),

            (new FkField('extended_fk', 'extendedFk', ProductDefinition::class))
                ->addFlags(new Extension()),
        ]);
    }
}

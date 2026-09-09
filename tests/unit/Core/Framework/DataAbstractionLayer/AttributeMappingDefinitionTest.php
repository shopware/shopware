<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeMappingDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AttributeMappingDefinition::class)]
class AttributeMappingDefinitionTest extends TestCase
{
    public function testFieldsAreBuiltFromTheMeta(): void
    {
        $definition = new AttributeMappingDefinition([
            'entity_name' => 'foo_bar_mapping',
            'source' => 'attribute_mapping_test_source',
            'reference' => 'attribute_mapping_test_reference',
            'fields' => [
                [
                    'class' => FkField::class,
                    'args' => ['source_id', 'sourceId', 'attribute_mapping_test_source'],
                    'flags' => [
                        PrimaryKey::class => ['class' => PrimaryKey::class],
                        Required::class => ['class' => Required::class],
                    ],
                ],
                [
                    'class' => FkField::class,
                    'args' => ['reference_id', 'referenceId', 'attribute_mapping_test_reference'],
                ],
                [
                    // entries without a field class are skipped
                    'args' => ['ignored'],
                ],
            ],
        ]);

        new StaticDefinitionInstanceRegistry(
            [
                $definition,
                AttributeMappingTestSourceDefinition::class,
                AttributeMappingTestReferenceDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        static::assertSame('foo_bar_mapping', $definition->getEntityName());

        $fields = $definition->getFields();

        $sourceId = $fields->get('sourceId');
        static::assertInstanceOf(FkField::class, $sourceId);
        static::assertTrue($sourceId->is(PrimaryKey::class));
        static::assertTrue($sourceId->is(Required::class));

        $referenceId = $fields->get('referenceId');
        static::assertInstanceOf(FkField::class, $referenceId);
        static::assertFalse($referenceId->is(PrimaryKey::class));

        // the version-aware source gets a reference version field, the plain reference does not
        $sourceVersion = $fields->get('attributeMappingTestSourceVersionId');
        static::assertInstanceOf(ReferenceVersionField::class, $sourceVersion);
        static::assertTrue($sourceVersion->is(PrimaryKey::class));
        static::assertNull($fields->get('attributeMappingTestReferenceVersionId'));

        static::assertCount(3, $fields);
    }
}

/**
 * @internal
 */
class AttributeMappingTestSourceDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'attribute_mapping_test_source';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey()),
            new VersionField(),
        ]);
    }
}

/**
 * @internal
 */
class AttributeMappingTestReferenceDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'attribute_mapping_test_reference';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey()),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(EntityDefinition::class)]
class EntityDefinitionTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetFieldsLegacyBehaviour(): void
    {
        $definition = new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'test-definition';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([
                    // Old behaviour: New UpdatedAtField is overwritten by the default field
                    (new UpdatedAtField())->setDescription('This is a test'),
                ]);
            }
        };
        $definition->compile($this->createMock(DefinitionInstanceRegistry::class));

        $updatedAtField = $definition->getFields()->get('updatedAt');
        static::assertInstanceOf(UpdatedAtField::class, $updatedAtField);
        // Default UpdatedAtField has no description
        static::assertSame('', $updatedAtField->getDescription());
    }

    public function testGetFieldsOverridesDefaultFields(): void
    {
        $definition = new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'test-definition';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([
                    // New UpdatedAtField overwrites the default field
                    (new UpdatedAtField())->setDescription('This is a test'),
                ]);
            }
        };
        $definition->compile($this->createMock(DefinitionInstanceRegistry::class));

        $updatedAtField = $definition->getFields()->get('updatedAt');
        static::assertInstanceOf(UpdatedAtField::class, $updatedAtField);
        static::assertSame('This is a test', $updatedAtField->getDescription());
    }
}

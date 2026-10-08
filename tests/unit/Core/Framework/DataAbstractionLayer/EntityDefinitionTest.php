<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityDefinition::class)]
class EntityDefinitionTest extends TestCase
{
    public function testInheritedConstructorDoesNotSignalDeprecationWhenInstantiatedFromAnotherConstructor(): void
    {
        $caller = new class {
            public EntityDefinition $definition;

            public function __construct()
            {
                $this->definition = new class extends EntityDefinition {
                    public const ENTITY_NAME = 'test_definition';

                    public function getEntityName(): string
                    {
                        return self::ENTITY_NAME;
                    }

                    protected function defineFields(): FieldCollection
                    {
                        return new FieldCollection();
                    }
                };
            }
        };

        static::assertSame('test_definition', $caller->definition->getEntityName());
    }

    public function testConstructorSignalsDeprecationWhenCalledByChildConstructor(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: ' . Feature::deprecatedMethodMessage(EntityDefinition::class, '__construct', 'v6.8.0.0')
        ));

        /** @phpstan-ignore-next-line - Intentionally testing the deprecated parent constructor. */
        new class extends EntityDefinition {
            public const ENTITY_NAME = 'test_definition';

            public function __construct()
            {
                parent::__construct();
            }

            public function getEntityName(): string
            {
                return self::ENTITY_NAME;
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection();
            }
        };
    }

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
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

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
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $updatedAtField = $definition->getFields()->get('updatedAt');
        static::assertInstanceOf(UpdatedAtField::class, $updatedAtField);
        static::assertSame('This is a test', $updatedAtField->getDescription());
    }
}

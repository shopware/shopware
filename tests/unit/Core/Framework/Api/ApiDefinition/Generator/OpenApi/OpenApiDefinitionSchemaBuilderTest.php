<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SimpleDefinition;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\_fixtures\ComplexDefinition;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\_fixtures\SimpleExtendedDefinition;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(OpenApiDefinitionSchemaBuilder::class)]
class OpenApiDefinitionSchemaBuilderTest extends TestCase
{
    private OpenApiDefinitionSchemaBuilder $schemaBuilder;

    private StaticDefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->schemaBuilder = new OpenApiDefinitionSchemaBuilder();
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [
                SimpleDefinition::class,
                ComplexDefinition::class,
                SimpleExtendedDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }

    public function testEntityNameConversion(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(SimpleDefinition::class),
            '/simple',
            false
        );
        static::assertArrayHasKey('Simple', $schema);
        static::assertArrayHasKey('SimpleJsonApi', $schema);
    }

    public function testAssociationSchemas(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(ComplexDefinition::class),
            '/complex',
            false
        );
        static::assertArrayHasKey('Complex', $schema);
        static::assertArrayHasKey('ComplexJsonApi', $schema);
    }

    public function testTypeConversion(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(SimpleDefinition::class),
            '/simple',
            false
        );
        $properties = json_decode($schema['Simple']->toJson(), true, \JSON_THROW_ON_ERROR, \JSON_THROW_ON_ERROR)['properties'];
        static::assertArrayHasKey('id', $properties);
        static::assertArrayHasKey('type', $properties['id']);
        static::assertEquals('string', $properties['id']['type']);
        static::assertArrayHasKey('pattern', $properties['id']);
        static::assertEquals('^[0-9a-f]{32}$', $properties['id']['pattern']);
        static::assertArrayHasKey('stringField', $properties);
        static::assertArrayHasKey('type', $properties['stringField']);
        static::assertEquals('string', $properties['stringField']['type']);
        static::assertArrayHasKey('intField', $properties);
        static::assertArrayHasKey('type', $properties['intField']);
        static::assertEquals('integer', $properties['intField']['type']);
        static::assertArrayHasKey('format', $properties['intField']);
        static::assertEquals('int64', $properties['intField']['format']);
        static::assertArrayHasKey('floatField', $properties);
        static::assertArrayHasKey('type', $properties['floatField']);
        static::assertEquals('number', $properties['floatField']['type']);
        static::assertArrayHasKey('format', $properties['floatField']);
        static::assertEquals('float', $properties['floatField']['format']);
        static::assertArrayHasKey('boolField', $properties);
        static::assertArrayHasKey('type', $properties['boolField']);
        static::assertEquals('boolean', $properties['boolField']['type']);
        static::assertArrayHasKey('childCount', $properties);
        static::assertArrayHasKey('type', $properties['childCount']);
        static::assertEquals('integer', $properties['childCount']['type']);
        static::assertArrayHasKey('format', $properties['childCount']);
        static::assertEquals('int64', $properties['childCount']['format']);
    }

    public function testFlagConversion(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(SimpleDefinition::class),
            '/simple',
            false
        );
        $properties = json_decode($schema['Simple']->toJson(), true, \JSON_THROW_ON_ERROR, \JSON_THROW_ON_ERROR)['properties'];

        static::assertArrayHasKey('requiredField', $properties);
        static::assertArrayHasKey('readOnlyField', $properties);
        static::assertArrayHasKey('readOnly', $properties['readOnlyField']);
        static::assertTrue($properties['readOnlyField']['readOnly']);
        static::assertArrayHasKey('runtimeField', $properties);
        static::assertEquals('Runtime field, cannot be used as part of the criteria.', $properties['runtimeField']['description']);
    }

    public function testExtensionConversion(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(SimpleExtendedDefinition::class),
            '/simple-extended',
            false
        );
        $properties = json_decode($schema['SimpleExtended']->toJson(), true, \JSON_THROW_ON_ERROR, \JSON_THROW_ON_ERROR)['properties'];

        static::assertArrayHasKey('extensions', $properties);
        static::assertArrayHasKey('properties', $properties['extensions']);
        static::assertArrayHasKey('extendedJsonField', $properties['extensions']['properties']);
    }
}

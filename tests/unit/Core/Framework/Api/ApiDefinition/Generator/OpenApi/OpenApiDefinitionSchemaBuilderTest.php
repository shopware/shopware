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
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\_fixtures\TechnicalOnlyDefinition;
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
                TechnicalOnlyDefinition::class,
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
        $properties = json_decode($schema['Simple']->toJson(), true, flags: \JSON_THROW_ON_ERROR)['properties'];
        static::assertArrayHasKey('id', $properties);
        static::assertArrayHasKey('type', $properties['id']);
        static::assertSame('string', $properties['id']['type']);
        static::assertArrayHasKey('pattern', $properties['id']);
        static::assertSame('^[0-9a-f]{32}$', $properties['id']['pattern']);
        static::assertArrayHasKey('stringField', $properties);
        static::assertArrayHasKey('type', $properties['stringField']);
        static::assertSame('string', $properties['stringField']['type']);
        static::assertArrayHasKey('intField', $properties);
        static::assertArrayHasKey('type', $properties['intField']);
        static::assertSame('integer', $properties['intField']['type']);
        static::assertArrayHasKey('format', $properties['intField']);
        static::assertSame('int64', $properties['intField']['format']);
        static::assertArrayHasKey('floatField', $properties);
        static::assertArrayHasKey('type', $properties['floatField']);
        static::assertSame('number', $properties['floatField']['type']);
        static::assertArrayHasKey('format', $properties['floatField']);
        static::assertSame('float', $properties['floatField']['format']);
        static::assertArrayHasKey('boolField', $properties);
        static::assertArrayHasKey('type', $properties['boolField']);
        static::assertSame('boolean', $properties['boolField']['type']);
        static::assertArrayHasKey('childCount', $properties);
        static::assertArrayHasKey('type', $properties['childCount']);
        static::assertSame('integer', $properties['childCount']['type']);
        static::assertArrayHasKey('format', $properties['childCount']);
        static::assertSame('int64', $properties['childCount']['format']);
    }

    public function testFlagConversion(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(SimpleDefinition::class),
            '/simple',
            false
        );
        $properties = json_decode($schema['Simple']->toJson(), true, flags: \JSON_THROW_ON_ERROR)['properties'];

        static::assertArrayHasKey('requiredField', $properties);
        static::assertArrayHasKey('readOnlyField', $properties);
        static::assertArrayHasKey('readOnly', $properties['readOnlyField']);
        static::assertTrue($properties['readOnlyField']['readOnly']);
        static::assertArrayHasKey('runtimeField', $properties);
        static::assertSame('Runtime field, cannot be used as part of the criteria.', $properties['runtimeField']['description']);
    }

    public function testExtensionConversion(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(SimpleExtendedDefinition::class),
            '/simple-extended',
            false
        );
        $properties = json_decode($schema['SimpleExtended']->toJson(), true, flags: \JSON_THROW_ON_ERROR)['properties'];

        static::assertArrayHasKey('createdAt', $properties);
        static::assertArrayHasKey('extensions', $properties);
        static::assertArrayHasKey('properties', $properties['extensions']);
        static::assertArrayHasKey('extendedJsonField', $properties['extensions']['properties']);
        static::assertArrayHasKey('data', $properties['extensions']['properties']['simpleIdField']['properties']);
    }

    public function testRequestExtensionConversionUsesDirectAssociationSchema(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(SimpleExtendedDefinition::class),
            '/simple-extended',
            false,
            false,
            'jsonapi',
            true
        );
        $createSchema = json_decode($schema['SimpleExtendedCreate']->toJson(), true, flags: \JSON_THROW_ON_ERROR);
        $updateSchema = json_decode($schema['SimpleExtendedUpdate']->toJson(), true, flags: \JSON_THROW_ON_ERROR);
        $createProperties = $createSchema['properties'];
        $updateProperties = $updateSchema['properties'];

        static::assertSame($createProperties, $updateProperties);
        static::assertSame(['requiredJsonField'], $createSchema['required']);
        static::assertArrayNotHasKey('required', $updateSchema);
        static::assertArrayNotHasKey('id', $createProperties);
        static::assertArrayNotHasKey('createdAt', $createProperties);
        static::assertArrayHasKey('requiredJsonField', $createProperties);
        static::assertArrayHasKey('extensions', $createProperties);
        static::assertArrayHasKey('properties', $createProperties['extensions']);
        static::assertArrayHasKey('extendedJsonField', $createProperties['extensions']['properties']);
        static::assertSame(
            '#/components/schemas/Simple',
            $createProperties['extensions']['properties']['simpleIdField']['$ref']
        );
        static::assertArrayNotHasKey('data', $createProperties['extensions']['properties']['simpleIdField']);
    }

    public function testRequestSchemaWithOnlyTechnicalFieldsHasNoPropertiesArray(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(TechnicalOnlyDefinition::class),
            '/technical-only',
            false,
            false,
            'jsonapi',
            true
        );

        $createSchema = json_decode($schema['TechnicalOnlyCreate']->toJson(), true, flags: \JSON_THROW_ON_ERROR);
        $updateSchema = json_decode($schema['TechnicalOnlyUpdate']->toJson(), true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame('object', $createSchema['type']);
        static::assertSame('object', $updateSchema['type']);
        static::assertArrayNotHasKey('properties', $createSchema);
        static::assertArrayNotHasKey('properties', $updateSchema);
        static::assertArrayNotHasKey('required', $createSchema);
        static::assertArrayNotHasKey('required', $updateSchema);
    }

    public function testJsonApiExtensionConversionKeepsAssociationLinkage(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(SimpleExtendedDefinition::class),
            '/simple-extended',
            false
        );
        $properties = json_decode($schema['SimpleExtendedJsonApi']->toJson(), true, flags: \JSON_THROW_ON_ERROR)['allOf'][1]['properties'];

        static::assertArrayHasKey('extensions', $properties);
        static::assertArrayHasKey('properties', $properties['extensions']);
        static::assertArrayHasKey('simpleIdField', $properties['extensions']['properties']);
        static::assertArrayHasKey('data', $properties['extensions']['properties']['simpleIdField']['properties']);
    }

    public function testAssociationDescriptions(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(ComplexDefinition::class),
            '/complex',
            false
        );

        $properties = json_decode($schema['Complex']->toJson(), true, flags: \JSON_THROW_ON_ERROR)['properties'];

        // Test ManyToOne association description
        static::assertArrayHasKey('simpleTo', $properties);
        static::assertArrayHasKey('description', $properties['simpleTo']);
        static::assertSame('A reference to a simple entity', $properties['simpleTo']['description']);

        // Test OneToMany association description
        static::assertArrayHasKey('simpleManys', $properties);
        static::assertArrayHasKey('description', $properties['simpleManys']);
        static::assertSame('Multiple simple entities', $properties['simpleManys']['description']);

        // Test with empty description
        static::assertArrayHasKey('simpleToWithEmptyDescription', $properties);
        static::assertArrayNotHasKey('description', $properties['simpleToWithEmptyDescription']);
    }
}

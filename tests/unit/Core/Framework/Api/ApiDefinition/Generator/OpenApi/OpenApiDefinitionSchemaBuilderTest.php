<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SimpleDefinition;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\_fixtures\ComplexDefinition;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\_fixtures\ImmutableFieldsDefinition;
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
                ImmutableFieldsDefinition::class,
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

        // Test modifiable fields in Update schema
        $updateProperties = $this->extractPropertiesFromSchema($schema['SimpleUpdate']);
        static::assertArrayHasKey('stringField', $updateProperties);
        static::assertArrayHasKey('type', $updateProperties['stringField']);
        static::assertSame('string', $updateProperties['stringField']['type']);
        static::assertArrayHasKey('intField', $updateProperties);
        static::assertArrayHasKey('type', $updateProperties['intField']);
        static::assertSame('integer', $updateProperties['intField']['type']);
        static::assertArrayHasKey('format', $updateProperties['intField']);
        static::assertSame('int64', $updateProperties['intField']['format']);
        static::assertArrayHasKey('floatField', $updateProperties);
        static::assertArrayHasKey('type', $updateProperties['floatField']);
        static::assertSame('number', $updateProperties['floatField']['type']);
        static::assertArrayHasKey('format', $updateProperties['floatField']);
        static::assertSame('float', $updateProperties['floatField']['format']);
        static::assertArrayHasKey('boolField', $updateProperties);
        static::assertArrayHasKey('type', $updateProperties['boolField']);
        static::assertSame('boolean', $updateProperties['boolField']['type']);

        // Test read-only fields in Read schema's inline properties
        $readProperties = $this->extractPropertiesFromSchema($schema['Simple']);
        static::assertArrayHasKey('id', $readProperties);
        static::assertArrayHasKey('type', $readProperties['id']);
        static::assertSame('string', $readProperties['id']['type']);
        static::assertArrayHasKey('pattern', $readProperties['id']);
        static::assertSame('^[0-9a-f]{32}$', $readProperties['id']['pattern']);

        // childCount has WriteProtected flag, so it's in Read schema (read-only)
        static::assertArrayHasKey('childCount', $readProperties);
        static::assertArrayHasKey('type', $readProperties['childCount']);
        static::assertSame('integer', $readProperties['childCount']['type']);
        static::assertArrayHasKey('format', $readProperties['childCount']);
        static::assertSame('int64', $readProperties['childCount']['format']);
    }

    public function testFlagConversion(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(SimpleDefinition::class),
            '/simple',
            false
        );

        // Required field should be in Update schema (modifiable)
        $updateProperties = $this->extractPropertiesFromSchema($schema['SimpleUpdate']);
        static::assertArrayHasKey('requiredField', $updateProperties);

        // Read-only fields should be in Read schema's inline properties
        $readProperties = $this->extractPropertiesFromSchema($schema['Simple']);
        static::assertArrayHasKey('readOnlyField', $readProperties);
        static::assertArrayHasKey('readOnly', $readProperties['readOnlyField']);
        static::assertTrue($readProperties['readOnlyField']['readOnly']);
        static::assertArrayHasKey('runtimeField', $readProperties);
        static::assertSame('Runtime field, cannot be used as part of the criteria.', $readProperties['runtimeField']['description']);
    }

    public function testExtensionConversion(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(SimpleExtendedDefinition::class),
            '/simple-extended',
            false
        );

        // Extensions are added to Update schema (modifiable)
        $updateProperties = $this->extractPropertiesFromSchema($schema['SimpleExtendedUpdate']);

        static::assertArrayHasKey('extensions', $updateProperties);
        static::assertArrayHasKey('properties', $updateProperties['extensions']);
        static::assertArrayHasKey('extendedJsonField', $updateProperties['extensions']['properties']);
    }

    public function testAssociationDescriptions(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(ComplexDefinition::class),
            '/complex',
            false
        );

        // Associations are included in Read schema's inline properties (allOf[0])
        $properties = $this->extractPropertiesFromSchema($schema['Complex']);

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

    public function testGeneratesUpdateSchemaForAllEntities(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(SimpleDefinition::class),
            '/simple',
            false
        );

        static::assertArrayHasKey('Simple', $schema);
        static::assertArrayHasKey('SimpleUpdate', $schema);
    }

    public function testGeneratesCreateSchemaForAllEntities(): void
    {
        // SimpleDefinition has no immutable fields - should STILL have Create schema
        $simpleSchema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(SimpleDefinition::class),
            '/simple',
            false
        );

        static::assertArrayHasKey('SimpleUpdate', $simpleSchema);
        static::assertArrayHasKey('SimpleCreate', $simpleSchema);

        // ImmutableFieldsDefinition has immutable fields - SHOULD have Create schema
        $immutableSchema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(ImmutableFieldsDefinition::class),
            '/immutable-test',
            false
        );

        static::assertArrayHasKey('ImmutableTestUpdate', $immutableSchema);
        static::assertArrayHasKey('ImmutableTestCreate', $immutableSchema);
    }

    public function testUpdateSchemaContainsOnlyModifiableFields(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(ImmutableFieldsDefinition::class),
            '/immutable-test',
            false
        );

        $updateSchema = json_decode($schema['ImmutableTestUpdate']->toJson(), true, 512, \JSON_THROW_ON_ERROR);

        // Update schema should contain modifiable fields
        static::assertArrayHasKey('properties', $updateSchema);
        $properties = $updateSchema['properties'];

        static::assertArrayHasKey('description', $properties);
        static::assertArrayHasKey('label', $properties);

        // Update schema should NOT contain immutable fields
        static::assertArrayNotHasKey('name', $properties);
        static::assertArrayNotHasKey('type', $properties);

        // Update schema should NOT contain read-only technical fields
        static::assertArrayNotHasKey('id', $properties);
        static::assertArrayNotHasKey('createdAt', $properties);
        static::assertArrayNotHasKey('updatedAt', $properties);
    }

    public function testCreateSchemaExtendsUpdateWithImmutableFields(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(ImmutableFieldsDefinition::class),
            '/immutable-test',
            false
        );

        $createSchema = json_decode($schema['ImmutableTestCreate']->toJson(), true, 512, \JSON_THROW_ON_ERROR);

        // Create schema should use allOf composition
        static::assertArrayHasKey('allOf', $createSchema);
        static::assertCount(2, $createSchema['allOf']);

        // First allOf should reference Update schema
        static::assertArrayHasKey('$ref', $createSchema['allOf'][0]);
        static::assertSame('#/components/schemas/ImmutableTestUpdate', $createSchema['allOf'][0]['$ref']);

        // Second allOf should contain immutable fields
        static::assertArrayHasKey('properties', $createSchema['allOf'][1]);
        $immutableProperties = $createSchema['allOf'][1]['properties'];

        static::assertArrayHasKey('name', $immutableProperties);
        static::assertArrayHasKey('type', $immutableProperties);

        // Immutable fields should have description indicating immutability
        static::assertStringContainsString('Immutable', $immutableProperties['name']['description'] ?? '');
    }

    public function testReadSchemaUsesAllOfComposition(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(ImmutableFieldsDefinition::class),
            '/immutable-test',
            false
        );

        $readSchema = json_decode($schema['ImmutableTest']->toJson(), true, 512, \JSON_THROW_ON_ERROR);

        // Read schema should use allOf composition
        static::assertArrayHasKey('allOf', $readSchema);
        static::assertCount(2, $readSchema['allOf']);

        // First allOf should contain read-only properties (id, createdAt, updatedAt)
        static::assertArrayHasKey('properties', $readSchema['allOf'][0]);
        $readOnlyProperties = $readSchema['allOf'][0]['properties'];

        static::assertArrayHasKey('id', $readOnlyProperties);
        static::assertTrue($readOnlyProperties['id']['readOnly'] ?? false);

        // Second allOf should reference Create schema (since entity has immutable fields)
        static::assertArrayHasKey('$ref', $readSchema['allOf'][1]);
        static::assertSame('#/components/schemas/ImmutableTestCreate', $readSchema['allOf'][1]['$ref']);
    }

    public function testReadSchemaReferencesCreateWhenNoImmutableFields(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(SimpleDefinition::class),
            '/simple',
            false
        );

        $readSchema = json_decode($schema['Simple']->toJson(), true, 512, \JSON_THROW_ON_ERROR);

        // Read schema should use allOf composition
        static::assertArrayHasKey('allOf', $readSchema);

        // Should reference Create schema (Create schema always exists)
        $hasCreateRef = false;
        foreach ($readSchema['allOf'] as $item) {
            if (isset($item['$ref']) && $item['$ref'] === '#/components/schemas/SimpleCreate') {
                $hasCreateRef = true;

                break;
            }
        }
        static::assertTrue($hasCreateRef, 'Read schema should reference Create schema');
    }

    public function testImmutableFieldRequiredInCreateSchema(): void
    {
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(ImmutableFieldsDefinition::class),
            '/immutable-test',
            false
        );

        $createSchema = json_decode($schema['ImmutableTestCreate']->toJson(), true, 512, \JSON_THROW_ON_ERROR);

        // Required fields are at the Create schema root level (valid OpenAPI pattern)
        static::assertArrayHasKey('required', $createSchema);
        static::assertContains('name', $createSchema['required']);

        // 'type' is immutable but not required, should not be in required array
        static::assertNotContains('type', $createSchema['required']);

        // 'label' is required and modifiable, should also be in required array
        static::assertContains('label', $createSchema['required']);
    }

    public function testStoreApiDoesNotGenerateUpdateCreateSchemas(): void
    {
        // When forSalesChannel is true (Store API), only flat Read schema should be generated
        $schema = $this->schemaBuilder->getSchemaByDefinition(
            $this->definitionRegistry->get(SimpleDefinition::class),
            '/simple',
            true // forSalesChannel = true (Store API)
        );

        // Store API should have the Read schema
        static::assertArrayHasKey('Simple', $schema);
        static::assertArrayHasKey('SimpleJsonApi', $schema);

        // Store API should NOT have Update/Create schemas
        static::assertArrayNotHasKey('SimpleUpdate', $schema);
        static::assertArrayNotHasKey('SimpleCreate', $schema);

        // The Read schema should be flat (no allOf composition)
        $readSchema = json_decode($schema['Simple']->toJson(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('properties', $readSchema);
        static::assertArrayNotHasKey('allOf', $readSchema);
    }

    /**
     * Helper method to extract all properties from a schema that uses allOf composition.
     * Merges properties from all allOf items that have direct properties.
     *
     * @return array<string, mixed>
     */
    private function extractPropertiesFromSchema(\OpenApi\Annotations\Schema $schema): array
    {
        $schemaData = json_decode($schema->toJson(), true, 512, \JSON_THROW_ON_ERROR);

        // If schema has direct properties, return them
        if (isset($schemaData['properties'])) {
            return $schemaData['properties'];
        }

        // If schema uses allOf, merge properties from all items
        if (isset($schemaData['allOf'])) {
            $properties = [];
            foreach ($schemaData['allOf'] as $item) {
                if (isset($item['properties'])) {
                    $properties = array_merge($properties, $item['properties']);
                }
            }

            return $properties;
        }

        return [];
    }
}

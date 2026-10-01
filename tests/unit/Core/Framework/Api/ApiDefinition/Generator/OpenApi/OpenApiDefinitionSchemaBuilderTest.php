<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\DefinitionWithJsonOverride;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\PluginExtensionForJsonOverride;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SimpleDefinition;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\_fixtures\ComplexDefinition;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\_fixtures\DefinitionWithHiddenRequiredTranslation;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\_fixtures\DefinitionWithHiddenRequiredTranslationTranslation;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\_fixtures\SimpleExtendedDefinition;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
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
                DefinitionWithJsonOverride::class,
                DefinitionWithHiddenRequiredTranslation::class,
                DefinitionWithHiddenRequiredTranslationTranslation::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testEntityNameConversion(): void
    {
        $schema = $this->schemaBuilder->createSchemas(
            $this->definitionRegistry->get(SimpleDefinition::class),
            '/simple',
            false
        );
        static::assertArrayHasKey('Simple', $schema);
        static::assertArrayHasKey('SimpleJsonApi', $schema);
    }

    public function testAssociationSchemas(): void
    {
        $schema = $this->schemaBuilder->createSchemas(
            $this->definitionRegistry->get(ComplexDefinition::class),
            '/complex',
            false
        );
        static::assertArrayHasKey('Complex', $schema);
        static::assertArrayHasKey('ComplexJsonApi', $schema);
    }

    public function testRequiredAssociationIsOnlyRequiredInFlatSchema(): void
    {
        $schema = $this->schemaBuilder->createSchemas(
            $this->definitionRegistry->get(ComplexDefinition::class),
            '/complex',
            false
        );
        $flatSchema = $schema['Complex'];
        $jsonApiSchema = $schema['ComplexJsonApi'];

        static::assertContains('idField', $flatSchema['required']);
        static::assertContains('simpleManys', $flatSchema['required']);
        static::assertContains('idField', $jsonApiSchema['allOf'][1]['required']);
        static::assertNotContains('simpleManys', $jsonApiSchema['allOf'][1]['required']);
    }

    public function testRequiredFieldsAreLimitedToGeneratedProperties(): void
    {
        $schema = $this->schemaBuilder->createSchemas(
            $this->definitionRegistry->get(DefinitionWithHiddenRequiredTranslation::class),
            '/definition-with-hidden-required-translation',
            true
        );

        $flatSchema = $schema['DefinitionWithHiddenRequiredTranslation'];
        $jsonApiSchema = $schema['DefinitionWithHiddenRequiredTranslationJsonApi'];

        static::assertSame(['id', 'visible'], $flatSchema['required']);
        static::assertSame(['id', 'visible'], $jsonApiSchema['allOf'][1]['required']);
        static::assertArrayNotHasKey('hiddenTranslated', $flatSchema['properties']);
        static::assertArrayNotHasKey('hiddenTranslated', $jsonApiSchema['allOf'][1]['properties']);
    }

    public function testTypeConversion(): void
    {
        $schema = $this->schemaBuilder->createSchemas(
            $this->definitionRegistry->get(SimpleDefinition::class),
            '/simple',
            false
        );
        $properties = $schema['Simple']['properties'];
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
        $schema = $this->schemaBuilder->createSchemas(
            $this->definitionRegistry->get(SimpleDefinition::class),
            '/simple',
            false
        );
        $properties = $schema['Simple']['properties'];

        static::assertArrayHasKey('requiredField', $properties);
        static::assertArrayHasKey('readOnlyField', $properties);
        static::assertArrayHasKey('readOnly', $properties['readOnlyField']);
        static::assertTrue($properties['readOnlyField']['readOnly']);
        static::assertArrayHasKey('runtimeField', $properties);
        static::assertSame('Runtime field, cannot be used as part of the criteria.', $properties['runtimeField']['description']);
    }

    public function testExtensionConversion(): void
    {
        $schema = $this->schemaBuilder->createSchemas(
            $this->definitionRegistry->get(SimpleExtendedDefinition::class),
            '/simple-extended',
            false
        );
        $properties = $schema['SimpleExtended']['properties'];

        static::assertArrayHasKey('extensions', $properties);
        static::assertArrayHasKey('properties', $properties['extensions']);
        static::assertArrayHasKey('extendedJsonField', $properties['extensions']['properties']);
    }

    public function testExtensionSchemaDoesNotGenerateBaseDefinitionFields(): void
    {
        $definition = $this->definitionRegistry->get(DefinitionWithJsonOverride::class);
        $extension = new PluginExtensionForJsonOverride();
        $definition->addExtension($extension);

        try {
            $fullSchema = $this->schemaBuilder->createSchemas($definition, '/json-override-entity', true);
            $schema = $this->schemaBuilder->createExtensionSchemas($definition, '/json-override-entity', true);
            $fullProperties = $fullSchema['JsonOverrideEntity']['properties'];
            $properties = $schema['JsonOverrideEntity']['properties'];

            static::assertSame(['extensions'], array_keys($properties));
            static::assertSame($fullProperties['extensions'], $properties['extensions']);
            static::assertSame('object', $properties['extensions']['type']);
            static::assertSame('object', $properties['extensions']['properties']['pluginEntities']['type']);
            static::assertSame('string', $properties['extensions']['properties']['pluginLabel']['type']);
            static::assertSame('boolean', $properties['extensions']['properties']['pluginActive']['type']);
        } finally {
            $definition->removeExtension($extension);
        }
    }

    public function testAssociationDescriptions(): void
    {
        $schema = $this->schemaBuilder->createSchemas(
            $this->definitionRegistry->get(ComplexDefinition::class),
            '/complex',
            false
        );

        $properties = $schema['Complex']['properties'];

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

    public function testJsonTypeOmitsTheJsonApiSchema(): void
    {
        $schema = $this->schemaBuilder->createSchemas(
            $this->definitionRegistry->get(SimpleDefinition::class),
            '/simple',
            false,
            apiType: DefinitionService::TYPE_JSON
        );

        static::assertSame(['Simple'], array_keys($schema));
        static::assertSame(['description', 'required', 'properties', 'type'], array_keys($schema['Simple']));
    }

    public function testRelationshipsAreReferencedInTheFlatSchemaAndLinkedInTheJsonApiSchema(): void
    {
        $schema = $this->schemaBuilder->createSchemas(
            $this->definitionRegistry->get(ComplexDefinition::class),
            '/complex',
            false
        );

        static::assertSame(
            ['$ref' => '#/components/schemas/Simple', 'description' => 'A reference to a simple entity'],
            $schema['Complex']['properties']['simpleTo']
        );
        static::assertSame(
            ['description' => 'Multiple simple entities', 'type' => 'array', 'items' => ['$ref' => '#/components/schemas/Simple']],
            $schema['Complex']['properties']['simpleManys']
        );

        $resource = $schema['ComplexJsonApi']['allOf'][1];
        static::assertSame(['$ref' => '#/components/schemas/resource'], $schema['ComplexJsonApi']['allOf'][0]);
        static::assertArrayNotHasKey('simpleTo', $resource['properties']);
        static::assertSame('object', $resource['properties']['relationships']['properties']['simpleTo']['type']);
        static::assertSame('simple', $resource['properties']['relationships']['properties']['simpleTo']['properties']['data']['properties']['type']['example']);
        static::assertSame('array', $resource['properties']['relationships']['properties']['simpleManys']['properties']['data']['type']);
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with OpenApiDefinitionSchemaBuilder::getSchemaByDefinition()
     */
    #[DataProvider('definitionProvider')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetSchemaByDefinitionSerializesToTheCreatedSchemas(string $definitionClass, string $path): void
    {
        $definition = $this->definitionRegistry->get($definitionClass);

        $annotations = $this->schemaBuilder->getSchemaByDefinition($definition, $path, false);
        $schemas = $this->schemaBuilder->createSchemas($definition, $path, false);

        static::assertSame(array_keys($schemas), array_keys($annotations));
        foreach ($schemas as $schemaName => $schema) {
            static::assertInstanceOf(Schema::class, $annotations[$schemaName]);
            static::assertSame($schemaName, $annotations[$schemaName]->schema);
            static::assertSame(['schema' => $schemaName] + $schema, json_decode($annotations[$schemaName]->toJson(), true, flags: \JSON_THROW_ON_ERROR));
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with OpenApiDefinitionSchemaBuilder::getExtensionSchemaByDefinition()
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetExtensionSchemaByDefinitionSerializesToTheCreatedSchemas(): void
    {
        $definition = $this->definitionRegistry->get(DefinitionWithJsonOverride::class);
        $extension = new PluginExtensionForJsonOverride();
        $definition->addExtension($extension);

        try {
            $annotations = $this->schemaBuilder->getExtensionSchemaByDefinition($definition, '/json-override-entity', true);
            $schemas = $this->schemaBuilder->createExtensionSchemas($definition, '/json-override-entity', true);

            static::assertSame(['JsonOverrideEntity'], array_keys($annotations));
            static::assertInstanceOf(Schema::class, $annotations['JsonOverrideEntity']);
            static::assertSame(
                ['schema' => 'JsonOverrideEntity'] + $schemas['JsonOverrideEntity'],
                json_decode($annotations['JsonOverrideEntity']->toJson(), true, flags: \JSON_THROW_ON_ERROR)
            );
        } finally {
            $definition->removeExtension($extension);
        }
    }

    public static function definitionProvider(): \Generator
    {
        yield 'simple definition' => [SimpleDefinition::class, '/simple'];
        yield 'definition with associations' => [ComplexDefinition::class, '/complex'];
        yield 'definition with extensions' => [SimpleExtendedDefinition::class, '/simple-extended'];
    }
}

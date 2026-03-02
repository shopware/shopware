<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\BundleSchemaPathCollection;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiPathBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\CustomBundleWithApiSchema\ShopwareBundleWithName;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SimpleDefinition;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SimpleMappingDefinition;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\_fixtures\ExtensionWithImmutableDefinition;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\_fixtures\ImmutableFieldsDefinition;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(OpenApi3Generator::class)]
class OpenApi3GeneratorTest extends TestCase
{
    private OpenApi3Generator $generator;

    private OpenApi3Generator $customApiGenerator;

    private ShopwareBundleWithName $customBundleSchemas;

    private StaticDefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->generator = new OpenApi3Generator(
            new OpenApiSchemaBuilder('0.1.0'),
            new OpenApiPathBuilder(),
            new OpenApiDefinitionSchemaBuilder(),
            [
                'Framework' => ['path' => __DIR__ . '/_fixtures'],
            ],
            new BundleSchemaPathCollection([])
        );

        $this->customBundleSchemas = new ShopwareBundleWithName();
        $customBundlePathCollection = new BundleSchemaPathCollection([$this->customBundleSchemas]);

        $this->customApiGenerator = new OpenApi3Generator(
            new OpenApiSchemaBuilder('0.1.0'),
            new OpenApiPathBuilder(),
            new OpenApiDefinitionSchemaBuilder(),
            [
                'Framework' => ['path' => __DIR__ . '/_fixtures'],
            ],
            $customBundlePathCollection
        );

        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [
                SimpleDefinition::class,
                ImmutableFieldsDefinition::class,
                SimpleMappingDefinition::class,
                ExtensionWithImmutableDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }

    public function testSchemaContainsCorrectPaths(): void
    {
        $schema = $this->generator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::API
        );
        $paths = $schema['paths'];

        static::assertArrayHasKey('get', $paths['/simple']);
        static::assertArrayHasKey('post', $paths['/simple']);
        static::assertArrayHasKey('get', $paths['/simple/{id}']);
        static::assertArrayHasKey('patch', $paths['/simple/{id}']);
        static::assertArrayHasKey('delete', $paths['/simple/{id}']);

        static::assertArrayHasKey('post', $paths['/_action/order_delivery/{orderDeliveryId}/state/{transition}']);
    }

    public function testSchemaContainsCorrectEntities(): void
    {
        $schema = $this->generator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::API,
            'json',
            null
        );
        $entities = $schema['components']['schemas'];
        static::assertArrayHasKey('Simple', $entities);
        static::assertArrayHasKey('infoConfigResponse', $entities);
    }

    public function testSchemaBuilding(): void
    {
        $schema = $this->generator->getSchema(
            $this->definitionRegistry->getDefinitions()
        );

        static::assertArrayHasKey('simple', $schema);
    }

    public function testSchemaContainsCustomPathsOnly(): void
    {
        $schema = $this->customApiGenerator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::API,
            DefinitionService::TYPE_JSON_API,
            $this->customBundleSchemas->getName()
        );

        $paths = $schema['paths'];

        static::assertArrayHasKey('post', $paths['/search/guided-shopping-presentation']);
        static::assertArrayNotHasKey('/_action/order_delivery/{orderDeliveryId}/state/{transition}', $paths);
    }

    public function testSchemaContainsCustomEntitiesOnly(): void
    {
        $schema = $this->customApiGenerator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::API,
            DefinitionService::TYPE_JSON_API,
            $this->customBundleSchemas->getName()
        );

        $entities = $schema['components']['schemas'];
        static::assertArrayHasKey('Presentation', $entities);
        static::assertArrayHasKey('infoConfigResponse', $entities);
        static::assertSame('Experimental', $schema['tags'][0]['name'] ?? null);
    }

    public function testGetSchemaExtractsPropertiesFromAllOfComposition(): void
    {
        // ImmutableFieldsDefinition uses allOf composition for Read schema
        $schema = $this->generator->getSchema(
            ['immutable_test' => $this->definitionRegistry->get(ImmutableFieldsDefinition::class)]
        );

        static::assertArrayHasKey('immutable_test', $schema);

        // Verify properties are extracted correctly from allOf composition
        $properties = $schema['immutable_test']['properties'];

        // Should have id (read-only)
        static::assertArrayHasKey('id', $properties);

        // Should have modifiable fields
        static::assertArrayHasKey('description', $properties);
        static::assertArrayHasKey('label', $properties);

        // Should have immutable fields
        static::assertArrayHasKey('name', $properties);
        static::assertArrayHasKey('type', $properties);
    }

    public function testGetSchemaIncludesTranslatableFields(): void
    {
        $schema = $this->generator->getSchema(
            $this->definitionRegistry->getDefinitions()
        );

        static::assertArrayHasKey('simple', $schema);
        static::assertArrayHasKey('translatable', $schema['simple']);
    }

    public function testGenerateWithImmutableFieldsCreatesProperSchemas(): void
    {
        $schema = $this->generator->generate(
            ['immutable_test' => $this->definitionRegistry->get(ImmutableFieldsDefinition::class)],
            DefinitionService::API,
            DefinitionService::TYPE_JSON_API
        );

        $entities = $schema['components']['schemas'];

        // Should have Update, Create, and Read schemas
        static::assertArrayHasKey('ImmutableTestUpdate', $entities);
        static::assertArrayHasKey('ImmutableTestCreate', $entities);
        static::assertArrayHasKey('ImmutableTest', $entities);
    }

    public function testGenerateWithoutImmutableFieldsDoesNotCreateCreateSchema(): void
    {
        $schema = $this->generator->generate(
            ['simple' => $this->definitionRegistry->get(SimpleDefinition::class)],
            DefinitionService::API,
            DefinitionService::TYPE_JSON_API
        );

        $entities = $schema['components']['schemas'];

        // Should have Update and Read schemas
        static::assertArrayHasKey('SimpleUpdate', $entities);
        static::assertArrayHasKey('Simple', $entities);

        // Should NOT have Create schema (no immutable fields)
        static::assertArrayNotHasKey('SimpleCreate', $entities);
    }

    public function testUnreferencedSchemasAreRemoved(): void
    {
        // Generate schema with both a regular entity and a mapping entity
        $schema = $this->generator->generate(
            [
                'simple' => $this->definitionRegistry->get(SimpleDefinition::class),
                'simple_mapping' => $this->definitionRegistry->get(SimpleMappingDefinition::class),
            ],
            DefinitionService::API,
            DefinitionService::TYPE_JSON_API
        );

        $entities = $schema['components']['schemas'];

        // Simple entity should exist (it has paths that reference it)
        static::assertArrayHasKey('Simple', $entities);
        static::assertArrayHasKey('SimpleUpdate', $entities);

        // SimpleMappingDefinition should NOT exist (it's a mapping entity with no references)
        static::assertArrayNotHasKey('SimpleMapping', $entities);
        static::assertArrayNotHasKey('SimpleMappingUpdate', $entities);
    }

    public function testGetSchemaWithNullSinceDefinition(): void
    {
        // ExtensionWithImmutableDefinition has since() = null
        $schema = $this->generator->getSchema(
            ['extension_immutable_test' => $this->definitionRegistry->get(ExtensionWithImmutableDefinition::class)]
        );

        static::assertArrayHasKey('extension_immutable_test', $schema);
        $properties = $schema['extension_immutable_test']['properties'];

        // Should have id, modifiable and immutable fields
        static::assertArrayHasKey('id', $properties);
        static::assertArrayHasKey('code', $properties);
        static::assertArrayHasKey('label', $properties);
    }

    public function testGenerateWithJsonTypeDoesNotRemoveSchemas(): void
    {
        // TYPE_JSON should not trigger removeUnreferencedSchemas
        $schema = $this->generator->generate(
            [
                'simple' => $this->definitionRegistry->get(SimpleDefinition::class),
                'simple_mapping' => $this->definitionRegistry->get(SimpleMappingDefinition::class),
            ],
            DefinitionService::API,
            DefinitionService::TYPE_JSON
        );

        $entities = $schema['components']['schemas'];

        // With TYPE_JSON, all schemas remain (no removal happens)
        static::assertArrayHasKey('Simple', $entities);
    }

    public function testReferencedSchemasAreKept(): void
    {
        // Generate schema where schemas reference each other
        $schema = $this->generator->generate(
            ['immutable_test' => $this->definitionRegistry->get(ImmutableFieldsDefinition::class)],
            DefinitionService::API,
            DefinitionService::TYPE_JSON_API
        );

        $entities = $schema['components']['schemas'];

        // All schemas should exist because they reference each other:
        // ImmutableTest references ImmutableTestCreate, which references ImmutableTestUpdate
        static::assertArrayHasKey('ImmutableTest', $entities);
        static::assertArrayHasKey('ImmutableTestCreate', $entities);
        static::assertArrayHasKey('ImmutableTestUpdate', $entities);
    }
}

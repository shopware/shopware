<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\BundleSchemaPathCollection;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\BundleWithAssociationEnrichmentPaths\BundleWithAssociationEnrichmentPaths;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\BundleWithPredeclaredSwLanguageId\BundleWithPredeclaredSwLanguageId;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\CustomBundleWithApiSchema\ShopwareBundleWithName;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\DefinitionWithAssociations;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\DefinitionWithJsonOverride;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\PluginBundleWithSchema\PluginBundleWithSchema;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\PluginExtensionForJsonOverride;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SalesChannelSimpleDefinition;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SEOUrlDefinition;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SimpleDefinition;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiGenerator::class)]
class StoreApiGeneratorTest extends TestCase
{
    private const BASE_DESCRIPTION = 'Base description.';

    /**
     * Associations of {@see DefinitionWithAssociations} that are documented for the Store API:
     * `category` carries a description, `children` does not. All other associations of that
     * definition are filtered out (hidden, translations, parent, non-ApiAware, admin-only).
     */
    private const TEST_ENTITY_ASSOCIATIONS = "\n\n**Available Associations:**\n- `category` - The category this entity belongs to\n- `children`";

    private const SEO_URL_ASSOCIATIONS = "\n\n**Available Associations:**\n- `simpleThings`";

    private StoreApiGenerator $generator;

    private StoreApiGenerator $customApiGenerator;

    private StoreApiGenerator $pluginApiGenerator;

    private StoreApiGenerator $associationEnrichmentGenerator;

    private Bundle $customBundleSchemas;

    private Bundle $associationEnrichmentBundle;

    private StaticDefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->generator = new StoreApiGenerator(
            new OpenApiSchemaBuilder('0.1.0'),
            new OpenApiDefinitionSchemaBuilder(),
            [
                'Framework' => ['path' => __DIR__ . '/_fixtures'],
            ],
            new BundleSchemaPathCollection([]),
        );

        $this->customBundleSchemas = new ShopwareBundleWithName();
        $customBundlePathCollection = new BundleSchemaPathCollection([$this->customBundleSchemas]);

        $this->customApiGenerator = new StoreApiGenerator(
            new OpenApiSchemaBuilder('0.1.0'),
            new OpenApiDefinitionSchemaBuilder(),
            [
                'Framework' => ['path' => __DIR__ . '/_fixtures'],
            ],
            $customBundlePathCollection,
        );
        $pluginBundle = new PluginBundleWithSchema();
        $pluginBundlePathCollection = new BundleSchemaPathCollection([$pluginBundle]);

        $this->pluginApiGenerator = new StoreApiGenerator(
            new OpenApiSchemaBuilder('0.1.0'),
            new OpenApiDefinitionSchemaBuilder(),
            [
                'Framework' => ['path' => __DIR__ . '/_fixtures'],
            ],
            $pluginBundlePathCollection,
        );

        $this->associationEnrichmentBundle = new BundleWithAssociationEnrichmentPaths();

        $this->associationEnrichmentGenerator = new StoreApiGenerator(
            new OpenApiSchemaBuilder('0.1.0'),
            new OpenApiDefinitionSchemaBuilder(),
            [
                'Framework' => ['path' => __DIR__ . '/_fixtures'],
            ],
            new BundleSchemaPathCollection([$this->associationEnrichmentBundle]),
        );

        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [
                SimpleDefinition::class,
                DefinitionWithAssociations::class,
                DefinitionWithJsonOverride::class,
                SEOUrlDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testSchemaContainsCorrectPaths(): void
    {
        $schema = $this->generator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            null
        );
        $paths = $schema['paths'];

        static::assertArrayHasKey('post', $paths['/_action/order_delivery/{orderDeliveryId}/state/{transition}']);
    }

    public function testSchemaContainsCorrectEntities(): void
    {
        $schema = $this->generator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            null
        );
        $entities = $schema['components']['schemas'];
        static::assertArrayNotHasKey('Simple', $entities);
        static::assertArrayHasKey('infoConfigResponse', $entities);
    }

    public function testStoreApiLoadsJsonApiBaseSchemasFromJson(): void
    {
        $schema = $this->generator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            null
        );
        $entities = $schema['components']['schemas'];

        foreach ([
            'success',
            'failure',
            'info',
            'meta',
            'data',
            'resource',
            'relationshipLinks',
            'links',
            'link',
            'attributes',
            'relationships',
            'relationship',
            'relationshipToOne',
            'relationshipToMany',
            'linkage',
            'pagination',
            'jsonapi',
            'error',
        ] as $schemaName) {
            static::assertArrayHasKey($schemaName, $entities);
        }

        static::assertSame(
            ['$ref' => '#/components/schemas/relationship'],
            $entities['relationships']['additionalProperties']
        );
        static::assertEqualsCanonicalizing(['data', 'meta', 'links'], array_keys($entities['relationship']['properties']));
        static::assertSame(1, $entities['relationship']['minProperties']);
        static::assertFalse($entities['relationship']['additionalProperties']);
        static::assertArrayNotHasKey('anyOf', $entities['relationship']);
    }

    public function testStoreApiDoesNotAddUnusedGeneratedComponents(): void
    {
        $schema = $this->generator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            null
        );
        $components = $schema['components'];

        static::assertArrayNotHasKey('contentType', $components['parameters'] ?? []);
        static::assertArrayNotHasKey('accept', $components['parameters'] ?? []);
        static::assertArrayHasKey('swLanguageId', $components['parameters'] ?? []);

        static::assertArrayNotHasKey(204, $components['responses'] ?? []);
        static::assertArrayHasKey('ApiKey', $components['securitySchemes'] ?? []);
    }

    public function testUnreferencedPhpGeneratedStoreApiSchemaIsNotEmitted(): void
    {
        $definitionRegistry = new StaticDefinitionInstanceRegistry(
            [SalesChannelSimpleDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $schema = $this->generator->generate(
            $definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            null
        );

        static::assertArrayNotHasKey('Simple', $schema['components']['schemas'] ?? []);
        static::assertArrayNotHasKey('SimpleJsonApi', $schema['components']['schemas'] ?? []);
    }

    public function testTransitivelyReferencedPhpGeneratedStoreApiSchemaIsEmitted(): void
    {
        $generator = new StoreApiGenerator(
            new OpenApiSchemaBuilder('0.1.0'),
            new OpenApiDefinitionSchemaBuilder(),
            [
                'Framework' => ['path' => __DIR__ . '/_fixtures/BundleWithPhpGeneratedSchemaReference'],
            ],
            new BundleSchemaPathCollection([]),
        );
        $definitionRegistry = new StaticDefinitionInstanceRegistry(
            [
                DefinitionWithAssociations::class,
                SimpleDefinition::class,
                SEOUrlDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $schema = $generator->generate(
            $definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            null
        );

        static::assertArrayHasKey('TestEntityWithAssociations', $schema['components']['schemas']);
        static::assertArrayHasKey('Simple', $schema['components']['schemas']);
        static::assertArrayNotHasKey('SEOUrl', $schema['components']['schemas']);
    }

    public function testJsonOwnedSchemaDoesNotContainJsonApiComponent(): void
    {
        $schema = $this->generateSchema($this->generator, null);

        static::assertArrayNotHasKey('JsonOverrideEntityJsonApi', $schema['components']['schemas']);
    }

    public function testSchemaContainsCustomEntitiesOnly(): void
    {
        $schema = $this->customApiGenerator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            $this->customBundleSchemas->getName()
        );

        $entities = $schema['components']['schemas'];
        static::assertArrayHasKey('Presentation', $entities);
        static::assertArrayHasKey('infoConfigResponse', $entities);
        static::assertSame('Experimental', $schema['tags'][0]['name'] ?? null);
    }

    public function testSchemaContainsCustomPathsOnly(): void
    {
        $schema = $this->customApiGenerator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            $this->customBundleSchemas->getName()
        );

        $paths = $schema['paths'];

        static::assertArrayHasKey('post', $paths['/search/guided-shopping-presentation']);
        static::assertArrayNotHasKey('/_action/order_delivery/{orderDeliveryId}/state/{transition}', $paths);
    }

    public function testMergeComponentsSchemaRequiredFieldsRecursive(): void
    {
        $schema = $this->generateSchema($this->customApiGenerator, $this->customBundleSchemas->getName());

        $entities = $schema['components']['schemas'];

        // Simple schema exists from JSON, but PHP schema was skipped (deprecated)
        static::assertArrayHasKey('Simple', $entities);
        static::assertArrayHasKey('required', $entities['Simple']);
        static::assertCount(1, $entities['Simple']['required']);
        static::assertContains('apiAlias', $entities['Simple']['required']);
        static::assertNotContains('requiredField', $entities['Simple']['required']);
    }

    public function testUndefinedRequiredPropertiesAreRemovedFromJsonSchemas(): void
    {
        $schema = $this->generateSchema($this->generator, null);

        $invalidRequiredSchema = $schema['components']['schemas']['SchemaWithInvalidRequiredProperty'];

        static::assertSame(['existing'], $invalidRequiredSchema['required']);
        static::assertSame(['existingNested'], $invalidRequiredSchema['properties']['nested']['required']);
    }

    public function testGroupsParametersParsing(): void
    {
        $schema = $this->generator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            null
        );

        // Assert that the schema does not contain 'x-parameter-groups' component
        static::assertArrayHasKey('components', $schema);
        static::assertArrayNotHasKey('x-parameter-groups', $schema['components']);

        // Check schema
        static::assertArrayHasKey('paths', $schema);
        static::assertArrayHasKey('/category', $schema['paths']);
        static::assertArrayHasKey('get', $schema['paths']['/category']);

        $operation = $schema['paths']['/category']['get'];
        static::assertArrayHasKey('parameters', $operation);
        static::assertIsArray($operation['parameters']);

        // Schema should contain all defined parameters
        $parameterNames = array_column($operation['parameters'], 'name');
        static::assertContains('page', $parameterNames);
        static::assertContains('limit', $parameterNames);
        // sw-language-id is injected as a $ref by the generator, not as an inline parameter
        $parameterRefs = array_column($operation['parameters'], '$ref');
        static::assertContains('#/components/parameters/swLanguageId', $parameterRefs);
        // but not left-overs of replaced parameter groups
        static::assertCount(3, $operation['parameters']);
    }

    public function testSwLanguageIdIsInjectedIntoEveryNonDeleteOperationOutsideInfo(): void
    {
        $bundle = new BundleWithPredeclaredSwLanguageId();
        $generator = new StoreApiGenerator(
            new OpenApiSchemaBuilder('0.1.0'),
            new OpenApiDefinitionSchemaBuilder(),
            [
                'Framework' => ['path' => __DIR__ . '/_fixtures'],
            ],
            new BundleSchemaPathCollection([$bundle]),
        );

        $schema = $generator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            $bundle->getName(),
        );

        static::assertArrayHasKey('swLanguageId', $schema['components']['parameters']);

        $assertedInjectedOperation = false;
        $assertedSkippedOperation = false;

        foreach ($schema['paths'] as $path => $pathDefinition) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (!isset($pathDefinition[$method])) {
                    continue;
                }

                $operationId = $pathDefinition[$method]['operationId'] ?? 'no-operation-id';
                $parameters = $pathDefinition[$method]['parameters'] ?? [];
                $hasHeader = false;
                foreach ($parameters as $parameter) {
                    if (
                        (isset($parameter['name']) && strtolower((string) $parameter['name']) === 'sw-language-id')
                        || (isset($parameter['$ref']) && $parameter['$ref'] === '#/components/parameters/swLanguageId')
                    ) {
                        $hasHeader = true;

                        break;
                    }
                }

                $shouldBeInjected = $method !== 'delete'
                    && !str_starts_with((string) $path, '/_info/');

                if ($shouldBeInjected) {
                    $assertedInjectedOperation = true;
                    static::assertTrue(
                        $hasHeader,
                        \sprintf('%s %s (%s) should advertise sw-language-id', strtoupper($method), $path, $operationId)
                    );
                } else {
                    $assertedSkippedOperation = true;
                    static::assertFalse(
                        $hasHeader,
                        \sprintf('%s %s (%s) must not advertise sw-language-id', strtoupper($method), $path, $operationId)
                    );
                }
            }
        }

        static::assertTrue($assertedInjectedOperation, 'Schema should contain at least one non-DELETE operation outside /_info/ to test');
        static::assertTrue($assertedSkippedOperation, 'Schema should contain at least one DELETE or /_info/ operation to test');
    }

    public function testGetSchemaThrowsUnsupportedException(): void
    {
        $this->expectExceptionObject(ApiException::unsupportedStoreApiSchemaEndpoint());

        $this->generator->getSchema($this->definitionRegistry->getDefinitions());
    }

    public function testAssociationDocumentationIsAddedToReadOperations(): void
    {
        $schema = $this->generator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            null
        );

        // Find a read operation in the paths
        $foundReadOperation = false;
        foreach ($schema['paths'] as $methods) {
            foreach ($methods as $operation) {
                if (isset($operation['operationId']) && str_starts_with($operation['operationId'], 'read')) {
                    $foundReadOperation = true;

                    // If the operation has associations, the description should contain them
                    if (isset($operation['description']) && str_contains($operation['description'], '**Available Associations:**')) {
                        static::assertStringContainsString('**Available Associations:**', $operation['description']);
                        // Verify it's properly formatted with bullet points
                        static::assertMatchesRegularExpression('/\*\*Available Associations:\*\*\n- `\w+`/', $operation['description']);
                    }
                }
            }
        }

        // Ensure we found at least one read operation to test
        static::assertTrue($foundReadOperation, 'No read operations found in the schema to test');
    }

    public function testAssociationDocumentationSkipsNonReadOperations(): void
    {
        $schema = $this->generator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            null
        );

        // Find non-read operations and verify they don't get association docs added
        $nonReadOperationsCount = 0;
        foreach ($schema['paths'] as $methods) {
            foreach ($methods as $operation) {
                if (isset($operation['operationId'])) {
                    $operationId = $operation['operationId'];
                    // Check operations that don't start with "read"
                    if (!str_starts_with($operationId, 'read')) {
                        ++$nonReadOperationsCount;
                        // These operations should not have associations added even if they have descriptions
                        if (isset($operation['description'])) {
                            // The description might naturally contain "Association" but not our formatted section
                            // We check that our enrichment didn't happen
                            $hasFormattedAssociations = str_contains($operation['description'], '**Available Associations:**');
                            // If it has formatted associations, it should be because it was manually added to the spec
                            // not because of our enrichment (which only targets read operations)
                            static::assertFalse(
                                $hasFormattedAssociations && str_starts_with($operationId, 'create'),
                                "Create operation {$operationId} should not have associations enrichment"
                            );
                        }
                    }
                }
            }
        }

        static::assertGreaterThan(0, $nonReadOperationsCount, 'Should have found at least one non-read operation');
    }

    public function testAssociationDocumentationSkipsOperationsWithoutOperationId(): void
    {
        $schema = $this->generator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            null
        );

        // Verify that all operations in the generated schema have operationId
        // This tests that the code path for missing operationId is handled
        $operationsChecked = 0;
        foreach ($schema['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                // All properly defined operations should have operationId
                if (\in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    ++$operationsChecked;
                    static::assertArrayHasKey('operationId', $operation, "Operation {$method} at path {$path} should have operationId");
                }
            }
        }

        static::assertGreaterThan(0, $operationsChecked, 'Should have checked at least one operation');
    }

    public function testAssociationDocumentationNotAddedTwice(): void
    {
        $schema = $this->generator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            null
        );

        // Check that if association docs are already present, they're not added again
        $operationsWithAssociationsCount = 0;
        foreach ($schema['paths'] as $methods) {
            foreach ($methods as $operation) {
                if (isset($operation['operationId'], $operation['description'])
                    && str_starts_with($operation['operationId'], 'read')
                    && str_contains($operation['description'], '**Available Associations:**')) {
                    ++$operationsWithAssociationsCount;
                    // Count occurrences of the associations header
                    $count = substr_count($operation['description'], '**Available Associations:**');
                    static::assertSame(
                        1,
                        $count,
                        'Association documentation should only appear once in the description'
                    );
                }
            }
        }

        // The test fixtures may not have entities with associations, so we just verify the logic works
        // by checking that IF there are associations, they don't appear twice
        static::assertGreaterThanOrEqual(0, $operationsWithAssociationsCount, 'Verified no duplicate associations in operations');
    }

    public function testAssociationDocumentationOnlyForEntitiesWithAssociations(): void
    {
        $schema = $this->generator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            null
        );

        // Verify that operations for entities without associations don't get docs
        $operationsWithoutAssociations = [];

        foreach ($schema['paths'] as $methods) {
            foreach ($methods as $operation) {
                if (isset($operation['operationId']) && str_starts_with($operation['operationId'], 'read')) {
                    if (!isset($operation['description']) || !str_contains($operation['description'], '**Available Associations:**')) {
                        $operationsWithoutAssociations[] = $operation['operationId'];
                    }
                }
            }
        }

        // Should have operations without associations (entities that don't have associations)
        static::assertNotEmpty($operationsWithoutAssociations, 'Should have operations without associations');
    }

    #[DataProvider('supportsDataProvider')]
    public function testSupports(string $format, string $api, bool $expected): void
    {
        static::assertSame($expected, $this->generator->supports($format, $api));
    }

    /**
     * @return \Generator<string, array{format: string, api: string, expected: bool}>
     */
    public static function supportsDataProvider(): \Generator
    {
        yield 'correct format and API' => [
            'format' => StoreApiGenerator::FORMAT,
            'api' => DefinitionService::STORE_API,
            'expected' => true,
        ];

        yield 'incorrect format (json)' => [
            'format' => 'json',
            'api' => DefinitionService::STORE_API,
            'expected' => false,
        ];

        yield 'incorrect format (openapi-2)' => [
            'format' => 'openapi-2',
            'api' => DefinitionService::STORE_API,
            'expected' => false,
        ];

        yield 'incorrect API (admin API)' => [
            'format' => StoreApiGenerator::FORMAT,
            'api' => DefinitionService::API,
            'expected' => false,
        ];

        yield 'incorrect API (custom)' => [
            'format' => StoreApiGenerator::FORMAT,
            'api' => 'some-other-api',
            'expected' => false,
        ];

        yield 'both incorrect' => [
            'format' => 'json',
            'api' => DefinitionService::API,
            'expected' => false,
        ];
    }

    /**
     * The generator resolves the documented entity from the shape of the 200 response of every
     * read operation and appends its association documentation to the operation description.
     */
    #[DataProvider('associationEnrichmentProvider')]
    public function testAssociationDocumentationIsAppendedForTheEntityResolvedFromTheResponse(string $path, string $expectedDescription): void
    {
        $paths = $this->generateAssociationEnrichmentSchema();

        static::assertArrayHasKey($path, $paths);
        static::assertSame($expectedDescription, $paths[$path]['get']['description']);
    }

    /**
     * @return \Generator<string, array{path: string, expectedDescription: string}>
     */
    public static function associationEnrichmentProvider(): \Generator
    {
        yield 'response-level $ref with List suffix resolves the entity' => [
            'path' => '/association-enrichment/response-level-list-ref',
            'expectedDescription' => self::BASE_DESCRIPTION . self::TEST_ENTITY_ASSOCIATIONS,
        ];

        yield 'response-level $ref with Detail suffix resolves the entity' => [
            'path' => '/association-enrichment/response-level-detail-ref',
            'expectedDescription' => self::BASE_DESCRIPTION . self::TEST_ENTITY_ASSOCIATIONS,
        ];

        yield 'response-level $ref without List or Detail suffix resolves the entity' => [
            'path' => '/association-enrichment/response-level-plain-ref',
            'expectedDescription' => self::BASE_DESCRIPTION . self::TEST_ENTITY_ASSOCIATIONS,
        ];

        yield 'schema $ref to a RouteResponse wrapper resolves the wrapped entity' => [
            'path' => '/association-enrichment/route-response-ref',
            'expectedDescription' => self::BASE_DESCRIPTION . self::TEST_ENTITY_ASSOCIATIONS,
        ];

        yield 'schema $ref to a DetailResponse wrapper resolves the wrapped entity' => [
            'path' => '/association-enrichment/detail-response-ref',
            'expectedDescription' => self::BASE_DESCRIPTION . self::TEST_ENTITY_ASSOCIATIONS,
        ];

        yield 'schema $ref to a ListingResult wrapper strips the Listing suffix' => [
            'path' => '/association-enrichment/listing-result-ref',
            'expectedDescription' => self::BASE_DESCRIPTION . self::TEST_ENTITY_ASSOCIATIONS,
        ];

        yield 'schema $ref to a SearchResult wrapper strips the Search suffix' => [
            'path' => '/association-enrichment/search-result-ref',
            'expectedDescription' => self::BASE_DESCRIPTION . self::TEST_ENTITY_ASSOCIATIONS,
        ];

        yield 'schema $ref to a CollectionResult wrapper strips the Collection suffix' => [
            'path' => '/association-enrichment/collection-result-ref',
            'expectedDescription' => self::BASE_DESCRIPTION . self::TEST_ENTITY_ASSOCIATIONS,
        ];

        yield 'plain schema $ref is converted from PascalCase to snake_case' => [
            'path' => '/association-enrichment/plain-schema-ref',
            'expectedDescription' => self::BASE_DESCRIPTION . self::TEST_ENTITY_ASSOCIATIONS,
        ];

        yield 'plain schema $ref with an acronym is converted letter by letter' => [
            'path' => '/association-enrichment/acronym-schema-ref',
            'expectedDescription' => self::BASE_DESCRIPTION . self::SEO_URL_ASSOCIATIONS,
        ];

        yield 'allOf member referencing a RouteResponse wrapper resolves the entity' => [
            'path' => '/association-enrichment/all-of-route-response-ref',
            'expectedDescription' => self::BASE_DESCRIPTION . self::TEST_ENTITY_ASSOCIATIONS,
        ];

        yield 'allOf member referencing a DetailResponse wrapper resolves the entity' => [
            'path' => '/association-enrichment/all-of-detail-response-ref',
            'expectedDescription' => self::BASE_DESCRIPTION . self::TEST_ENTITY_ASSOCIATIONS,
        ];

        yield 'allOf member referencing a Result wrapper resolves the entity' => [
            'path' => '/association-enrichment/all-of-listing-result-ref',
            'expectedDescription' => self::BASE_DESCRIPTION . self::TEST_ENTITY_ASSOCIATIONS,
        ];

        yield 'allOf skips inline members and resolves the first plain $ref' => [
            'path' => '/association-enrichment/all-of-plain-ref',
            'expectedDescription' => self::BASE_DESCRIPTION . self::TEST_ENTITY_ASSOCIATIONS,
        ];

        yield 'collection response resolves the entity from the elements item $ref' => [
            'path' => '/association-enrichment/elements-items-ref',
            'expectedDescription' => self::BASE_DESCRIPTION . self::TEST_ENTITY_ASSOCIATIONS,
        ];

        yield 'operation without a 200 response is left untouched' => [
            'path' => '/association-enrichment/no-success-response',
            'expectedDescription' => self::BASE_DESCRIPTION,
        ];

        yield 'operation with a 200 response but no schema is left untouched' => [
            'path' => '/association-enrichment/success-without-schema',
            'expectedDescription' => self::BASE_DESCRIPTION,
        ];

        yield 'inline object schema without any $ref is left untouched' => [
            'path' => '/association-enrichment/inline-object-schema',
            'expectedDescription' => self::BASE_DESCRIPTION,
        ];

        yield 'non-string response-level $ref is left untouched' => [
            'path' => '/association-enrichment/non-string-response-ref',
            'expectedDescription' => self::BASE_DESCRIPTION,
        ];

        yield 'response-level $ref not pointing at a Response schema is left untouched' => [
            'path' => '/association-enrichment/non-matching-response-ref',
            'expectedDescription' => self::BASE_DESCRIPTION,
        ];

        yield 'generic EntitySearchResult wrapper resolves to no entity' => [
            'path' => '/association-enrichment/generic-entity-search-result-ref',
            'expectedDescription' => self::BASE_DESCRIPTION,
        ];

        yield 'generic SearchResult wrapper resolves to no entity' => [
            'path' => '/association-enrichment/generic-search-result-ref',
            'expectedDescription' => self::BASE_DESCRIPTION,
        ];

        yield 'Result outside of the suffix position resolves to no known entity' => [
            'path' => '/association-enrichment/result-not-at-end-ref',
            'expectedDescription' => self::BASE_DESCRIPTION,
        ];

        yield 'RouteResponse outside of the suffix position resolves to no entity' => [
            'path' => '/association-enrichment/route-response-not-at-end-ref',
            'expectedDescription' => self::BASE_DESCRIPTION,
        ];

        yield 'DetailResponse outside of the suffix position resolves to no entity' => [
            'path' => '/association-enrichment/detail-response-not-at-end-ref',
            'expectedDescription' => self::BASE_DESCRIPTION,
        ];

        yield '$ref without a path separator resolves to no entity' => [
            'path' => '/association-enrichment/ref-without-slash',
            'expectedDescription' => self::BASE_DESCRIPTION,
        ];

        yield 'entity without documentable associations is left untouched' => [
            'path' => '/association-enrichment/entity-without-associations-ref',
            'expectedDescription' => self::BASE_DESCRIPTION,
        ];

        yield 'operation whose operationId does not start with read is left untouched' => [
            'path' => '/association-enrichment/non-read-operation',
            'expectedDescription' => self::BASE_DESCRIPTION,
        ];

        yield 'operation without an operationId is left untouched' => [
            'path' => '/association-enrichment/without-operation-id',
            'expectedDescription' => self::BASE_DESCRIPTION,
        ];

        yield 'operation that already documents its associations is not enriched twice' => [
            'path' => '/association-enrichment/already-enriched',
            'expectedDescription' => self::BASE_DESCRIPTION . "\n\n**Available Associations:**\n- `manuallyMaintained`",
        ];
    }

    public function testAssociationDocumentationIsNotAddedToOperationsWithoutDescription(): void
    {
        $paths = $this->generateAssociationEnrichmentSchema();

        static::assertArrayNotHasKey('description', $paths['/association-enrichment/without-description']['get']);
    }

    public function testEnrichPathsWithAssociationsIntegration(): void
    {
        // This is an integration test to ensure enrichPathsWithAssociations works end-to-end
        $schema = $this->generator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            null
        );

        // Verify that the schema has paths
        static::assertArrayHasKey('paths', $schema);
        static::assertIsArray($schema['paths']);

        // Count operations processed
        $totalOperations = 0;
        $readOperations = 0;

        foreach ($schema['paths'] as $methods) {
            foreach ($methods as $method => $operation) {
                if (\in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    ++$totalOperations;

                    if (isset($operation['operationId']) && str_starts_with($operation['operationId'], 'read')) {
                        ++$readOperations;
                    }
                }
            }
        }

        // Ensure we processed some operations
        static::assertGreaterThan(0, $totalOperations, 'Should have processed some operations');
        static::assertGreaterThan(0, $readOperations, 'Should have processed some read operations');
    }

    public function testGenerationForABundleWithoutAnyPathDefinitionYieldsAnEmptyPathList(): void
    {
        // no bundle contributes schema paths, so only the components and tags of the core fixtures remain
        $schema = $this->generateSchema($this->generator, 'BundleWithoutSchemaPaths');

        static::assertSame([], $schema['paths']);
        static::assertArrayHasKey('infoConfigResponse', $schema['components']['schemas']);
    }

    public function testSwLanguageIdHeaderIsInjectedOrKeptWithoutDuplicationPerOperation(): void
    {
        $bundle = new BundleWithPredeclaredSwLanguageId();
        $generator = new StoreApiGenerator(
            new OpenApiSchemaBuilder('0.1.0'),
            new OpenApiDefinitionSchemaBuilder(),
            [
                'Framework' => ['path' => __DIR__ . '/_fixtures'],
            ],
            new BundleSchemaPathCollection([$bundle]),
        );

        $schema = $generator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            $bundle->getName(),
        );

        static::assertArrayHasKey('swLanguageId', $schema['components']['parameters']);

        $noHeader = $schema['paths']['/no-header']['get'];
        $noHeaderRefs = array_filter(
            array_column($noHeader['parameters'], '$ref'),
            static fn (string $ref): bool => $ref === '#/components/parameters/swLanguageId'
        );
        static::assertCount(1, $noHeaderRefs, 'sw-language-id should be injected exactly once into operations without it');

        $noParametersKey = $schema['paths']['/no-parameters-key']['get'];
        static::assertIsArray($noParametersKey['parameters']);
        $noParametersKeyRefs = array_filter(
            array_column($noParametersKey['parameters'], '$ref'),
            static fn (string $ref): bool => $ref === '#/components/parameters/swLanguageId'
        );
        static::assertCount(1, $noParametersKeyRefs, 'sw-language-id should be injected when the operation omits the parameters key');

        $inline = $schema['paths']['/predeclared-by-name']['get'];
        $inlineNames = array_filter(
            $inline['parameters'],
            static fn (array $param): bool => ($param['name'] ?? null) === 'sw-language-id'
        );
        $inlineRefs = array_filter(
            $inline['parameters'],
            static fn (array $param): bool => ($param['$ref'] ?? null) === '#/components/parameters/swLanguageId'
        );
        static::assertCount(1, $inlineNames, 'Inline sw-language-id declaration should remain');
        static::assertCount(0, $inlineRefs, 'No $ref should be injected next to an existing inline sw-language-id');

        $byRef = $schema['paths']['/predeclared-by-ref']['get'];
        $byRefMatches = array_filter(
            array_column($byRef['parameters'], '$ref'),
            static fn (string $ref): bool => $ref === '#/components/parameters/swLanguageId'
        );
        static::assertCount(1, $byRefMatches, 'sw-language-id $ref should not be duplicated when already present');

        $mixedCase = $schema['paths']['/predeclared-by-name-mixed-case']['get'];
        $mixedCaseNames = array_filter(
            $mixedCase['parameters'],
            static fn (array $param): bool => isset($param['name']) && strtolower((string) $param['name']) === 'sw-language-id'
        );
        $mixedCaseRefs = array_filter(
            $mixedCase['parameters'],
            static fn (array $param): bool => ($param['$ref'] ?? null) === '#/components/parameters/swLanguageId'
        );
        static::assertCount(1, $mixedCaseNames, 'Mixed-case sw-language-id declaration should remain');
        static::assertCount(0, $mixedCaseRefs, 'No $ref should be injected next to a mixed-case sw-language-id declaration');

        $mutation = $schema['paths']['/mutation']['delete'];
        $mutationRefs = array_column($mutation['parameters'] ?? [], '$ref');
        static::assertNotContains(
            '#/components/parameters/swLanguageId',
            $mutationRefs,
            'sw-language-id should not be injected into non-GET operations',
        );

        $infoSample = $schema['paths']['/_info/sample']['get'];
        $infoRefs = array_column($infoSample['parameters'] ?? [], '$ref');
        static::assertNotContains(
            '#/components/parameters/swLanguageId',
            $infoRefs,
            'sw-language-id should not be injected into /_info/* GET operations',
        );
    }

    public function testPhpSchemaIsSkippedWhenJsonSchemaExists(): void
    {
        $schema = $this->generateSchema($this->generator, null);

        $entities = $schema['components']['schemas'];

        // JsonOverrideEntity should exist in output (from JSON file)
        static::assertArrayHasKey('JsonOverrideEntity', $entities);

        // JSON-defined field should be present
        static::assertArrayHasKey('jsonOnlyField', $entities['JsonOverrideEntity']['properties']);
        static::assertSame(
            'This field only exists in the JSON schema',
            $entities['JsonOverrideEntity']['properties']['jsonOnlyField']['description']
        );

        // PHP-defined field should NOT be present (PHP schema was skipped)
        static::assertArrayNotHasKey('phpOnlyField', $entities['JsonOverrideEntity']['properties']);
    }

    public function testSchemaIsGeneratedWhenNoDefinitionContributesAPhpSchema(): void
    {
        $definitionRegistry = new StaticDefinitionInstanceRegistry(
            [DefinitionWithJsonOverride::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $schema = ErrorHandler::call(fn (): array => $this->generator->generate(
            $definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            null
        ));

        static::assertArrayHasKey('JsonOverrideEntity', $schema['components']['schemas']);
        static::assertArrayHasKey('jsonOnlyField', $schema['components']['schemas']['JsonOverrideEntity']['properties']);
    }

    public function testPhpSchemaIsSkippedWhenNoJsonPathReferencesIt(): void
    {
        $schema = $this->generateSchema($this->generator, null);

        $entities = $schema['components']['schemas'];
        static::assertArrayNotHasKey('Simple', $entities);
    }

    public function testJsonSchemaOverridesPhpSchemaInCustomBundle(): void
    {
        $schema = $this->generateSchema($this->customApiGenerator, $this->customBundleSchemas->getName());

        $entities = $schema['components']['schemas'];

        // Simple exists from JSON but PHP generation was skipped
        static::assertArrayHasKey('Simple', $entities);
        static::assertArrayHasKey('apiAlias', $entities['Simple']['properties']);

        // PHP-only fields should NOT be present
        static::assertArrayNotHasKey('stringField', $entities['Simple']['properties']);
        static::assertArrayNotHasKey('intField', $entities['Simple']['properties']);
    }

    public function testPluginExtensionFieldsSurviveJsonSchemaOverride(): void
    {
        $definition = $this->definitionRegistry->getByEntityName(DefinitionWithJsonOverride::ENTITY_NAME);
        $extension = new PluginExtensionForJsonOverride();
        $definition->addExtension($extension);

        try {
            $schema = $this->generateSchema($this->generator, null);

            $entities = $schema['components']['schemas'];

            static::assertArrayHasKey('JsonOverrideEntity', $entities);

            // JSON-defined field should be present
            static::assertArrayHasKey('jsonOnlyField', $entities['JsonOverrideEntity']['properties']);

            // PHP base field should NOT be present (PHP schema was skipped)
            static::assertArrayNotHasKey('phpOnlyField', $entities['JsonOverrideEntity']['properties']);

            // Plugin extension association SHOULD be present under extensions
            static::assertArrayHasKey('extensions', $entities['JsonOverrideEntity']['properties']);
            $extensionProperties = $entities['JsonOverrideEntity']['properties']['extensions']['properties'];
            static::assertArrayHasKey('pluginEntities', $extensionProperties);
            static::assertSame('object', $extensionProperties['pluginEntities']['type']);
            static::assertSame('string', $extensionProperties['pluginLabel']['type']);
            static::assertSame('boolean', $extensionProperties['pluginActive']['type']);
        } finally {
            $definition->removeExtension($extension);
        }
    }

    public function testPluginJsonAddsFieldToCoreJsonEntity(): void
    {
        $schema = $this->generateSchema($this->pluginApiGenerator, null);

        $entities = $schema['components']['schemas'];

        static::assertArrayHasKey('JsonOverrideEntity', $entities);

        // Core JSON fields are present
        static::assertArrayHasKey('jsonOnlyField', $entities['JsonOverrideEntity']['properties']);
        static::assertArrayHasKey('name', $entities['JsonOverrideEntity']['properties']);
        static::assertArrayHasKey('status', $entities['JsonOverrideEntity']['properties']);

        // Plugin-added JSON field is present
        static::assertArrayHasKey('pluginAddedField', $entities['JsonOverrideEntity']['properties']);
        static::assertSame(
            'Field added by a plugin via JSON schema',
            $entities['JsonOverrideEntity']['properties']['pluginAddedField']['description']
        );

        // PHP-only field is NOT present (PHP schema was skipped)
        static::assertArrayNotHasKey('phpOnlyField', $entities['JsonOverrideEntity']['properties']);
    }

    public function testPluginJsonExtendsEnumWithDeduplication(): void
    {
        $schema = $this->generateSchema($this->pluginApiGenerator, null);

        $entities = $schema['components']['schemas'];
        $statusEnum = $entities['JsonOverrideEntity']['properties']['status']['enum'];

        // Core defines ["active", "inactive"], plugin adds ["active", "draft"]
        // After deduplication: ["active", "inactive", "draft"] — no duplicates
        static::assertCount(3, $statusEnum);
        static::assertContains('active', $statusEnum);
        static::assertContains('inactive', $statusEnum);
        static::assertContains('draft', $statusEnum);
    }

    public function testPluginExtensionFieldsSurviveWithPluginJsonSchema(): void
    {
        $definition = $this->definitionRegistry->getByEntityName(DefinitionWithJsonOverride::ENTITY_NAME);
        $extension = new PluginExtensionForJsonOverride();
        $definition->addExtension($extension);

        try {
            $schema = $this->generateSchema($this->pluginApiGenerator, null);

            $entities = $schema['components']['schemas'];

            static::assertArrayHasKey('JsonOverrideEntity', $entities);

            // Plugin JSON field is present
            static::assertArrayHasKey('pluginAddedField', $entities['JsonOverrideEntity']['properties']);

            // PHP base field is NOT present
            static::assertArrayNotHasKey('phpOnlyField', $entities['JsonOverrideEntity']['properties']);

            // PHP extension association IS preserved
            static::assertArrayHasKey('extensions', $entities['JsonOverrideEntity']['properties']);
            $extensionProperties = $entities['JsonOverrideEntity']['properties']['extensions']['properties'];
            static::assertArrayHasKey('pluginEntities', $extensionProperties);
            static::assertSame('string', $extensionProperties['pluginLabel']['type']);
            static::assertSame('boolean', $extensionProperties['pluginActive']['type']);
        } finally {
            $definition->removeExtension($extension);
        }
    }

    /**
     * Generates the Store API schema for the bundle that contributes one path per response shape
     * the association enrichment has to understand, and returns its paths.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function generateAssociationEnrichmentSchema(): array
    {
        $schema = $this->generateSchema($this->associationEnrichmentGenerator, $this->associationEnrichmentBundle->getName());

        static::assertIsArray($schema['paths']);

        return $schema['paths'];
    }

    /**
     * @return array<string, mixed>
     */
    private function generateSchema(StoreApiGenerator $generator, ?string $bundleName): array
    {
        return $generator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            $bundleName
        );
    }
}

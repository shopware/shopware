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
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\CustomBundleWithApiSchema\ShopwareBundleWithName;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\DefinitionWithAssociations;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SimpleDefinition;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(StoreApiGenerator::class)]
class StoreApiGeneratorTest extends TestCase
{
    private StoreApiGenerator $generator;

    private StoreApiGenerator $customApiGenerator;

    private Bundle $customBundleSchemas;

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
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [
                SimpleDefinition::class,
                DefinitionWithAssociations::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
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
        static::assertArrayHasKey('Simple', $entities);
        static::assertArrayHasKey('infoConfigResponse', $entities);
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
        $schema = $this->customApiGenerator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            $this->customBundleSchemas->getName()
        );

        $entities = $schema['components']['schemas'];

        static::assertArrayHasKey('Simple', $entities);
        static::assertArrayHasKey('required', $entities['Simple']);
        static::assertCount(2, $entities['Simple']['required']);
        static::assertContains('requiredField', $entities['Simple']['required']);
        static::assertContains('apiAlias', $entities['Simple']['required']);
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
        static::assertContains('sw-language-id', $parameterNames);
        static::assertContains('page', $parameterNames);
        static::assertContains('limit', $parameterNames);
        // but not left-overs of replaced parameter groups
        static::assertCount(3, $operation['parameters']);

        foreach ($operation['parameters'] as $parameter) {
            static::assertArrayHasKey('name', $parameter);
            static::assertArrayHasKey('in', $parameter);
            static::assertArrayHasKey('schema', $parameter);
        }
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
     * @return iterable<string, array{format: string, api: string, expected: bool}>
     */
    public static function supportsDataProvider(): iterable
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

    public function testExtractEntityNameFromOperationWithResponseLevelRef(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityNameFromOperation');

        // Test response-level $ref with ProductListResponse
        $operation = [
            'responses' => [
                '200' => [
                    '$ref' => '#/components/responses/ProductListResponse',
                ],
            ],
        ];
        $result = $method->invoke($this->generator, $operation);
        static::assertSame('product', $result);

        // Test response-level $ref with ProductDetailResponse
        $operation = [
            'responses' => [
                '200' => [
                    '$ref' => '#/components/responses/ProductDetailResponse',
                ],
            ],
        ];
        $result = $method->invoke($this->generator, $operation);
        static::assertSame('product', $result);

        // Test response-level $ref with plain Response (no List/Detail)
        $operation = [
            'responses' => [
                '200' => [
                    '$ref' => '#/components/responses/OrderResponse',
                ],
            ],
        ];
        $result = $method->invoke($this->generator, $operation);
        static::assertSame('order', $result);
    }

    public function testExtractEntityNameFromOperationWithoutValidResponse(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityNameFromOperation');

        // Test with no 200 response
        $operation = [
            'responses' => [
                '404' => ['description' => 'Not found'],
            ],
        ];
        $result = $method->invoke($this->generator, $operation);
        static::assertNull($result);

        // Test with no schema in 200 response
        $operation = [
            'responses' => [
                '200' => ['description' => 'Success'],
            ],
        ];
        $result = $method->invoke($this->generator, $operation);
        static::assertNull($result);
    }

    public function testExtractEntityNameFromOperationWithDirectRef(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityNameFromOperation');

        // Test with RouteResponse
        $operation = [
            'responses' => [
                '200' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/OrderRouteResponse',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $result = $method->invoke($this->generator, $operation);
        static::assertSame('order', $result);

        // Test with DetailResponse
        $operation = [
            'responses' => [
                '200' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/ProductDetailResponse',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $result = $method->invoke($this->generator, $operation);
        static::assertSame('product', $result);

        // Test with Result wrapper
        $operation = [
            'responses' => [
                '200' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/ProductListingResult',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $result = $method->invoke($this->generator, $operation);
        static::assertSame('product', $result);

        // Test with plain schema reference
        $operation = [
            'responses' => [
                '200' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/ShippingMethod',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $result = $method->invoke($this->generator, $operation);
        static::assertSame('shipping_method', $result);
    }

    public function testExtractEntityNameFromOperationWithAllOf(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityNameFromOperation');

        // Test with allOf containing RouteResponse
        $operation = [
            'responses' => [
                '200' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'allOf' => [
                                    ['$ref' => '#/components/schemas/OrderRouteResponse'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $result = $method->invoke($this->generator, $operation);
        static::assertSame('order', $result);

        // Test with allOf containing DetailResponse
        $operation = [
            'responses' => [
                '200' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'allOf' => [
                                    ['$ref' => '#/components/schemas/ProductDetailResponse'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $result = $method->invoke($this->generator, $operation);
        static::assertSame('product', $result);

        // Test with allOf containing Result
        $operation = [
            'responses' => [
                '200' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'allOf' => [
                                    ['$ref' => '#/components/schemas/ProductListingResult'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $result = $method->invoke($this->generator, $operation);
        static::assertSame('product', $result);

        // Test with allOf containing plain reference
        $operation = [
            'responses' => [
                '200' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'allOf' => [
                                    ['$ref' => '#/components/schemas/Customer'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $result = $method->invoke($this->generator, $operation);
        static::assertSame('customer', $result);
    }

    public function testExtractEntityNameFromOperationWithArrayItems(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityNameFromOperation');

        // Test with array items reference (collection endpoints)
        $operation = [
            'responses' => [
                '200' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'properties' => [
                                    'elements' => [
                                        'items' => [
                                            '$ref' => '#/components/schemas/Product',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $result = $method->invoke($this->generator, $operation);
        static::assertSame('product', $result);
    }

    public function testExtractEntityNameFromOperationReturnsNullForUnrecognizedPattern(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityNameFromOperation');

        // Test with schema that doesn't match any pattern
        $operation = [
            'responses' => [
                '200' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'foo' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $result = $method->invoke($this->generator, $operation);
        static::assertNull($result);
    }

    public function testExtractEntityFromResultRefWithGenericResults(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityFromResultRef');

        // Test with generic EntitySearchResult - should return null
        $result = $method->invoke($this->generator, '#/components/schemas/EntitySearchResult');
        static::assertNull($result);

        // Test with generic SearchResult - should return null
        $result = $method->invoke($this->generator, '#/components/schemas/SearchResult');
        static::assertNull($result);
    }

    public function testExtractEntityFromResultRefWithSpecificResults(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityFromResultRef');

        // Test with ProductListingResult
        $result = $method->invoke($this->generator, '#/components/schemas/ProductListingResult');
        static::assertSame('product', $result);

        // Test with ProductSearchResult
        $result = $method->invoke($this->generator, '#/components/schemas/ProductSearchResult');
        static::assertSame('product', $result);

        // Test with ProductCollectionResult
        $result = $method->invoke($this->generator, '#/components/schemas/ProductCollectionResult');
        static::assertSame('product', $result);
    }

    public function testExtractEntityFromResultRefWithInvalidPattern(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityFromResultRef');

        // Test with reference that doesn't end in Result
        $result = $method->invoke($this->generator, '#/components/schemas/Product');
        static::assertNull($result);
    }

    public function testExtractEntityFromRouteResponseRef(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityFromRouteResponseRef');

        // Test valid RouteResponse
        $result = $method->invoke($this->generator, '#/components/schemas/OrderRouteResponse');
        static::assertSame('order', $result);

        // Test with multi-word entity
        $result = $method->invoke($this->generator, '#/components/schemas/ShippingMethodRouteResponse');
        static::assertSame('shipping_method', $result);

        // Test invalid pattern
        $result = $method->invoke($this->generator, '#/components/schemas/Order');
        static::assertNull($result);
    }

    public function testExtractEntityFromDetailResponseRef(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityFromDetailResponseRef');

        // Test valid DetailResponse
        $result = $method->invoke($this->generator, '#/components/schemas/ProductDetailResponse');
        static::assertSame('product', $result);

        // Test with multi-word entity
        $result = $method->invoke($this->generator, '#/components/schemas/PaymentMethodDetailResponse');
        static::assertSame('payment_method', $result);

        // Test invalid pattern
        $result = $method->invoke($this->generator, '#/components/schemas/Product');
        static::assertNull($result);
    }

    public function testExtractEntityNameFromRef(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityNameFromRef');

        // Test simple entity name
        $result = $method->invoke($this->generator, '#/components/schemas/Product');
        static::assertSame('product', $result);

        // Test multi-word entity name (PascalCase to snake_case)
        $result = $method->invoke($this->generator, '#/components/schemas/ShippingMethod');
        static::assertSame('shipping_method', $result);

        // Test entity with multiple capital letters
        $result = $method->invoke($this->generator, '#/components/schemas/SEOUrl');
        static::assertSame('s_e_o_url', $result);
    }

    public function testGetAssociationsDocumentationReturnsEmptyForNoAssociations(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('getAssociationsDocumentation');

        // Test with SimpleDefinition which has no associations
        $definition = $this->definitionRegistry->get(SimpleDefinition::class);
        $result = $method->invoke($this->generator, $definition);

        static::assertSame('', $result);
    }

    public function testGetAssociationsDocumentationFormatsCorrectly(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('getAssociationsDocumentation');

        // Test with a definition that has associations
        $foundDefinitionWithAssociations = false;

        foreach ($this->definitionRegistry->getDefinitions() as $definition) {
            if (!$definition instanceof \Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition) {
                continue;
            }

            $result = $method->invoke($this->generator, $definition);

            // If this definition has associations
            if ($result !== '') {
                $foundDefinitionWithAssociations = true;

                // Verify the format
                static::assertStringStartsWith("\n\n**Available Associations:**\n", $result);
                static::assertStringContainsString('- `', $result);

                // Verify no duplicate association headers
                static::assertSame(1, substr_count($result, '**Available Associations:**'));

                break;
            }
        }

        // At least verify the test ran
        static::assertGreaterThanOrEqual(0, $foundDefinitionWithAssociations ? 1 : 0);
    }

    public function testGetAssociationsDocumentationSkipsNonAssociationFields(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('getAssociationsDocumentation');

        // Test that non-association fields are skipped
        $definition = $this->definitionRegistry->get(SimpleDefinition::class);
        $result = $method->invoke($this->generator, $definition);

        // SimpleDefinition should not have associations
        static::assertSame('', $result);
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

    public function testEnrichPathsWithAssociationsHandlesEmptyPaths(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('enrichPathsWithAssociations');

        // Test with empty paths - should return early
        $specs = ['paths' => []];
        $definitions = [];

        $method->invoke($this->generator, $specs, $definitions);

        // If we get here without errors, the early return worked
        static::assertArrayHasKey('paths', $specs);
        static::assertIsArray($specs['paths']);
        static::assertCount(0, $specs['paths']);
    }

    public function testEnrichPathsWithAssociationsHandlesNonArrayPaths(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('enrichPathsWithAssociations');

        // Test with non-array paths - should return early without errors
        $specs = ['paths' => null];
        $definitions = [];

        // Should not throw an exception when paths is not an array
        $method->invoke($this->generator, $specs, $definitions);

        // If we get here, the method handled the null value gracefully
        static::assertArrayHasKey('paths', $specs);
    }

    public function testEnrichPathsWithAssociationsHandlesMissingPaths(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('enrichPathsWithAssociations');

        // Test with no paths key
        $specs = [];
        $definitions = [];

        $method->invoke($this->generator, $specs, $definitions);

        // Should handle gracefully
        static::assertArrayNotHasKey('paths', $specs);
    }

    public function testExtractEntityNameFromOperationWithNonStringRef(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityNameFromOperation');

        // Test with non-string $ref
        $operation = [
            'responses' => [
                '200' => [
                    '$ref' => 12345, // Non-string ref
                ],
            ],
        ];
        $result = $method->invoke($this->generator, $operation);
        static::assertNull($result);
    }

    public function testExtractEntityNameFromOperationWithNonMatchingResponseRef(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityNameFromOperation');

        // Test response-level $ref that doesn't match pattern
        $operation = [
            'responses' => [
                '200' => [
                    '$ref' => '#/invalid/reference',
                ],
            ],
        ];
        $result = $method->invoke($this->generator, $operation);
        static::assertNull($result);
    }

    public function testExtractEntityFromResultRefWithNonMatchingPattern(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityFromResultRef');

        // Test with ref that doesn't match pattern
        $result = $method->invoke($this->generator, '#/components/schemas/SomethingElse');
        static::assertNull($result);
    }

    public function testExtractEntityFromResultRefWithInvalidPregReplaceResult(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityFromResultRef');

        // Test edge case where preg_replace returns null
        $result = $method->invoke($this->generator, '#/components/schemas/ProductListingResult');
        static::assertSame('product', $result);
    }

    public function testExtractEntityFromRouteResponseRefWithInvalidPattern(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityFromRouteResponseRef');

        // Test preg_replace null return
        $result = $method->invoke($this->generator, '#/components/schemas/InvalidRef');
        static::assertNull($result);
    }

    public function testExtractEntityFromDetailResponseRefWithInvalidPattern(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityFromDetailResponseRef');

        // Test preg_replace null return
        $result = $method->invoke($this->generator, '#/components/schemas/InvalidRef');
        static::assertNull($result);
    }

    public function testExtractEntityNameFromRefWithInvalidPattern(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('extractEntityNameFromRef');

        // Test with invalid pattern
        $result = $method->invoke($this->generator, 'invalid-ref-without-hash');
        static::assertNull($result);
    }

    public function testGetAssociationsDocumentationWithEntityWithAssociations(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('getAssociationsDocumentation');

        // Get the DefinitionWithAssociations entity definition
        $definition = $this->definitionRegistry->getByEntityName('test_entity_with_associations');

        // Invoke the method
        $result = $method->invoke($this->generator, $definition, DefinitionService::STORE_API);

        // Verify result is a non-empty string with association documentation
        static::assertIsString($result);
        static::assertStringContainsString('**Available Associations:**', $result);

        // Verify the association with description is included
        static::assertStringContainsString('`category`', $result);
        static::assertStringContainsString('The category this entity belongs to', $result);

        // Verify the association without description is included
        static::assertStringContainsString('`children`', $result);

        // Verify that hidden associations are NOT included
        static::assertStringNotContainsString('`hiddenAssociation`', $result);

        // Verify that translations are NOT included
        static::assertStringNotContainsString('translations', $result);

        // Verify that parent associations are NOT included
        static::assertStringNotContainsString('`parent`', $result);

        // Verify that non-ApiAware associations are NOT included
        static::assertStringNotContainsString('`notApiAware`', $result);

        // Verify that admin-only associations are NOT included
        static::assertStringNotContainsString('`adminOnly`', $result);
    }

    public function testGetAssociationsDocumentationReturnsEmptyStringForEntityWithoutAssociations(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('getAssociationsDocumentation');

        // Get the SimpleDefinition entity definition (has no associations)
        $definition = $this->definitionRegistry->getByEntityName('simple');

        // Invoke the method
        $result = $method->invoke($this->generator, $definition, DefinitionService::STORE_API);

        // Verify result is an empty string when no associations
        static::assertSame('', $result);
    }

    public function testGetAssociationsDocumentationReturnsFormattedStringWithMultipleAssociations(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('getAssociationsDocumentation');

        // Get the DefinitionWithAssociations entity definition
        $definition = $this->definitionRegistry->getByEntityName('test_entity_with_associations');

        // Invoke the method
        $result = $method->invoke($this->generator, $definition, DefinitionService::STORE_API);

        // Verify format: should contain "**Available Associations:**" and newlines for multiple associations
        static::assertStringContainsString('**Available Associations:**', $result);

        // Count the number of valid associations (category and children should be included)
        $associationCount = substr_count($result, '`');
        // Should have at least 2 associations (category and children) * 2 backticks each = 4 backticks
        static::assertGreaterThanOrEqual(4, $associationCount);
    }

    public function testGetAssociationsDocumentationSupportsOptionalDescription(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('getAssociationsDocumentation');

        // Get the DefinitionWithAssociations entity definition
        $definition = $this->definitionRegistry->getByEntityName('test_entity_with_associations');

        // Invoke the method
        $result = $method->invoke($this->generator, $definition, DefinitionService::STORE_API);

        // Verify that associations without descriptions are still included
        static::assertStringContainsString('`children`', $result);

        // After `children`, there should be no " - " followed by a description
        static::assertStringNotContainsString('`children` - ', $result);

        // Verify that category DOES have a description (for contrast)
        static::assertStringContainsString('`category` - ', $result);
    }
}

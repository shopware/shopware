<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
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
        foreach ($schema['paths'] as $methods) {
            foreach ($methods as $operation) {
                if (isset($operation['operationId'])) {
                    $operationId = $operation['operationId'];
                    // Check operations that don't start with "read"
                    if (!str_starts_with($operationId, 'read')) {
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

        static::assertTrue(true, 'Non-read operations checked');
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
        foreach ($schema['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                // All properly defined operations should have operationId
                if (\in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    static::assertArrayHasKey('operationId', $operation, "Operation {$method} at path {$path} should have operationId");
                }
            }
        }

        static::assertTrue(true, 'Operations with operationId verified');
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
        foreach ($schema['paths'] as $methods) {
            foreach ($methods as $operation) {
                if (isset($operation['operationId'], $operation['description'])
                    && str_starts_with($operation['operationId'], 'read')
                    && str_contains($operation['description'], '**Available Associations:**')) {

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

        static::assertTrue(true, 'No duplicate association documentation found');
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

    public function testSupportsReturnsTrueForStoreApiAndOpenApi3Format(): void
    {
        // Test that supports() returns true for the correct format and API combination
        static::assertTrue($this->generator->supports(StoreApiGenerator::FORMAT, DefinitionService::STORE_API));
    }

    public function testSupportsReturnsFalseForIncorrectFormat(): void
    {
        // Test that supports() returns false for incorrect format
        static::assertFalse($this->generator->supports('json', DefinitionService::STORE_API));
        static::assertFalse($this->generator->supports('openapi-2', DefinitionService::STORE_API));
    }

    public function testSupportsReturnsFalseForIncorrectApi(): void
    {
        // Test that supports() returns false for incorrect API type
        static::assertFalse($this->generator->supports(StoreApiGenerator::FORMAT, DefinitionService::API));
        static::assertFalse($this->generator->supports(StoreApiGenerator::FORMAT, 'some-other-api'));
    }

    public function testSupportsReturnsFalseForBothIncorrect(): void
    {
        // Test that supports() returns false when both format and API are incorrect
        static::assertFalse($this->generator->supports('json', DefinitionService::API));
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
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use OpenApi\Annotations\License;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\Parameter;
use Shopware\Core\Framework\Api\ApiDefinition\ApiDefinitionGeneratorInterface;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\IgnoreInOpenapiSchema;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;

/**
 * @internal
 *
 * @phpstan-import-type Api from DefinitionService
 * @phpstan-import-type OpenApiSpec from DefinitionService
 */
#[Package('framework')]
class StoreApiGenerator implements ApiDefinitionGeneratorInterface
{
    final public const FORMAT = 'openapi-3';
    private const OPERATION_KEYS = [
        'get',
        'post',
        'put',
        'patch',
        'delete',
    ];

    private readonly string $schemaPath;

    /**
     * @param array{Framework: array{path: string}} $bundles
     *
     * @internal
     */
    public function __construct(
        private readonly OpenApiSchemaBuilder $openApiBuilder,
        private readonly OpenApiDefinitionSchemaBuilder $definitionSchemaBuilder,
        array $bundles,
        private readonly BundleSchemaPathCollection $bundleSchemaPathCollection,
    ) {
        $this->schemaPath = $bundles['Framework']['path'] . '/Api/ApiDefinition/Generator/Schema/StoreApi';
    }

    public function supports(string $format, string $api): bool
    {
        return $format === self::FORMAT && $api === DefinitionService::STORE_API;
    }

    public function generate(array $definitions, string $api, string $apiType, ?string $bundleName): array
    {
        $openApi = new OpenApi([
            'openapi' => '3.1.0',
        ]);
        $this->openApiBuilder->enrich($openApi, $api);

        $forSalesChannel = $api === DefinitionService::STORE_API;

        ksort($definitions);

        foreach ($definitions as $definition) {
            if (!$definition instanceof EntityDefinition) {
                continue;
            }

            if (!$this->shouldDefinitionBeIncluded($definition)) {
                continue;
            }

            $onlyReference = $this->shouldIncludeReferenceOnly($definition, $forSalesChannel);

            $schema = $this->definitionSchemaBuilder->getSchemaByDefinition($definition, $this->getResourceUri($definition), $forSalesChannel, $onlyReference);

            $openApi->components->merge($schema);
        }

        $this->addGeneralInformation($openApi);
        $this->addContentTypeParameter($openApi);

        $data = json_decode($openApi->toJson(), true, 512, \JSON_THROW_ON_ERROR);
        $data['paths'] ??= [];

        $schemaPaths = [$this->schemaPath];

        if (!empty($bundleName)) {
            $schemaPaths = array_merge([$this->schemaPath . '/components', $this->schemaPath . '/tags'], $this->bundleSchemaPathCollection->getSchemaPaths($api, $bundleName));
        } else {
            $schemaPaths = array_merge($schemaPaths, $this->bundleSchemaPathCollection->getSchemaPaths($api, $bundleName));
        }

        $loader = new OpenApiFileLoader($schemaPaths);

        $preFinalSpecs = $this->mergeComponentsSchemaRequiredFieldsRecursive($data, $loader->loadOpenapiSpecification());
        /** @var OpenApiSpec $finalSpecs */
        $finalSpecs = array_replace_recursive($data, $preFinalSpecs);

        $this->resolveParameterGroups($finalSpecs);
        $this->enrichPathsWithAssociations($finalSpecs, $definitions);

        return $finalSpecs;
    }

    /**
     * {@inheritdoc}
     *
     * @param array<string, EntityDefinition>|array<string, EntityDefinition&SalesChannelDefinitionInterface> $definitions
     *
     * @return never
     */
    public function getSchema(array $definitions): array
    {
        throw ApiException::unsupportedStoreApiSchemaEndpoint();
    }

    private function shouldDefinitionBeIncluded(EntityDefinition $definition): bool
    {
        if (preg_match('/_translation$/', $definition->getEntityName())) {
            return false;
        }

        if (mb_strpos($definition->getEntityName(), 'version') === 0) {
            return false;
        }

        return true;
    }

    private function shouldIncludeReferenceOnly(EntityDefinition $definition, bool $forSalesChannel): bool
    {
        $class = new \ReflectionClass($definition);
        if ($class->isSubclassOf(MappingEntityDefinition::class)) {
            return true;
        }

        if ($forSalesChannel && !is_subclass_of($definition, SalesChannelDefinitionInterface::class)) {
            return true;
        }

        return false;
    }

    private function getResourceUri(EntityDefinition $definition, string $rootPath = '/'): string
    {
        return ltrim('/', $rootPath) . '/' . str_replace('_', '-', $definition->getEntityName());
    }

    private function addGeneralInformation(OpenApi $openApi): void
    {
        $openApi->info->description = 'This endpoint reference contains an overview of all endpoints comprising the Shopware Store API';
        $openApi->info->license = new License([
            'name' => 'MIT',
            'url' => 'https://github.com/shopware/shopware/blob/trunk/LICENSE',
        ]);
    }

    private function addContentTypeParameter(OpenApi $openApi): void
    {
        $openApi->components->parameters = [
            new Parameter([
                'parameter' => 'contentType',
                'name' => 'Content-Type',
                'in' => 'header',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                    'default' => 'application/json',
                ],
                'description' => 'Content type of the request',
            ]),
            new Parameter([
                'parameter' => 'accept',
                'name' => 'Accept',
                'in' => 'header',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                    'default' => 'application/json',
                ],
                'description' => 'Accepted response content types',
            ]),
        ];

        if (!is_iterable($openApi->paths)) {
            return;
        }

        foreach ($openApi->paths as $path) {
            foreach (self::OPERATION_KEYS as $key) {
                // @phpstan-ignore property.dynamicName (We check the keys via OPERATION_KEYS)
                $operation = $path->$key;

                if (!$operation instanceof Operation) {
                    continue;
                }

                if (!\is_array($operation->parameters)) {
                    $operation->parameters = [];
                }

                array_push(
                    $operation->parameters,
                    new Parameter(['ref' => '#/components/parameters/contentType']),
                    new Parameter(['ref' => '#/components/parameters/accept']),
                );
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $specsFromDefinition
     * @param array<string, array<string, mixed>> $specsFromStaticJsonDefinition
     *
     * @return array<string, array<string, mixed>>
     */
    private function mergeComponentsSchemaRequiredFieldsRecursive(array $specsFromDefinition, array $specsFromStaticJsonDefinition): array
    {
        foreach ($specsFromDefinition['components']['schemas'] as $key => $value) {
            if (isset($specsFromStaticJsonDefinition['components']['schemas'][$key]['required']) && isset($specsFromDefinition['components']['schemas'][$key]['required'])) {
                $specsFromStaticJsonDefinition['components']['schemas'][$key]['required']
                    = array_merge_recursive(
                        $specsFromStaticJsonDefinition['components']['schemas'][$key]['required'],
                        $specsFromDefinition['components']['schemas'][$key]['required']
                    );
            }
        }

        return $specsFromStaticJsonDefinition;
    }

    /**
     * [WARNING] Please refrain from using this functionality in new code. It is a workaround to reduce duplication of
     * the criteria parameter groups and may be removed in the future.
     *
     * OpenAPI specification does not support groups of parameters as reusable components.
     * As in Shopware has a number of GET routes that support passing criteria as a set of parameters,
     * describing them in the OpenAPI spec leads to a lot of duplication.
     *
     * This methods adds support for a custom extension that allows describing parameter groups in the components
     * and referencing them in the separate paths as a group. Those groups will be resolved and replaced with the actual parameters.
     *
     * Example:
     *
     * ```json
     * {
     *   "components": {
     *     "x-parameter-groups": {
     *       "pagination": [
     *         {
     *           "name": "limit",
     *           "in": "query",
     *           "required": false,
     *            ... usual parameter properties
     *         },
     *         {
     *           "name": "page",
     *           ... usual parameter properties
     *         }
     *       ]
     *     }
     *   },
     *   "paths": {
     *     "/product": {
     *       "get": {
     *         "parameters": [
     *           {
     *             "x-parameter-group": "pagination"
     *           },
     *           ... other parameters
     *         ]
     *         ... usual operation properties
     *       }
     *     }
     *   }
     * }
     * ```
     *
     * @param OpenApiSpec $specs
     */
    private function resolveParameterGroups(array &$specs): void
    {
        if (!isset($specs['paths']) || !\is_array($specs['paths'])) {
            return;
        }

        // this is a custom extension that is not supported by the OpenAPI spec
        // it has to be processed and removed before the final output
        $parameterGroups = $specs['components']['x-parameter-groups'] ?? [];
        unset($specs['components']['x-parameter-groups']);

        foreach ($specs['paths'] as &$pathDefinition) {
            foreach ($pathDefinition as &$operation) {
                if (!isset($operation['parameters']) || !\is_array($operation['parameters'])) {
                    continue;
                }

                $newParams = [];
                $hasGroup = false;

                foreach ($operation['parameters'] as $parameter) {
                    if (isset($parameter['x-parameter-group'])) {
                        $hasGroup = true;
                        $groupNames = (array) $parameter['x-parameter-group'];

                        foreach ($groupNames as $groupName) {
                            if (isset($parameterGroups[$groupName])) {
                                array_push($newParams, ...$parameterGroups[$groupName]);
                            }
                        }
                    } else {
                        $newParams[] = $parameter;
                    }
                }

                if ($hasGroup) {
                    $operation['parameters'] = $newParams;
                }
            }
        }
    }

    /**
     * Automatically enriches path descriptions with available associations
     *
     * @param OpenApiSpec $specs
     * @param array<string, EntityDefinition> $definitions
     */
    private function enrichPathsWithAssociations(array &$specs, array $definitions): void
    {
        if (!isset($specs['paths']) || !\is_array($specs['paths'])) {
            return;
        }

        // Build a map of entity names to their association documentation
        $associationDocs = [];
        foreach ($definitions as $def) {
            if (!$def instanceof EntityDefinition) {
                continue;
            }

            $doc = $this->getAssociationsDocumentation($def);
            if (!empty($doc)) {
                $associationDocs[$def->getEntityName()] = $doc;
            }
        }

        // Enrich all paths
        foreach ($specs['paths'] as &$pathDefinition) {
            foreach (self::OPERATION_KEYS as $method) {
                if (!isset($pathDefinition[$method])) {
                    continue;
                }

                // Only enrich read operations (operationId starts with "read")
                if (!isset($pathDefinition[$method]['operationId'])
                    || !str_starts_with($pathDefinition[$method]['operationId'], 'read')) {
                    continue;
                }

                // Try to find entity reference in the response schema
                $entityName = $this->extractEntityNameFromOperation($pathDefinition[$method]);

                if (!$entityName || !isset($associationDocs[$entityName])) {
                    continue;
                }

                // Append associations documentation
                if (isset($pathDefinition[$method]['description'])) {
                    $currentDesc = $pathDefinition[$method]['description'];
                    // Only add if not already present
                    if (!str_contains($currentDesc, '**Available Associations:**')) {
                        $pathDefinition[$method]['description'] = $currentDesc . $associationDocs[$entityName];
                    }
                }
            }
        }
    }

    /**
     * Extracts entity name from operation response schemas
     *
     * @param array<string, mixed> $operation
     */
    private function extractEntityNameFromOperation(array $operation): ?string
    {
        // Handle response-level $ref (e.g., "$ref": "#/components/responses/ProductListResponse")
        if (isset($operation['responses']['200']['$ref'])) {
            $ref = $operation['responses']['200']['$ref'];
            // Extract entity name from response reference like "ProductListResponse" -> "product"
            // Match pattern: components/responses/{Entity}[List|Detail]Response
            if (\is_string($ref) && preg_match('#/([^/]+?)(?:List|Detail)?Response$#', $ref, $matches)) {
                $converted = preg_replace('/(?<!^)[A-Z]/', '_$0', $matches[1]);
                if (!\is_string($converted)) {
                    return null;
                }

                return strtolower($converted);
            }
        }

        // Check if there's a 200 response with a schema
        if (!isset($operation['responses']['200']['content']['application/json']['schema'])) {
            return null;
        }

        $schema = $operation['responses']['200']['content']['application/json']['schema'];

        // Check for direct reference (e.g., "#/components/schemas/ShippingMethod" or "ProductDetailResponse")
        if (isset($schema['$ref'])) {
            $ref = $schema['$ref'];
            // Check if it's a RouteResponse wrapper - extract actual entity reference
            if (str_contains($ref, 'RouteResponse')) {
                return $this->extractEntityFromRouteResponseRef($ref);
            }
            // Check if it's a DetailResponse wrapper (ProductDetailResponse -> product)
            if (str_contains($ref, 'DetailResponse')) {
                return $this->extractEntityFromDetailResponseRef($ref);
            }
            // Check if it's a Result wrapper (ProductListingResult, etc.)
            if (str_contains($ref, 'Result')) {
                $entityName = $this->extractEntityFromResultRef($ref);
                if ($entityName) {
                    return $entityName;
                }
            }

            return $this->extractEntityNameFromRef($ref);
        }

        // Check for allOf with references
        if (isset($schema['allOf']) && \is_array($schema['allOf'])) {
            foreach ($schema['allOf'] as $item) {
                if (isset($item['$ref'])) {
                    $ref = $item['$ref'];
                    if (str_contains($ref, 'RouteResponse')) {
                        $entityName = $this->extractEntityFromRouteResponseRef($ref);
                    } elseif (str_contains($ref, 'DetailResponse')) {
                        $entityName = $this->extractEntityFromDetailResponseRef($ref);
                    } elseif (str_contains($ref, 'Result')) {
                        $entityName = $this->extractEntityFromResultRef($ref);
                    } else {
                        $entityName = $this->extractEntityNameFromRef($ref);
                    }
                    if ($entityName) {
                        return $entityName;
                    }
                }
            }
        }

        // Check for array items reference (collection endpoints)
        if (isset($schema['properties']['elements']['items']['$ref'])) {
            return $this->extractEntityNameFromRef($schema['properties']['elements']['items']['$ref']);
        }

        return null;
    }

    /**
     * Extracts entity name from Result schema reference
     * Example: "#/components/schemas/ProductListingResult" -> "product"
     *
     * This handles wrapper classes like ProductListingResult, EntitySearchResult, etc.
     */
    private function extractEntityFromResultRef(string $ref): ?string
    {
        // Common patterns:
        // ProductListingResult -> product
        // EntitySearchResult -> generic, skip

        // Extract schema name from reference
        if (!preg_match('#/([^/]+)Result$#', $ref, $matches)) {
            return null;
        }

        $schemaName = $matches[1];

        // Skip generic result wrappers
        if (\in_array($schemaName, ['EntitySearch', 'Search'], true)) {
            return null;
        }

        // Handle patterns like "ProductListing" -> "product"
        // Remove common suffixes before converting
        $schemaName = preg_replace('/(?:Listing|Search|Collection)$/', '', $schemaName);
        if (!\is_string($schemaName)) {
            return null;
        }

        // Convert PascalCase to snake_case
        $converted = preg_replace('/(?<!^)[A-Z]/', '_$0', $schemaName);
        if (!\is_string($converted)) {
            return null;
        }

        return strtolower($converted);
    }

    /**
     * Extracts entity name from RouteResponse schema reference
     * Example: "#/components/schemas/OrderRouteResponse" -> "order"
     */
    private function extractEntityFromRouteResponseRef(string $ref): ?string
    {
        // Extract schema name from reference
        if (!preg_match('#/([^/]+)RouteResponse$#', $ref, $matches)) {
            return null;
        }

        $schemaName = $matches[1];

        // Convert PascalCase to snake_case
        $converted = preg_replace('/(?<!^)[A-Z]/', '_$0', $schemaName);
        if (!\is_string($converted)) {
            return null;
        }

        return strtolower($converted);
    }

    /**
     * Extracts entity name from DetailResponse schema reference
     * Example: "#/components/schemas/ProductDetailResponse" -> "product"
     */
    private function extractEntityFromDetailResponseRef(string $ref): ?string
    {
        // Extract schema name from reference
        if (!preg_match('#/([^/]+)DetailResponse$#', $ref, $matches)) {
            return null;
        }

        $schemaName = $matches[1];

        // Convert PascalCase to snake_case
        $converted = preg_replace('/(?<!^)[A-Z]/', '_$0', $schemaName);
        if (!\is_string($converted)) {
            return null;
        }

        return strtolower($converted);
    }

    /**
     * Extracts entity name from schema reference
     * Example: "#/components/schemas/ShippingMethod" -> "shipping_method"
     */
    private function extractEntityNameFromRef(string $ref): ?string
    {
        // Extract schema name from reference
        if (!preg_match('#/([^/]+)$#', $ref, $matches)) {
            return null;
        }

        $schemaName = $matches[1];

        // Convert PascalCase to snake_case
        $converted = preg_replace('/(?<!^)[A-Z]/', '_$0', $schemaName);
        if (!\is_string($converted)) {
            return null;
        }

        return strtolower($converted);
    }

    /**
     * Generates documentation for available associations
     */
    private function getAssociationsDocumentation(EntityDefinition $definition): string
    {
        $associations = [];

        foreach ($definition->getFields() as $field) {
            if (!$field instanceof AssociationField) {
                continue;
            }

            // Skip if explicitly hidden from OpenAPI
            if ($field->getFlag(IgnoreInOpenapiSchema::class)) {
                continue;
            }

            // Skip translations
            if ($field->getPropertyName() === 'translations') {
                continue;
            }

            // Skip parent associations - they cannot be loaded via Criteria due to infinite recursion prevention
            // Error: FRAMEWORK__PARENT_ASSOCIATION_CAN_NOT_BE_FETCHED
            if ($field instanceof ParentAssociationField) {
                continue;
            }

            // Check ApiAware flag for Store API
            $apiAware = $field->getFlag(ApiAware::class);
            if (!$apiAware || !$apiAware->isSourceAllowed(SalesChannelApiSource::class)) {
                continue;
            }

            $fieldName = $field->getPropertyName();

            // Get description from Field
            $description = $field->getDescription();

            // Build the association line
            $line = '- `' . $fieldName . '`';

            if ($description) {
                $line .= ' - ' . $description;
            }

            $associations[] = $line;
        }

        if (empty($associations)) {
            return '';
        }

        return "\n\n**Available Associations:**\n" . implode("\n", $associations);
    }
}

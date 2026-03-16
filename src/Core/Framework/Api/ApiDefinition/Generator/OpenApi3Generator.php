<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use OpenApi\Annotations\OpenApi;
use Shopware\Core\Framework\Api\ApiDefinition\ApiDefinitionGeneratorInterface;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiPathBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;

/**
 * @internal
 *
 * @phpstan-import-type OpenApiSpec from DefinitionService
 */
#[Package('framework')]
class OpenApi3Generator implements ApiDefinitionGeneratorInterface
{
    final public const FORMAT = 'openapi-3';

    private readonly string $schemaPath;

    /**
     * @param array{Framework: array{path: string}} $bundles
     */
    public function __construct(
        private readonly OpenApiSchemaBuilder $openApiBuilder,
        private readonly OpenApiPathBuilder $pathBuilder,
        private readonly OpenApiDefinitionSchemaBuilder $definitionSchemaBuilder,
        array $bundles,
        private readonly BundleSchemaPathCollection $bundleSchemaPathCollection
    ) {
        $this->schemaPath = $bundles['Framework']['path'] . '/Api/ApiDefinition/Generator/Schema/AdminApi';
    }

    public function supports(string $format, string $api): bool
    {
        return $format === self::FORMAT;
    }

    /**
     * @param array<string, EntityDefinition>|array<string, EntityDefinition&SalesChannelDefinitionInterface> $definitions
     *
     * @return OpenApiSpec
     */
    public function generate(array $definitions, string $api, string $apiType = DefinitionService::TYPE_JSON_API, ?string $bundleName = null): array
    {
        $forSalesChannel = $this->containsSalesChannelDefinition($definitions);

        $openApi = new OpenApi([
            'openapi' => '3.1.0',
        ]);
        $this->openApiBuilder->enrich($openApi, $api);

        ksort($definitions);

        foreach ($definitions as $definition) {
            if (!$this->shouldDefinitionBeIncluded($definition)) {
                continue;
            }

            $onlyFlat = match ($apiType) {
                DefinitionService::TYPE_JSON => true,
                default => $this->shouldIncludeReferenceOnly($definition, $forSalesChannel),
            };

            $schema = $this->definitionSchemaBuilder->getSchemaByDefinition(
                $definition,
                $this->getResourceUri($definition),
                $forSalesChannel,
                $onlyFlat,
                $apiType
            );

            // Retrieve POST schema metadata from the schema builder
            $postSchemaRef = $this->definitionSchemaBuilder->getLastPostSchemaRef();
            $postRequiredFields = $this->definitionSchemaBuilder->getLastPostRequiredFields();

            $openApi->components->merge($schema);

            if ($onlyFlat) {
                continue;
            }

            if ($apiType === DefinitionService::TYPE_JSON_API) {
                $openApi->merge($this->pathBuilder->getPathActions(
                    $definition,
                    $this->getResourceUri($definition),
                    $postSchemaRef,
                    $postRequiredFields
                ));
                $openApi->merge([$this->pathBuilder->getTag($definition)]);
            }
        }

        $data = json_decode($openApi->toJson(), true, 512, \JSON_THROW_ON_ERROR);
        $data['paths'] ??= [];

        $schemaPaths = [$this->schemaPath];

        if ($bundleName !== null && $bundleName !== '') {
            $schemaPaths = array_merge([$this->schemaPath . '/components', $this->schemaPath . '/tags'], $this->bundleSchemaPathCollection->getSchemaPaths($api, $bundleName));
            $data['paths'] = [];
        } else {
            $schemaPaths = array_merge($schemaPaths, $this->bundleSchemaPathCollection->getSchemaPaths($api, $bundleName));
        }

        $loader = new OpenApiFileLoader($schemaPaths);

        /** @var OpenApiSpec $finalSpecs */
        $finalSpecs = array_replace_recursive($data, $loader->loadOpenapiSpecification());

        // Remove unused schema components to reduce spec size
        // Only for JSON_API type where we have paths that reference schemas
        if ($apiType === DefinitionService::TYPE_JSON_API && empty($bundleName)) {
            $finalSpecs = $this->removeUnreferencedSchemas($finalSpecs);
        }

        return $finalSpecs;
    }

    /**
     * @param array<string, EntityDefinition>|list<EntityDefinition&SalesChannelDefinitionInterface> $definitions
     *
     * @return array<string, array{name: string, translatable: array<int|string, mixed>, properties: array<string, mixed>}>
     */
    public function getSchema(array $definitions): array
    {
        $schemaDefinitions = [];

        $forSalesChannel = $this->containsSalesChannelDefinition($definitions);

        ksort($definitions);

        foreach ($definitions as $definition) {
            if (!$definition instanceof EntityDefinition) {
                continue;
            }

            if (str_ends_with($definition->getEntityName(), '_translation')) {
                continue;
            }

            try {
                $definition->getEntityName();
            } catch (\Exception) {
                // mapping tables has no repository, skip them
                continue;
            }

            $schemas = $this->definitionSchemaBuilder->getSchemaByDefinition($definition, $this->getResourceUri($definition), $forSalesChannel);
            $schemaName = $this->snakeCaseToCamelCase($definition->getEntityName());

            // Prefer JsonApi schema for relationship extraction (has JSON:API relationships wrapper),
            // fall back to Read schema for entities without JsonApi (e.g. store-api flat schemas)
            $jsonApiSchema = $schemas[$schemaName . 'JsonApi'] ?? null;
            $readSchema = $schemas[$schemaName] ?? null;

            $sourceSchema = $jsonApiSchema ?? $readSchema;
            if ($sourceSchema === null) {
                throw ApiException::invalidSchemaStructure($definition->getEntityName());
            }
            $sourceSchemaData = json_decode($sourceSchema->toJson(), true, 512, \JSON_THROW_ON_ERROR);

            // For JsonApi schema: extract from allOf[1].properties (matches trunk behavior)
            // For Read/flat schema: extract from allOf composition or direct properties
            if ($jsonApiSchema !== null && isset($sourceSchemaData['allOf'][1]['properties'])) {
                $schema = $sourceSchemaData['allOf'][1]['properties'];
            } else {
                $schema = $this->extractPropertiesFromSchemaData($sourceSchemaData, $schemas);
            }

            $relationships = [];
            if (\array_key_exists('relationships', $schema)) {
                foreach ($schema['relationships']['properties'] as $propertyName => $extension) {
                    $relationshipData = $extension['properties']['data'];
                    $type = $relationshipData['type'];

                    if ($type === 'object') {
                        $entity = $relationshipData['properties']['type']['example'];
                    } elseif ($type === 'array') {
                        $entity = $relationshipData['items']['properties']['type']['example'];
                    } else {
                        throw ApiException::invalidSchemaStructure($definition->getEntityName());
                    }

                    $relationships[$propertyName] = [
                        'type' => $type,
                        'entity' => $entity,
                    ];
                }
            }

            $properties = array_merge(
                [
                    'id' => [
                        'type' => 'string',
                        'pattern' => '^[0-9a-f]{32}$',
                    ],
                ],
                $schema,
                $relationships
            );

            if (\array_key_exists('extensions', $properties)) {
                $extensions = [];

                foreach ($properties['extensions']['properties'] as $propertyName => $extension) {
                    $field = $definition->getFields()->get($propertyName);

                    if (!$field instanceof AssociationField) {
                        $extensions[$propertyName] = $extension;

                        continue;
                    }

                    $data = $extension['properties']['data'];
                    $type = $data['type'];

                    if ($type === 'object') {
                        $entity = $data['properties']['type']['example'];
                    } elseif ($type === 'array') {
                        $entity = $data['items']['properties']['type']['example'];
                    } else {
                        throw ApiException::invalidSchemaStructure($definition->getEntityName());
                    }

                    $extensions[$propertyName] = ['type' => $type, 'entity' => $entity];
                }

                $properties['extensions']['properties'] = $extensions;
            }

            $entityName = $definition->getEntityName();
            $schemaDefinitions[$entityName] = [
                'name' => $entityName,
                'translatable' => $definition->getFields()->filterInstance(TranslatedField::class)->getKeys(),
                'properties' => $properties,
            ];
        }

        return $schemaDefinitions;
    }

    private function getResourceUri(EntityDefinition $definition): string
    {
        return '/' . str_replace('_', '-', $definition->getEntityName());
    }

    /**
     * @param array<string, EntityDefinition>|list<EntityDefinition&SalesChannelDefinitionInterface> $definitions
     */
    private function containsSalesChannelDefinition(array $definitions): bool
    {
        foreach ($definitions as $definition) {
            if (is_subclass_of($definition, SalesChannelDefinitionInterface::class)) {
                return true;
            }
        }

        return false;
    }

    private function shouldDefinitionBeIncluded(EntityDefinition $definition): bool
    {
        if (str_ends_with($definition->getEntityName(), '_translation')) {
            return false;
        }

        if (str_starts_with($definition->getEntityName(), 'version')) {
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

    private function snakeCaseToCamelCase(string $input): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $input)));
    }

    /**
     * Extracts properties from schema data, handling both allOf composition and direct properties
     *
     * @param array<string, mixed> $schemaData
     * @param array<string, \OpenApi\Annotations\Schema> $allSchemas
     *
     * @return array<string, mixed>
     */
    private function extractPropertiesFromSchemaData(array $schemaData, array $allSchemas): array
    {
        // Direct properties (legacy/store-api structure)
        if (isset($schemaData['properties'])) {
            return $schemaData['properties'];
        }

        // allOf composition (new structure)
        if (!isset($schemaData['allOf'])) {
            return [];
        }

        $properties = [];

        foreach ($schemaData['allOf'] as $item) {
            // Inline properties
            if (isset($item['properties'])) {
                $properties = array_merge($properties, $item['properties']);
            }

            // Reference to another schema
            if (isset($item['$ref'])) {
                $refName = str_replace('#/components/schemas/', '', $item['$ref']);
                if (isset($allSchemas[$refName])) {
                    $refSchemaData = json_decode($allSchemas[$refName]->toJson(), true, 512, \JSON_THROW_ON_ERROR);
                    $refProperties = $this->extractPropertiesFromSchemaData($refSchemaData, $allSchemas);
                    $properties = array_merge($properties, $refProperties);
                }
            }
        }

        return $properties;
    }

    /**
     * Removes schema components that are not referenced anywhere in the spec.
     * This reduces the size of the OpenAPI specification by removing orphaned schemas
     * like mapping entities that are never directly referenced in paths or other schemas.
     *
     * @param OpenApiSpec $spec
     *
     * @return OpenApiSpec
     */
    private function removeUnreferencedSchemas(array $spec): array
    {
        if (!isset($spec['components']['schemas'])) {
            return $spec;
        }

        $schemas = $spec['components']['schemas'];
        $referencedSchemas = $this->collectReferencedSchemas($spec, $schemas);

        // Filter schemas to only keep referenced ones
        $spec['components']['schemas'] = array_filter(
            $schemas,
            static fn (mixed $schema, string $name): bool => isset($referencedSchemas[$name]),
            \ARRAY_FILTER_USE_BOTH
        );

        return $spec;
    }

    /**
     * Collects all schema names that are referenced in the spec.
     * Recursively resolves references to include transitive dependencies.
     *
     * @param array<string, mixed> $spec
     * @param array<string, mixed> $allSchemas
     *
     * @return array<string, true>
     */
    private function collectReferencedSchemas(array $spec, array $allSchemas): array
    {
        $referenced = [];

        // Collect direct references from paths and other parts (excluding components.schemas itself)
        $specWithoutSchemas = $spec;
        unset($specWithoutSchemas['components']['schemas']);
        $this->findRefsRecursive($specWithoutSchemas, $referenced);

        // Resolve transitive references from referenced schemas
        $queue = array_keys($referenced);
        while ($queue !== []) {
            $schemaName = array_shift($queue);
            if (!isset($allSchemas[$schemaName])) {
                continue;
            }

            $schemaRefs = [];
            $this->findRefsRecursive($allSchemas[$schemaName], $schemaRefs);

            foreach ($schemaRefs as $refName => $true) {
                if (!isset($referenced[$refName])) {
                    $referenced[$refName] = true;
                    $queue[] = $refName;
                }
            }
        }

        return $referenced;
    }

    /**
     * Recursively finds all $ref references in a data structure.
     *
     * @param array<string, true> $refs
     */
    private function findRefsRecursive(mixed $data, array &$refs): void
    {
        if (!\is_array($data)) {
            return;
        }

        if (isset($data['$ref']) && \is_string($data['$ref'])) {
            $refName = str_replace('#/components/schemas/', '', $data['$ref']);
            if (!str_starts_with($data['$ref'], '#/components/schemas/')) {
                return;
            }
            $refs[$refName] = true;
        }

        foreach ($data as $value) {
            $this->findRefsRecursive($value, $refs);
        }
    }
}

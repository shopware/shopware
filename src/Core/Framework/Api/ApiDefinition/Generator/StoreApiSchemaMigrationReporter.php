<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use OpenApi\Annotations\Components;
use OpenApi\Annotations\OpenApi;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;

/**
 * @internal
 */
#[Package('framework')]
class StoreApiSchemaMigrationReporter
{
    /**
     * @internal
     *
     * @param iterable<StoreApiSchemaMigrationScopeProviderInterface> $scopeProviders
     */
    public function __construct(
        private readonly OpenApiDefinitionSchemaBuilder $definitionSchemaBuilder,
        private readonly iterable $scopeProviders,
    ) {
    }

    /**
     * @param array<string, EntityDefinition> $definitions
     */
    public function report(array $definitions, string $scope = CoreStoreApiSchemaMigrationScopeProvider::SCOPE): StoreApiSchemaMigrationReport
    {
        $scopeProvider = $this->getScopeProvider($scope);
        $jsonSpec = $this->loadOpenApiSpecification($scopeProvider->getSchemaPaths());
        $jsonSchemaNames = $this->getJsonSchemaNames($jsonSpec);
        $generatedSchemas = $this->getGeneratedSchemas($definitions, $scopeProvider, $jsonSchemaNames);
        $referencedJsonSchemaNames = $this->getReferencedJsonSchemaNames($jsonSpec, $generatedSchemas['componentSchemas']);
        $phpGeneratedSchemaNames = $this->getPhpGeneratedSchemaNames($generatedSchemas['definitionSchemas'], $jsonSchemaNames, $referencedJsonSchemaNames);

        $jsonOverridesPhpGenerated = array_intersect($jsonSchemaNames, $phpGeneratedSchemaNames);
        $phpGeneratedOnly = array_diff($phpGeneratedSchemaNames, $jsonSchemaNames);

        return new StoreApiSchemaMigrationReport(
            jsonOverridesPhpGenerated: $this->sortList($jsonOverridesPhpGenerated),
            phpGeneratedOnly: $this->sortList($phpGeneratedOnly),
            jsonWithoutPhpGenerated: $this->sortList(array_diff($jsonSchemaNames, $phpGeneratedSchemaNames)),
        );
    }

    /**
     * @return list<string>
     */
    public function getSupportedScopes(): array
    {
        $scopes = [];
        foreach ($this->scopeProviders as $scopeProvider) {
            $scopes[] = $scopeProvider->getScope();
        }

        return array_values(array_unique($scopes));
    }

    /**
     * @param array<string, array<string, mixed>> $definitionSchemas
     * @param list<string> $jsonSchemaNames
     * @param list<string> $referencedJsonSchemaNames
     *
     * @return list<string>
     */
    private function getPhpGeneratedSchemaNames(
        array $definitionSchemas,
        array $jsonSchemaNames,
        array $referencedJsonSchemaNames
    ): array {
        $schemaNames = [];

        foreach ($definitionSchemas as $schemaName => $schema) {
            if (\in_array($schemaName, $jsonSchemaNames, true)) {
                continue;
            }

            if (!array_intersect(array_keys($schema), $referencedJsonSchemaNames)) {
                continue;
            }

            array_push($schemaNames, ...array_keys($schema));
        }

        return $this->sortList($schemaNames);
    }

    /**
     * @param array<string, EntityDefinition> $definitions
     * @param list<string> $jsonSchemaNames
     *
     * @return array{
     *     definitionSchemas: array<string, array<string, mixed>>,
     *     componentSchemas: array<string, mixed>
     * }
     */
    private function getGeneratedSchemas(
        array $definitions,
        StoreApiSchemaMigrationScopeProviderInterface $scopeProvider,
        array $jsonSchemaNames
    ): array {
        $definitionSchemas = [];

        ksort($definitions);

        foreach ($definitions as $definition) {
            if (!$this->shouldDefinitionBeIncluded($definition, $scopeProvider)) {
                continue;
            }

            $schemaName = $this->definitionSchemaBuilder->getSchemaName($definition);

            if (\in_array($schemaName, $jsonSchemaNames, true)) {
                $definitionSchemas[$schemaName] = $this->definitionSchemaBuilder->getExtensionSchemaByDefinition(
                    $definition,
                    $this->getResourceUri($definition),
                    true,
                );

                continue;
            }

            $definitionSchemas[$schemaName] = $this->definitionSchemaBuilder->getSchemaByDefinition(
                $definition,
                $this->getResourceUri($definition),
                true,
                $this->shouldIncludeReferenceOnly($definition),
                DefinitionService::TYPE_JSON_API,
            );
        }

        $openApi = new OpenApi([
            'openapi' => '3.2.0',
        ]);
        $openApi->components = new Components([]);

        foreach ($definitionSchemas as $schema) {
            $openApi->components->merge(array_values($schema));
        }

        $data = json_decode($openApi->toJson(), true, 512, \JSON_THROW_ON_ERROR);
        $componentSchemas = $data['components']['schemas'] ?? [];
        if (!\is_array($componentSchemas)) {
            $componentSchemas = [];
        }

        return [
            'definitionSchemas' => $definitionSchemas,
            'componentSchemas' => $componentSchemas,
        ];
    }

    /**
     * @param array<string, mixed> $jsonSpec
     * @param array<string, mixed> $generatedComponentSchemas
     *
     * @return list<string>
     */
    private function getReferencedJsonSchemaNames(array $jsonSpec, array $generatedComponentSchemas): array
    {
        $componentSchemas = $jsonSpec['components']['schemas'] ?? [];
        if (!\is_array($componentSchemas)) {
            $componentSchemas = [];
        }
        $componentSchemas = array_replace_recursive($componentSchemas, $generatedComponentSchemas);

        $referencedSchemaNames = [];
        $queue = [];
        $this->collectSchemaReferences($jsonSpec['paths'] ?? [], $queue);

        while ($queue !== []) {
            $schemaName = array_shift($queue);
            if (isset($referencedSchemaNames[$schemaName]) || !\is_string($schemaName)) {
                continue;
            }

            $referencedSchemaNames[$schemaName] = true;

            if (isset($componentSchemas[$schemaName])) {
                $this->collectSchemaReferences($componentSchemas[$schemaName], $queue);
            }
        }

        return $this->sortList(array_keys($referencedSchemaNames));
    }

    /**
     * @param list<string> $schemaNames
     */
    private function collectSchemaReferences(mixed $value, array &$schemaNames): void
    {
        if (!\is_array($value)) {
            return;
        }

        if (isset($value['$ref']) && \is_string($value['$ref']) && str_starts_with($value['$ref'], '#/components/schemas/')) {
            $schemaNames[] = mb_substr($value['$ref'], 21);
        }

        foreach ($value as $nestedValue) {
            $this->collectSchemaReferences($nestedValue, $schemaNames);
        }
    }

    /**
     * @param array<string, mixed> $jsonSpec
     *
     * @return list<string>
     */
    private function getJsonSchemaNames(array $jsonSpec): array
    {
        if (!isset($jsonSpec['components']['schemas'])) {
            return [];
        }

        $schemas = $jsonSpec['components']['schemas'];
        if (!\is_array($schemas)) {
            return [];
        }

        $schemaNames = [];
        foreach (array_keys($schemas) as $schemaName) {
            if (\is_string($schemaName)) {
                $schemaNames[] = $schemaName;
            }
        }

        return $this->sortList($schemaNames);
    }

    /**
     * @param list<string> $schemaPaths
     *
     * @return array<string, mixed>
     */
    private function loadOpenApiSpecification(array $schemaPaths): array
    {
        $loader = new OpenApiFileLoader($schemaPaths);

        return $loader->loadOpenapiSpecification();
    }

    private function shouldDefinitionBeIncluded(EntityDefinition $definition, StoreApiSchemaMigrationScopeProviderInterface $scopeProvider): bool
    {
        if (preg_match('/_translation$/', $definition->getEntityName())) {
            return false;
        }

        if (mb_strpos($definition->getEntityName(), 'version') === 0) {
            return false;
        }

        if ($scopeProvider->includesAllDefinitions()) {
            return true;
        }

        foreach ($scopeProvider->getDefinitionClassPrefixes() as $namespace) {
            if (str_starts_with($definition::class, $namespace)) {
                return true;
            }
        }

        return false;
    }

    private function getScopeProvider(string $scope): StoreApiSchemaMigrationScopeProviderInterface
    {
        foreach ($this->scopeProviders as $scopeProvider) {
            if ($scopeProvider->getScope() === $scope) {
                return $scopeProvider;
            }
        }

        throw ApiException::unsupportedStoreApiSchemaMigrationScope($scope, $this->getSupportedScopes());
    }

    private function shouldIncludeReferenceOnly(EntityDefinition $definition): bool
    {
        return $definition instanceof MappingEntityDefinition
            || !$definition instanceof SalesChannelDefinitionInterface;
    }

    private function getResourceUri(EntityDefinition $definition, string $rootPath = '/'): string
    {
        return ltrim('/', $rootPath) . '/' . str_replace('_', '-', $definition->getEntityName());
    }

    /**
     * @param array<array-key, string> $schemaNames
     *
     * @return list<string>
     */
    private function sortList(array $schemaNames): array
    {
        $schemaNames = array_values(array_unique($schemaNames));
        sort($schemaNames);

        return $schemaNames;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 *
 * @phpstan-type StoreApiSchemaMigrationAllowlist array{
 *     phpGeneratedStoreApiSchemas: list<string>
 * }
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
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * @param array<string, EntityDefinition> $definitions
     */
    public function report(array $definitions, string $scope = CoreStoreApiSchemaMigrationScopeProvider::SCOPE): StoreApiSchemaMigrationReport
    {
        $scopeProvider = $this->getScopeProvider($scope);
        $jsonSchemaNames = $this->getJsonSchemaNames($scopeProvider->getSchemaPaths());
        $referencedJsonSchemaNames = $this->getReferencedJsonSchemaNames($scopeProvider->getSchemaPaths());
        $phpGeneratedSchemaNames = $this->getPhpGeneratedSchemaNames($definitions, $scopeProvider, $jsonSchemaNames, $referencedJsonSchemaNames);
        $allowlist = $this->loadAllowlist($scopeProvider->getAllowlistPath());

        $jsonOverridesPhpGenerated = array_intersect($jsonSchemaNames, $phpGeneratedSchemaNames);
        $phpGeneratedOnly = array_diff($phpGeneratedSchemaNames, $jsonSchemaNames);

        return new StoreApiSchemaMigrationReport(
            jsonOverridesPhpGenerated: $this->sortList($jsonOverridesPhpGenerated),
            phpGeneratedOnly: $this->sortList($phpGeneratedOnly),
            phpGeneratedOnlyAllowed: $this->sortList(array_intersect($phpGeneratedOnly, $allowlist['phpGeneratedStoreApiSchemas'])),
            phpGeneratedOnlyWithoutAllowlist: $this->sortList(array_diff($phpGeneratedOnly, $allowlist['phpGeneratedStoreApiSchemas'])),
            jsonWithoutPhpGenerated: $this->sortList(array_diff($jsonSchemaNames, $phpGeneratedSchemaNames)),
            allowlistWithoutPhpGeneratedOnlySchema: $this->sortList(array_diff($allowlist['phpGeneratedStoreApiSchemas'], $phpGeneratedOnly)),
            allowlistWithoutPhpGeneratedSchema: $this->sortList(array_diff($allowlist['phpGeneratedStoreApiSchemas'], $phpGeneratedSchemaNames)),
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
     * @param array<string, EntityDefinition> $definitions
     * @param list<string> $jsonSchemaNames
     * @param list<string> $referencedJsonSchemaNames
     *
     * @return list<string>
     */
    private function getPhpGeneratedSchemaNames(
        array $definitions,
        StoreApiSchemaMigrationScopeProviderInterface $scopeProvider,
        array $jsonSchemaNames,
        array $referencedJsonSchemaNames
    ): array {
        $schemaNames = [];

        ksort($definitions);

        foreach ($definitions as $definition) {
            if (!$this->shouldDefinitionBeIncluded($definition, $scopeProvider)) {
                continue;
            }

            $schemaName = $this->definitionSchemaBuilder->getSchemaName($definition);

            if (\in_array($schemaName, $jsonSchemaNames, true)) {
                continue;
            }

            if (!\in_array($schemaName, $referencedJsonSchemaNames, true)) {
                continue;
            }

            $schema = $this->definitionSchemaBuilder->getSchemaByDefinition(
                $definition,
                $this->getResourceUri($definition),
                true,
                $this->shouldIncludeReferenceOnly($definition),
                DefinitionService::TYPE_JSON_API,
            );

            array_push($schemaNames, ...array_keys($schema));
        }

        return $this->sortList($schemaNames);
    }

    /**
     * @param list<string> $schemaPaths
     *
     * @return list<string>
     */
    private function getReferencedJsonSchemaNames(array $schemaPaths): array
    {
        $loader = new OpenApiFileLoader($schemaPaths);
        $jsonSpec = $loader->loadOpenapiSpecification();

        $componentSchemas = $jsonSpec['components']['schemas'] ?? [];
        if (!\is_array($componentSchemas)) {
            $componentSchemas = [];
        }

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
     * @param list<string> $schemaPaths
     *
     * @return list<string>
     */
    private function getJsonSchemaNames(array $schemaPaths): array
    {
        $loader = new OpenApiFileLoader($schemaPaths);
        $jsonSpec = $loader->loadOpenapiSpecification();

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
     * @return StoreApiSchemaMigrationAllowlist
     */
    private function loadAllowlist(string $allowlistPath): array
    {
        if (!$this->filesystem->exists($allowlistPath)) {
            return [
                'phpGeneratedStoreApiSchemas' => [],
            ];
        }

        try {
            $contents = $this->filesystem->readFile($allowlistPath);
        } catch (IOExceptionInterface) {
            throw ApiException::schemaDefinitionNotReadable($allowlistPath);
        }

        try {
            $data = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ApiException::invalidStoreApiSchemaMigrationAllowlist($allowlistPath, 'JSON could not be decoded.', $exception);
        }

        if (!\is_array($data)) {
            throw ApiException::invalidStoreApiSchemaMigrationAllowlist($allowlistPath, 'The root value must be an object.');
        }

        return [
            'phpGeneratedStoreApiSchemas' => $this->readAllowlistSchemaNames($data, 'phpGeneratedStoreApiSchemas', $allowlistPath),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private function readAllowlistSchemaNames(array $data, string $key, string $allowlistPath): array
    {
        if (!isset($data[$key]) || !\is_array($data[$key])) {
            throw ApiException::invalidStoreApiSchemaMigrationAllowlist($allowlistPath, \sprintf('The "%s" list is missing.', $key));
        }

        $schemaNames = [];
        foreach ($data[$key] as $schemaName) {
            if (!\is_string($schemaName)) {
                throw ApiException::invalidStoreApiSchemaMigrationAllowlist($allowlistPath, \sprintf('The "%s" list must contain only schema names.', $key));
            }

            $schemaNames[] = $schemaName;
        }

        return $this->sortList($schemaNames);
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

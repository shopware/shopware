<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;

/**
 * @internal
 *
 * @phpstan-import-type OpenApiSpec from DefinitionService
 *
 * @phpstan-type StoreApiSchemaMigrationReport array{
 *     jsonOverridesPhpGenerated: list<string>,
 *     jsonOverridesPhpGeneratedAllowed: list<string>,
 *     jsonOverridesPhpGeneratedWithoutAllowlist: list<string>,
 *     phpGeneratedOnly: list<string>,
 *     phpGeneratedOnlyAllowed: list<string>,
 *     phpGeneratedOnlyWithoutAllowlist: list<string>,
 *     jsonWithoutPhpGenerated: list<string>,
 *     allowlistWithoutJsonOverridesPhpGeneratedSchema: list<string>,
 *     allowlistWithoutPhpGeneratedOnlySchema: list<string>,
 *     allowlistWithoutPhpGeneratedSchema: list<string>
 * }
 * @phpstan-type StoreApiSchemaMigrationAllowlist array{
 *     jsonOverridesPhpGeneratedSchemas: list<string>,
 *     phpGeneratedStoreApiSchemas: list<string>
 * }
 */
#[Package('framework')]
class StoreApiSchemaMigrationReporter
{
    public const SCOPE_CORE = 'core';

    public const SCOPE_ALL = 'all';

    private readonly string $schemaPath;

    private readonly string $frameworkPath;

    private readonly string $corePath;

    private readonly string $allowlistPath;

    /**
     * @internal
     *
     * @param array{Framework: array{path: string}} $bundles
     */
    public function __construct(
        private readonly OpenApiDefinitionSchemaBuilder $definitionSchemaBuilder,
        array $bundles,
        private readonly BundleSchemaPathCollection $bundleSchemaPathCollection,
    ) {
        $this->frameworkPath = realpath($bundles['Framework']['path']) ?: $bundles['Framework']['path'];
        $this->corePath = str_ends_with($this->frameworkPath, '/src/Core/Framework') ? \dirname($this->frameworkPath, 2) : $this->frameworkPath;
        $this->schemaPath = $this->frameworkPath . '/Api/ApiDefinition/Generator/Schema/StoreApi';
        $this->allowlistPath = $this->frameworkPath . '/Api/ApiDefinition/Generator/StoreApiPhpGeneratedSchemaAllowlist.json';
    }

    /**
     * @param array<string, EntityDefinition> $definitions
     * @param self::SCOPE_* $scope
     *
     * @return StoreApiSchemaMigrationReport
     */
    public function report(array $definitions, string $scope = self::SCOPE_CORE): array
    {
        $phpGeneratedSchemaNames = $this->getPhpGeneratedSchemaNames($definitions, $scope);
        $jsonSchemaNames = $this->getJsonSchemaNames($scope);
        $allowlist = $this->loadAllowlist();

        $jsonOverridesPhpGenerated = $this->sortList(array_intersect($jsonSchemaNames, $phpGeneratedSchemaNames));
        $phpGeneratedOnly = $this->sortList(array_diff($phpGeneratedSchemaNames, $jsonSchemaNames));

        return [
            'jsonOverridesPhpGenerated' => $jsonOverridesPhpGenerated,
            'jsonOverridesPhpGeneratedAllowed' => $this->sortList(array_intersect($jsonOverridesPhpGenerated, $allowlist['jsonOverridesPhpGeneratedSchemas'])),
            'jsonOverridesPhpGeneratedWithoutAllowlist' => $this->sortList(array_diff($jsonOverridesPhpGenerated, $allowlist['jsonOverridesPhpGeneratedSchemas'])),
            'phpGeneratedOnly' => $phpGeneratedOnly,
            'phpGeneratedOnlyAllowed' => $this->sortList(array_intersect($phpGeneratedOnly, $allowlist['phpGeneratedStoreApiSchemas'])),
            'phpGeneratedOnlyWithoutAllowlist' => $this->sortList(array_diff($phpGeneratedOnly, $allowlist['phpGeneratedStoreApiSchemas'])),
            'jsonWithoutPhpGenerated' => $this->sortList(array_diff($jsonSchemaNames, $phpGeneratedSchemaNames)),
            'allowlistWithoutJsonOverridesPhpGeneratedSchema' => $this->sortList(array_diff($allowlist['jsonOverridesPhpGeneratedSchemas'], $jsonOverridesPhpGenerated)),
            'allowlistWithoutPhpGeneratedOnlySchema' => $this->sortList(array_diff($allowlist['phpGeneratedStoreApiSchemas'], $phpGeneratedOnly)),
            'allowlistWithoutPhpGeneratedSchema' => $this->sortList(array_diff($allowlist['phpGeneratedStoreApiSchemas'], $phpGeneratedSchemaNames)),
        ];
    }

    /**
     * @param array<string, EntityDefinition> $definitions
     * @param self::SCOPE_* $scope
     *
     * @return list<string>
     */
    private function getPhpGeneratedSchemaNames(array $definitions, string $scope): array
    {
        $schemaNames = [];

        ksort($definitions);

        foreach ($definitions as $definition) {
            if (!$this->shouldDefinitionBeIncluded($definition)) {
                continue;
            }

            if ($scope === self::SCOPE_CORE && !$this->isCoreDefinition($definition)) {
                continue;
            }

            $schema = $this->definitionSchemaBuilder->getSchemaByDefinition(
                $definition,
                $this->getResourceUri($definition),
                true,
                $this->shouldIncludeReferenceOnly($definition),
            );

            array_push($schemaNames, ...array_keys($schema));
        }

        return $this->sortList($schemaNames);
    }

    /**
     * @param self::SCOPE_* $scope
     *
     * @return list<string>
     */
    private function getJsonSchemaNames(string $scope): array
    {
        $schemaPaths = [$this->schemaPath];
        if ($scope === self::SCOPE_ALL) {
            $schemaPaths = array_merge($schemaPaths, $this->bundleSchemaPathCollection->getSchemaPaths(DefinitionService::STORE_API, null));
        }

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
    private function loadAllowlist(): array
    {
        if (!is_file($this->allowlistPath)) {
            return [
                'jsonOverridesPhpGeneratedSchemas' => [],
                'phpGeneratedStoreApiSchemas' => [],
            ];
        }

        $contents = file_get_contents($this->allowlistPath);
        if ($contents === false) {
            throw ApiException::schemaDefinitionNotReadable($this->allowlistPath);
        }

        try {
            $data = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ApiException::invalidStoreApiSchemaMigrationAllowlist($this->allowlistPath, 'JSON could not be decoded.', $exception);
        }

        if (!\is_array($data)) {
            throw ApiException::invalidStoreApiSchemaMigrationAllowlist($this->allowlistPath, 'The root value must be an object.');
        }

        return [
            'jsonOverridesPhpGeneratedSchemas' => $this->readAllowlistSchemaNames($data, 'jsonOverridesPhpGeneratedSchemas'),
            'phpGeneratedStoreApiSchemas' => $this->readAllowlistSchemaNames($data, 'phpGeneratedStoreApiSchemas'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private function readAllowlistSchemaNames(array $data, string $key): array
    {
        if (!isset($data[$key]) || !\is_array($data[$key])) {
            throw ApiException::invalidStoreApiSchemaMigrationAllowlist($this->allowlistPath, \sprintf('The "%s" list is missing.', $key));
        }

        $schemaNames = [];
        foreach ($data[$key] as $schemaName) {
            if (!\is_string($schemaName)) {
                throw ApiException::invalidStoreApiSchemaMigrationAllowlist($this->allowlistPath, \sprintf('The "%s" list must contain only schema names.', $key));
            }

            $schemaNames[] = $schemaName;
        }

        return $this->sortList($schemaNames);
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

    private function isCoreDefinition(EntityDefinition $definition): bool
    {
        $filename = (new \ReflectionClass($definition))->getFileName();
        if ($filename === false) {
            return false;
        }

        return str_starts_with($filename, $this->corePath . '/');
    }

    private function shouldIncludeReferenceOnly(EntityDefinition $definition): bool
    {
        $class = new \ReflectionClass($definition);
        if ($class->isSubclassOf(MappingEntityDefinition::class)) {
            return true;
        }

        if (!is_subclass_of($definition, SalesChannelDefinitionInterface::class)) {
            return true;
        }

        return false;
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

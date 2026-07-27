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
 * @phpstan-import-type OpenApiSpec from DefinitionService
 *
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

    private const PLATFORM_NAMESPACES = [
        'Shopware\\Administration\\',
        'Shopware\\Core\\',
        'Shopware\\Elasticsearch\\',
        'Shopware\\Storefront\\',
    ];

    private readonly string $schemaPath;

    private readonly string $allowlistPath;

    public function __construct(
        private readonly OpenApiDefinitionSchemaBuilder $definitionSchemaBuilder,
        private readonly BundleSchemaPathCollection $bundleSchemaPathCollection,
        private readonly Filesystem $filesystem = new Filesystem(),
        ?string $schemaPath = null,
        ?string $allowlistPath = null,
    ) {
        $this->schemaPath = $schemaPath ?? __DIR__ . '/Schema/StoreApi';
        $this->allowlistPath = $allowlistPath ?? __DIR__ . '/StoreApiPhpGeneratedSchemaAllowlist.json';
    }

    /**
     * @param array<string, EntityDefinition> $definitions
     * @param self::SCOPE_* $scope
     */
    public function report(array $definitions, string $scope = self::SCOPE_CORE): StoreApiSchemaMigrationReport
    {
        $phpGeneratedSchemaNames = $this->getPhpGeneratedSchemaNames($definitions, $scope);
        $jsonSchemaNames = $this->getJsonSchemaNames($scope);
        $allowlist = $this->loadAllowlist();

        $jsonOverridesPhpGenerated = array_intersect($jsonSchemaNames, $phpGeneratedSchemaNames);
        $phpGeneratedOnly = array_diff($phpGeneratedSchemaNames, $jsonSchemaNames);

        return new StoreApiSchemaMigrationReport(
            jsonOverridesPhpGenerated: $this->sortList($jsonOverridesPhpGenerated),
            jsonOverridesPhpGeneratedAllowed: $this->sortList(array_intersect($jsonOverridesPhpGenerated, $allowlist['jsonOverridesPhpGeneratedSchemas'])),
            jsonOverridesPhpGeneratedWithoutAllowlist: $this->sortList(array_diff($jsonOverridesPhpGenerated, $allowlist['jsonOverridesPhpGeneratedSchemas'])),
            phpGeneratedOnly: $this->sortList($phpGeneratedOnly),
            phpGeneratedOnlyAllowed: $this->sortList(array_intersect($phpGeneratedOnly, $allowlist['phpGeneratedStoreApiSchemas'])),
            phpGeneratedOnlyWithoutAllowlist: $this->sortList(array_diff($phpGeneratedOnly, $allowlist['phpGeneratedStoreApiSchemas'])),
            jsonWithoutPhpGenerated: $this->sortList(array_diff($jsonSchemaNames, $phpGeneratedSchemaNames)),
            allowlistWithoutJsonOverridesPhpGeneratedSchema: $this->sortList(array_diff($allowlist['jsonOverridesPhpGeneratedSchemas'], $jsonOverridesPhpGenerated)),
            allowlistWithoutPhpGeneratedOnlySchema: $this->sortList(array_diff($allowlist['phpGeneratedStoreApiSchemas'], $phpGeneratedOnly)),
            allowlistWithoutPhpGeneratedSchema: $this->sortList(array_diff($allowlist['phpGeneratedStoreApiSchemas'], $phpGeneratedSchemaNames)),
        );
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
        if (!$this->filesystem->exists($this->allowlistPath)) {
            return [
                'jsonOverridesPhpGeneratedSchemas' => [],
                'phpGeneratedStoreApiSchemas' => [],
            ];
        }

        try {
            $contents = $this->filesystem->readFile($this->allowlistPath);
        } catch (IOExceptionInterface) {
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
        foreach (self::PLATFORM_NAMESPACES as $namespace) {
            if (str_starts_with($definition::class, $namespace)) {
                return true;
            }
        }

        return false;
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

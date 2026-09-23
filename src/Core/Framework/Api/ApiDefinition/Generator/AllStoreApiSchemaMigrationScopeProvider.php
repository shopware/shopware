<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class AllStoreApiSchemaMigrationScopeProvider implements StoreApiSchemaMigrationScopeProviderInterface
{
    public const SCOPE = 'all';

    private readonly string $schemaPath;

    public function __construct(
        private readonly BundleSchemaPathCollection $bundleSchemaPathCollection,
        ?string $schemaPath = null,
    ) {
        $this->schemaPath = $schemaPath ?? __DIR__ . '/Schema/StoreApi';
    }

    public function getScope(): string
    {
        return self::SCOPE;
    }

    public function getDefinitionClassPrefixes(): array
    {
        return [];
    }

    public function getSchemaPaths(): array
    {
        return array_values(array_merge(
            [$this->schemaPath],
            $this->bundleSchemaPathCollection->getSchemaPaths(DefinitionService::STORE_API, null),
        ));
    }

    public function includesAllDefinitions(): bool
    {
        return true;
    }
}

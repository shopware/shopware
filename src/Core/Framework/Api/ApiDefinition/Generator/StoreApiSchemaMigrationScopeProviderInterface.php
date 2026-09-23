<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface StoreApiSchemaMigrationScopeProviderInterface
{
    public const SERVICE_TAG = 'shopware.store_api_schema_migration.scope_provider';

    public function getScope(): string;

    /**
     * @return list<string>
     */
    public function getDefinitionClassPrefixes(): array;

    /**
     * @return list<string>
     */
    public function getSchemaPaths(): array;

    public function includesAllDefinitions(): bool;
}

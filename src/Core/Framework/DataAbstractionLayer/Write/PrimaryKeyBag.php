<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class PrimaryKeyBag
{
    /**
     * @var array<string, array<array<string>>>
     */
    private array $primaryKeys = [];

    /**
     * @var array<string, array[]>
     */
    private array $existences = [];

    private bool $prefetchingCompleted = false;

    public function add(EntityDefinition $definition, array $primaryKey): void
    {
        $key = $this->getCacheKey($primaryKey);
        $this->primaryKeys[$definition->getEntityName()][$key] = $primaryKey;
    }

    public function getPrimaryKeys(): array
    {
        return $this->primaryKeys;
    }

    public function hasExistence(EntityDefinition $definition, array $primaryKey): bool
    {
        $key = $this->getCacheKey($primaryKey);

        return isset($this->existences[$definition->getEntityName()][$key]);
    }

    public function addExistenceState(EntityDefinition $definition, array $primaryKey, array $state): void
    {
        $key = $this->getCacheKey($primaryKey);
        $this->existences[$definition->getEntityName()][$key] = $state;
    }

    public function getExistenceState(EntityDefinition $definition, array $primaryKey): ?array
    {
        return $this->existences[$definition->getEntityName()][$this->getCacheKey($primaryKey)] ?? null;
    }

    public function setPrefetchingCompleted(bool $completed): void
    {
        $this->prefetchingCompleted = $completed;
    }

    public function isPrefetchingCompleted(): bool
    {
        return $this->prefetchingCompleted;
    }

    private function getCacheKey(array $primaryKey): string
    {
        return implode('_', $primaryKey);
    }
}

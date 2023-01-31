<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;

#[Package('sales-channel')]
class SeoResolverData
{
    /**
     * @var array<string, mixed>
     */
    private array $entityMap = [];

    public function add(string $entityName, Entity $entity): void
    {
        if (!isset($this->entityMap[$entityName])) {
            $this->entityMap[$entityName] = [];
        }

        $this->entityMap[$entityName][$entity->getUniqueIdentifier()] = $entity;
    }

    /**
     * @return array<string|int>
     */
    public function getEntities(): array
    {
        return array_keys($this->entityMap);
    }

    /**
     * @return array<string|int>
     */
    public function getIds(string $entityName): array
    {
        return array_keys($this->entityMap[$entityName]);
    }

    public function get(string $entityName, string $id): Entity
    {
        return $this->entityMap[$entityName][$id];
    }
}

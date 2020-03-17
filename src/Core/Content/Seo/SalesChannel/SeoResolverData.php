<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class SeoResolverData
{
    /**
     * @var array
     */
    private $entityMap = [];

    public function add(string $entityName, Entity $entity): void
    {
        if (!isset($this->entityMap[$entityName])) {
            $this->entityMap[$entityName] = [];
        }

        $this->entityMap[$entityName][$entity->getUniqueIdentifier()] = $entity;
    }

    public function getEntities(): array
    {
        return array_keys($this->entityMap);
    }

    public function getIds(string $entityName): array
    {
        return array_keys($this->entityMap[$entityName]);
    }

    public function get(string $entityName, string $id): Entity
    {
        return $this->entityMap[$entityName][$id];
    }
}

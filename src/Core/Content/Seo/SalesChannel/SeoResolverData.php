<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class SeoResolverData
{
    /**
     * @var array<string, array<string, array<string, Entity>>>
     */
    private array $entityMap = [];

    public function add(string $entityName, Entity $entity): void
    {
        if (!isset($this->entityMap[$entityName])) {
            $this->entityMap[$entityName] = [];
        }

        if (!isset($this->entityMap[$entityName][$entity->getUniqueIdentifier()])) {
            $this->entityMap[$entityName][$entity->getUniqueIdentifier()] = [];
        }

        /**
         * The same entity can be added multiple times, e.g. if the same product is assigned in multiple cross-selling groups
         * Using `spl_object_hash` to ensure that every entity can be added multiple times and hence allowing to enrich seoUrls for all these duplicated entities even if they're in different extensions
         */
        $hash = spl_object_hash($entity);

        if (isset($this->entityMap[$entityName][$entity->getUniqueIdentifier()][$hash])) {
            return;
        }

        $this->entityMap[$entityName][$entity->getUniqueIdentifier()][$hash] = $entity;
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

    /**
     * @deprecated tag:v6.8.0 - use getAll instead
     */
    public function get(string $entityName, string $id): Entity
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0', 'getAll')
        );

        return array_values($this->getAll($entityName, $id))[0];
    }

    /**
     * @return array<Entity>
     */
    public function getAll(string $entityName, string $id): array
    {
        return $this->entityMap[$entityName][$id];
    }
}

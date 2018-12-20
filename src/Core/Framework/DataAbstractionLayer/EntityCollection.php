<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Struct\Collection;

class EntityCollection extends Collection
{
    /**
     * @param Entity $entity
     */
    public function add($entity): void
    {
        $this->set($entity->getUniqueIdentifier(), $entity);
    }

    public function getIds(): array
    {
        return $this->fmap(function (Entity $entity) {
            return $entity->getUniqueIdentifier();
        });
    }

    public function filterByProperty(string $property, $value)
    {
        return $this->filter(
            function (Entity $struct) use ($property, $value) {
                return $struct->get($property) === $value;
            }
        );
    }

    public function merge(self $collection): void
    {
        /** @var Entity $entity */
        foreach ($collection as $entity) {
            if ($this->has($entity->getUniqueIdentifier())) {
                continue;
            }
            $this->add($entity);
        }
    }

    /**
     * @param array $ids
     *
     * @return static
     */
    public function getList(array $ids)
    {
        return new static(array_intersect_key($this->elements, array_flip($ids)));
    }

    public function sortByIdArray(array $ids): void
    {
        $sorted = [];
        foreach ($ids as $id) {
            if (array_key_exists($id, $this->elements)) {
                $sorted[$id] = $this->elements[$id];
            }
        }
        $this->elements = $sorted;
    }

    protected function getExpectedClass(): string
    {
        return Entity::class;
    }
}

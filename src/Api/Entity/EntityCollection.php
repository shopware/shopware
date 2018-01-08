<?php declare(strict_types=1);

namespace Shopware\Api\Entity;

use Shopware\Framework\Struct\Collection;

class EntityCollection extends Collection
{
    public function add(Entity $entity)
    {
        $class = $this->getExpectedClass();
        if (!$entity instanceof $class) {
            throw new \InvalidArgumentException(
                sprintf('Expected collection element of type %s got %s', $class, get_class($entity))
            );
        }

        $this->elements[$entity->getId()] = $entity;
    }

    public function get(string $id)
    {
        if ($this->has($id)) {
            return $this->elements[$id];
        }

        return null;
    }

    public function getIds(): array
    {
        return $this->fmap(function (Entity $entity) {
            return $entity->getId();
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

    public function merge(self $collection)
    {
        /** @var Entity $entity */
        foreach ($collection as $entity) {
            if ($this->has($entity->getId())) {
                continue;
            }
            $this->add($entity);
        }
    }

    public function remove(string $id): void
    {
        parent::doRemoveByKey($id);
    }

    public function getList(array $ids)
    {
        return new static(array_intersect_key($this->elements, array_flip($ids)));
    }

    public function sortByIdArray(array $ids)
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

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

        $this->elements[$entity->getUuid()] = $entity;
    }

    public function get(string $uuid)
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (Entity $entity) {
            return $entity->getUuid();
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

    public function merge(EntityCollection $collection)
    {
        /** @var Entity $entity */
        foreach ($collection as $entity) {
            if ($this->has($entity->getUuid())) {
                continue;
            }
            $this->add($entity);
        }
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function getList(array $uuids)
    {
        return new static(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function sortByUuidArray(array $uuids)
    {
        $sorted = [];
        foreach ($uuids as $uuid) {
            if (array_key_exists($uuid, $this->elements)) {
                $sorted[$uuid] = $this->elements[$uuid];
            }
        }
        $this->elements = $sorted;
    }

    protected function getExpectedClass(): string
    {
        return Entity::class;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @template TElement of Entity
 *
 * @extends Collection<TElement>
 */
#[Package('core')]
class EntityCollection extends Collection
{
    public function __construct(iterable $elements = [])
    {
        parent::__construct([]);

        foreach ($elements as $element) {
            $this->validateType($element);

            $this->set($element->getUniqueIdentifier(), $element);
        }
    }

    public function fill(array $entities): void
    {
        array_map([$this, 'add'], $entities);
    }

    /**
     * @param TElement $entity
     */
    public function add($entity): void
    {
        $this->set($entity->getUniqueIdentifier(), $entity);
    }

    /**
     * @return list<string>
     */
    public function getIds(): array
    {
        return $this->fmap(static function (Entity $entity) {
            return $entity->getUniqueIdentifier();
        });
    }

    /**
     * tag v6.6.0 Return type will be natively typed to `static`
     *
     * @return static
     */
    #[\ReturnTypeWillChange]
    public function filterByProperty(string $property, $value)
    {
        return $this->filter(
            static function (Entity $struct) use ($property, $value) {
                return $struct->get($property) === $value;
            }
        );
    }

    /**
     * tag v6.6.0 Return type will be natively typed to `static`
     *
     * @return static
     */
    #[\ReturnTypeWillChange]
    public function filterAndReduceByProperty(string $property, $value)
    {
        $filtered = [];

        foreach ($this->getIterator() as $key => $struct) {
            if ($struct->get($property) !== $value) {
                continue;
            }
            $filtered[] = $struct;
            $this->remove($key);
        }

        return $this->createNew($filtered);
    }

    /**
     * @param EntityCollection<TElement> $collection
     */
    public function merge(self $collection): void
    {
        /** @var TElement $entity */
        foreach ($collection as $entity) {
            if ($this->has($entity->getUniqueIdentifier())) {
                continue;
            }
            $this->add($entity);
        }
    }

    /**
     * @param TElement $entity
     */
    public function insert(int $position, Entity $entity): void
    {
        $items = array_values($this->elements);

        $this->elements = [];
        foreach ($items as $index => $item) {
            if ($index === $position) {
                $this->add($entity);
            }
            $this->add($item);
        }
    }

    /**
     * tag v6.6.0 Return type will be natively typed to `static`
     *
     * @return static
     */
    #[\ReturnTypeWillChange]
    public function getList(array $ids)
    {
        return $this->createNew(array_intersect_key($this->elements, array_flip($ids)));
    }

    public function sortByIdArray(array $ids): void
    {
        $sorted = [];

        foreach ($ids as $id) {
            if (\is_array($id)) {
                $id = implode('-', array_unique($id));
            }

            if (\array_key_exists($id, $this->elements)) {
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

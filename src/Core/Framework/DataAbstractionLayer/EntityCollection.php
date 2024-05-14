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
    /**
     * @param iterable<TElement> $elements
     */
    public function __construct(iterable $elements = [])
    {
        parent::__construct();

        foreach ($elements as $element) {
            $this->validateType($element);

            $this->set($element->getUniqueIdentifier(), $element);
        }
    }

    /**
     * @param array<TElement> $entities
     */
    public function fill(array $entities): void
    {
        array_map($this->add(...), $entities);
    }

    /**
     * @param TElement $entity
     */
    public function add($entity): void
    {
        $this->set($entity->getUniqueIdentifier(), $entity);
    }

    /**
     * @return array<string>
     */
    public function getIds(): array
    {
        return $this->fmap(static function (Entity $entity) {
            return $entity->getUniqueIdentifier();
        });
    }

    /**
     * @param mixed $value
     */
    public function filterByProperty(string $property, $value): static
    {
        return $this->filter(
            static function (Entity $struct) use ($property, $value) {
                return $struct->get($property) === $value;
            }
        );
    }

    public function filterAndReduceByProperty(string $property, $value): static
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
     * @param array<string> $ids
     */
    public function getList(array $ids): static
    {
        return $this->createNew(array_intersect_key($this->elements, array_flip($ids)));
    }

    /**
     * @param array<array-key, array<string>|string> $ids
     */
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

    /**
     * Global collector to access the value of multiple custom fields of the collection.
     *
     * If no fields are passed, all custom fields are returned.
     * If multiple fields are passed, the result is an array with the entity id as key and an array of custom fields as value.
     *
     * Example:
     * ```php
     * $collection->getCustomFieldsValues('my_custom_field', 'my_other_custom_field');
     *  [
     *      'entity-id-1' => [
     *          'my_custom_field' => 'value',
     *          'my_other_custom_field' => 'value',
     *      ],
     *      'entity-id-2' => [
     *          'my_custom_field' => 'value',
     *          'my_other_custom_field' => 'value',
     *      ],
     *  ]
     * ```
     *
     * @return array<string, mixed>
     */
    public function getCustomFieldsValues(string ...$fields): array
    {
        if (!$this->hasCustomFieldSupport(__METHOD__)) {
            return [];
        }

        $values = [];
        foreach ($this->elements as $element) {
            if (empty($fields)) {
                // @phpstan-ignore-next-line not possible to typehint or docblock the trait
                $values[$element->getUniqueIdentifier()] = $element->getCustomFields();

                continue;
            }

            // @phpstan-ignore-next-line not possible to typehint or docblock the trait
            $values[$element->getUniqueIdentifier()] = $element->getCustomFieldsValues(...$fields);
        }

        return $values;
    }

    /**
     * Global collector to access the value of a custom field of the collection.
     *
     * The result is an array with the entity id as key and the value of the custom field as value.
     *
     * Example:
     * ```php
     * $collection->getCustomFieldsValue('my_custom_field');
     *  [
     *      'entity-id-1' => 'value',
     *      'entity-id-2' => 'value',
     *  ]
     * ```
     *
     * @return array|mixed[]
     */
    public function getCustomFieldsValue(string $field): array
    {
        if (!$this->hasCustomFieldSupport(__METHOD__)) {
            return [];
        }

        $values = [];
        foreach ($this->elements as $element) {
            // @phpstan-ignore-next-line not possible to typehint or docblock the trait
            $values[$element->getUniqueIdentifier()] = $element->getCustomFieldsValue($field);
        }

        return $values;
    }

    /**
     * Sets the custom fields for all entities in the collection.
     *
     * The passed array must have the entity id as key and an array of custom fields as value.
     *
     * Example:
     * ```php
     * $collection->setCustomFields([
     *    'entity-id-1' => [
     *        'my_custom_field' => 'value',
     *        'my_other_custom_field' => 'value',
     *    ],
     *    'entity-id-2' => [
     *        'my_custom_field' => 'value',
     *        'my_other_custom_field' => 'value',
     *    ]
     * ]);
     * ```
     *
     * @param array<string, array<string, mixed>> $values
     */
    public function setCustomFields(array $values): void
    {
        if (!$this->hasCustomFieldSupport(__METHOD__)) {
            return;
        }

        foreach ($values as $id => $value) {
            $element = $this->get($id);
            if ($element === null) {
                continue;
            }

            // @phpstan-ignore-next-line not possible to typehint or docblock the trait
            $element->changeCustomFields($value);
        }
    }

    protected function getExpectedClass(): string
    {
        return Entity::class;
    }

    private function hasCustomFieldSupport(string $methodName): bool
    {
        $first = $this->first();
        if ($first === null) {
            return false;
        }
        $uses = \class_uses($first);
        if ($uses === false || !\in_array(EntityCustomFieldsTrait::class, $uses, true)) {
            throw new \RuntimeException(
                sprintf('%s() is only supported for entities that use the EntityCustomFieldsTrait', $methodName)
            );
        }

        return true;
    }
}

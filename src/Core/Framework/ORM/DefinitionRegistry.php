<?php declare(strict_types=1);

namespace Shopware\Framework\ORM;

/**
 * Contains all registered entity definitions.
 */
class DefinitionRegistry
{
    /**
     * @var string[]
     */
    protected $elements = [];

    public function __construct(array $elements)
    {
        $this->elements = $elements;
    }

    /**
     * @param string|EntityDefinition $definition
     */
    public function add(string $definition): void
    {
        $this->elements[$definition::getEntityName()] = $definition;
    }

    /**
     * @param string $entity
     *
     * @return null|string|EntityDefinition
     */
    public function get(string $entity): ?string
    {
        return $this->elements[$entity] ?? null;
    }

    public function getByClass(string $class): ?string
    {
        foreach ($this->elements as $element) {
            if ($element === $class) {
                return $element;
            }
        }

        return null;
    }

    /**
     * @return EntityDefinition[]|string[]
     */
    public function getElements(): array
    {
        return $this->elements;
    }
}

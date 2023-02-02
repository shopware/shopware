<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Symfony\Component\Validator\Constraint;

class DataValidationDefinition
{
    /**
     * @var array
     */
    private $properties = [];

    /**
     * @var DataValidationDefinition[]
     */
    private $subDefinitions = [];

    /**
     * @var DataValidationDefinition[]
     */
    private $listDefinitions = [];

    /**
     * @var string
     */
    private $name;

    public function __construct(string $name = '')
    {
        $this->name = $name;
    }

    public function add(string $name, Constraint ...$constraints): self
    {
        $list = $this->properties[$name] ?? [];

        foreach ($constraints as $constraint) {
            $list[] = $constraint;
        }

        $this->properties[$name] = $list;

        return $this;
    }

    public function set(string $name, Constraint ...$constraints): self
    {
        if (\array_key_exists($name, $this->properties)) {
            unset($this->properties[$name]);
        }

        return $this->add($name, ...$constraints);
    }

    public function addSub(string $name, DataValidationDefinition $definition): self
    {
        $this->subDefinitions[$name] = $definition;

        return $this;
    }

    public function addList(string $name, DataValidationDefinition $definition): self
    {
        $this->listDefinitions[$name] = $definition;

        return $this;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getSubDefinitions(): array
    {
        return $this->subDefinitions;
    }

    public function getListDefinitions(): array
    {
        return $this->listDefinitions;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

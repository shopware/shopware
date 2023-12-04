<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

#[Package('core')]
class DataValidationDefinition
{
    /**
     * @var array<string, Constraint[]>
     */
    private array $properties = [];

    /**
     * @var DataValidationDefinition[]
     */
    private array $subDefinitions = [];

    /**
     * @var DataValidationDefinition[]
     */
    private array $listDefinitions = [];

    public function __construct(private readonly string $name = '')
    {
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

    /**
     * @return array<string, Constraint[]>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @return DataValidationDefinition[]
     */
    public function getSubDefinitions(): array
    {
        return $this->subDefinitions;
    }

    /**
     * @return DataValidationDefinition[]
     */
    public function getListDefinitions(): array
    {
        return $this->listDefinitions;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

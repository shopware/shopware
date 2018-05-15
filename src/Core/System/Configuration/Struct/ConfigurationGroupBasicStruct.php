<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Struct;

use Shopware\Framework\ORM\Entity;

class ConfigurationGroupBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var bool
     */
    protected $filterable;

    /**
     * @var bool
     */
    protected $comparable;

    /**
     * @var int
     */
    protected $position;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getFilterable(): bool
    {
        return $this->filterable;
    }

    public function setFilterable(bool $filterable): void
    {
        $this->filterable = $filterable;
    }

    public function getComparable(): bool
    {
        return $this->comparable;
    }

    public function setComparable(bool $comparable): void
    {
        $this->comparable = $comparable;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}

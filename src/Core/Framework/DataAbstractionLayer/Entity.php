<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Struct\Struct;

class Entity extends Struct
{
    /**
     * @var string
     */
    protected $id;

    public function __toString()
    {
        return $this->getId();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function get(string $property)
    {
        try {
            return $this->$property;
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(
                sprintf('Property %s do not exist in class %s', $property, \get_class($this))
            );
        }
    }
}

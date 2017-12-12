<?php declare(strict_types=1);

namespace Shopware\Api\Entity;

use Shopware\Framework\Struct\Struct;

class Entity extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var float
     */
    protected $searchScore = 0;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function get(string $property)
    {
        try {
            return $this->$property;
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(
                sprintf('Property %s do not exist in class %s', $property, get_class($this))
            );
        }
    }

    public function getSearchScore(): float
    {
        return $this->searchScore;
    }

    public function setSearchScore(float $searchScore): void
    {
        $this->searchScore = $searchScore;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM;

use Shopware\Core\Framework\Struct\Struct;

class Entity extends Struct
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $tenantId;

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
                sprintf('Property %s do not exist in class %s', $property, get_class($this))
            );
        }
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function setTenantId(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }
}

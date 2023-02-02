<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

/**
 * @codeCoverageIgnore
 */
class PermissionStruct extends StoreStruct
{
    /**
     * @var string
     */
    protected $entity;

    /**
     * @var string
     */
    protected $operation;

    public static function fromArray(array $data): StoreStruct
    {
        return (new self())->assign($data);
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): void
    {
        $this->entity = $entity;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function setOperation(string $operation): void
    {
        $this->operation = $operation;
    }
}

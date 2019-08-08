<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\Acl\Resource\AclResourceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

class InsertCommand implements WriteCommandInterface
{
    /**
     * @var array
     */
    private $payload;

    /**
     * @var EntityDefinition
     */
    private $definition;

    /**
     * @var array
     */
    private $primaryKey;
    /**
     * @var EntityExistence
     */
    private $existence;

    public function __construct(EntityDefinition $definition, array $payload, array $primaryKey, EntityExistence $existence)
    {
        $this->payload = $payload;
        $this->definition = $definition;
        $this->primaryKey = $primaryKey;
        $this->existence = $existence;
    }

    public function isValid(): bool
    {
        return (bool) \count($this->payload);
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function getPrimaryKey(): array
    {
        return $this->primaryKey;
    }

    public function getEntityExistence(): EntityExistence
    {
        return $this->existence;
    }

    public function getPrivilege(): string
    {
        return AclResourceDefinition::PRIVILEGE_CREATE;
    }
}

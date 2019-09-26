<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\Acl\Resource\AclResourceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

class DeleteCommand implements WriteCommandInterface
{
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

    public function __construct(EntityDefinition $definition, array $pkData, EntityExistence $existence)
    {
        $this->definition = $definition;
        $this->primaryKey = $pkData;
        $this->existence = $existence;
    }

    public function isValid(): bool
    {
        return (bool) \count($this->primaryKey);
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
        return AclResourceDefinition::PRIVILEGE_DELETE;
    }

    public function getPayload(): array
    {
        return [];
    }

    public function getPath(): string
    {
        return '';
    }
}

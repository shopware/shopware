<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\Api\Acl\Resource\AclResourceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

class DeleteCommand extends WriteCommand implements ChangeSetAware
{
    use ChangeSetAwareTrait;

    public function __construct(EntityDefinition $definition, array $primaryKey, EntityExistence $existence)
    {
        parent::__construct($definition, [], $primaryKey, $existence, '');
    }

    public function isValid(): bool
    {
        return (bool) \count($this->primaryKey);
    }

    public function getPrivilege(): string
    {
        return AclResourceDefinition::PRIVILEGE_DELETE;
    }
}

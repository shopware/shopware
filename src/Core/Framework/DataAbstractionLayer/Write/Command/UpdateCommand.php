<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;

class UpdateCommand extends WriteCommand implements ChangeSetAware
{
    use ChangeSetAwareTrait;

    public function getPrivilege(): ?string
    {
        return AclRoleDefinition::PRIVILEGE_UPDATE;
    }
}

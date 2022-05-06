<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-final - Will be @final
 * @final
 */
class InsertCommand extends WriteCommand
{
    public function getPrivilege(): ?string
    {
        return AclRoleDefinition::PRIVILEGE_CREATE;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\Api\Acl\Resource\AclResourceDefinition;

class InsertCommand extends WriteCommand
{
    public function getPrivilege(): string
    {
        return AclResourceDefinition::PRIVILEGE_CREATE;
    }
}

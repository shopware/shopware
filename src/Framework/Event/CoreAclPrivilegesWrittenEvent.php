<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class CoreAclPrivilegesWrittenEvent extends WrittenEvent
{
    const NAME = 's_core_acl_privileges.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_core_acl_privileges';
    }
}

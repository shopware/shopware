<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class CoreAclResourcesWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 's_core_acl_resources.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_core_acl_resources';
    }
}

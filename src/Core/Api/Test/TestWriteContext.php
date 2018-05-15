<?php declare(strict_types=1);

namespace Shopware\Api\Test;

use Shopware\Framework\ORM\Write\WriteContext;
use Shopware\Application\Context\Struct\ApplicationContext;

class TestWriteContext extends WriteContext
{
    private function __construct()
    {
    }

    public static function create(): WriteContext
    {
        return WriteContext::createFromApplicationContext(
            ApplicationContext::createDefaultContext(Defaults::TENANT_ID)
        );
    }
}

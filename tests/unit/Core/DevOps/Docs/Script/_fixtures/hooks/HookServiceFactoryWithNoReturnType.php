<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures\hooks;

use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;

class HookServiceFactoryWithNoReturnType
{
    public function factory(Hook $hook, Script $script): SimpleService|bool
    {
        return new SimpleService();
    }

    public function getName(): string
    {
        return 'no_return_type_service';
    }
}

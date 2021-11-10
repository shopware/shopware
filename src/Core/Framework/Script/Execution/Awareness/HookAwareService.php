<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution\Awareness;

use Shopware\Core\Framework\Script\Execution\Hook;

abstract class HookAwareService
{
    public function inject(Hook $hook): void
    {
        // don't assign the hook to the service
        // this method should be used to extract hook information like context and sales channel context
    }

    abstract public function getName(): string;
}

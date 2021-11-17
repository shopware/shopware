<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution\Awareness;

use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;

abstract class HookAwareService
{
    /**
     * The inject function can be used to resolve dynamic dependencies of a hook.
     * Probably the most common use case for this is the dependency injection of the sales channel context
     * Instead of the script developer having to inject the context into a script service every time,
     * the service can have it injected in advance and provide the developer a slimmer api
     */
    public function inject(Hook $hook, Script $script): void
    {
        // don't assign the hook to the service
        // this method should be used to extract hook information like context and sales channel context
    }

    abstract public function getName(): string;
}

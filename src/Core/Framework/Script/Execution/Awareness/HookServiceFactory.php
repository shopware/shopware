<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution\Awareness;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;

/**
 * @internal not to be intended that plugin developers can provide services for hooks (atm)
 */
#[Package('core')]
abstract class HookServiceFactory
{
    /**
     * The factory method creates a new instance of the service to be made available to the hooks.
     * The service is then available under the accessor specified in `getName()`.
     *
     * @return object Returns a new instance
     */
    abstract public function factory(Hook $hook, Script $script): object;

    /**
     * Defines the name under which the created service is available. For example, if `cart` is returned,
     * the service will be available in scripts under `services.cart`
     */
    abstract public function getName(): string;

    /**
     * Is executed after each script and allows cleaning up state or execute some finish tasks at the end of each script.
     *
     * The provided service is the instance of the `self::factory` method
     */
    public function after(object $service, Hook $hook, Script $script): void
    {
    }
}

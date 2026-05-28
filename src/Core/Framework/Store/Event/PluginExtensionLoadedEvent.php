<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fired by ExtensionLoader while building the extension struct for a plugin.
 * Listeners may inspect the plugin's class to flip `$isTheme` so the resulting ExtensionStruct reflects theme status without Core knowing about Storefront's ThemeInterface.
 *
 * @internal
 */
#[Package('checkout')]
final class PluginExtensionLoadedEvent extends Event
{
    public bool $isTheme = false;

    public function __construct(
        public readonly PluginEntity $plugin,
        public readonly Context $context,
    ) {
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fired by ExtensionLoader after building the ExtensionStruct for an installed plugin or app.
 * Listeners receive the source entity and the resulting struct and may enrich it - e.g. Storefront
 * checks the source and flags themes via $extension->setIsTheme(true) - without Core depending on Storefront.
 *
 * @internal
 */
#[Package('checkout')]
final class ExtensionLoadedEvent extends Event
{
    public function __construct(
        public readonly AppEntity|PluginEntity $source,
        public readonly ExtensionStruct $extension,
        public readonly Context $context,
    ) {
    }
}

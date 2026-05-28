<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fired by ExtensionLoader while building the extension struct for an installed app.
 * Listeners may flip `$isTheme` based on package-specific knowledge (e.g. Storefront checks whether the app's name appears in its installed-themes registry).
 *
 * @internal
 */
#[Package('checkout')]
final class AppExtensionLoadedEvent extends Event
{
    public bool $isTheme = false;

    public function __construct(
        public readonly AppEntity $app,
        public readonly Context $context,
    ) {
    }
}

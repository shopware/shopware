<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fired per app during shop-id-change resolution when apps are silently uninstalled.
 * Listeners run local cleanup (e.g. theme uninstall) without notifying the app backend or running the standard deactivation lifecycle (no webhooks, no flow indexing).
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
final class AppSilentlyUninstalledEvent extends Event
{
    public function __construct(
        public readonly AppEntity $app,
        public readonly Context $context,
    ) {
    }
}

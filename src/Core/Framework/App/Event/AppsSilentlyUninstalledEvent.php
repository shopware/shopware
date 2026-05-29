<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fired once per shop-id-change resolution when apps are silently uninstalled, carrying the full set of affected apps.
 * Listeners run local cleanup (e.g. theme uninstall) without notifying the app backend or running the standard deactivation lifecycle (no webhooks, no flow indexing).
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
final class AppsSilentlyUninstalledEvent extends Event
{
    public function __construct(
        public readonly AppCollection $apps,
        public readonly Context $context,
    ) {
    }
}

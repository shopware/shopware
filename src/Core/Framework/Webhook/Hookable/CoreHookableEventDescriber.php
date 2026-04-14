<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Hookable;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Hookable;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class CoreHookableEventDescriber implements HookableEventDescriber
{
    /**
     * @return list<HookableEventDescription>
     */
    public function describe(Manifest $manifest): array
    {
        $events = [];

        foreach (Hookable::HOOKABLE_EVENTS as $eventClass => $eventName) {
            $events[] = new HookableEventDescription(
                $eventName,
                Hookable::HOOKABLE_EVENTS_PRIVILEGES[$eventClass]
            );
        }

        return $events;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Authorization\Policy\Policy;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\Webhook;
use Shopware\Core\Service\Event\CommercialLicenseProvidedEvent;

/**
 * Restricts the events that carry service data to self-managed apps.
 *
 * @internal
 */
#[Package('framework')]
final class ServiceWebhookPolicy implements Policy
{
    public function __construct(private readonly ActiveAppsLoader $activeAppsLoader)
    {
    }

    public function handles(string $eventName): bool
    {
        return $eventName === CommercialLicenseProvidedEvent::NAME;
    }

    public function permitsSubscription(string $eventName, Subscriber $subscriber): bool
    {
        return $subscriber->manifest?->getMetadata()->isSelfManaged() ?? false;
    }

    public function permitsDelivery(Hookable $event, Webhook $webhook): bool
    {
        if ($webhook->appName === null) {
            return false;
        }

        foreach ($this->activeAppsLoader->getActiveApps() as $app) {
            if ($app['name'] === $webhook->appName) {
                return $app['selfManaged'];
            }
        }

        return false;
    }
}

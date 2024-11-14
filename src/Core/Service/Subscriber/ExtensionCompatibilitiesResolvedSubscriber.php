<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Event\ExtensionCompatibilitiesResolvedEvent;
use Shopware\Core\Framework\Update\Services\ExtensionCompatibility;
use Shopware\Core\Service\ServiceRegistryClient;
use Shopware\Core\Service\ServiceRegistryEntry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('core')]
class ExtensionCompatibilitiesResolvedSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ServiceRegistryClient $serviceRegistryClient)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ExtensionCompatibilitiesResolvedEvent::class => 'markAppsWithServiceAsCompatible',
        ];
    }

    public function markAppsWithServiceAsCompatible(ExtensionCompatibilitiesResolvedEvent $event): void
    {
        $services = $this->serviceRegistryClient->getAll();
        $serviceNames = array_map(fn (ServiceRegistryEntry $entry) => $entry->name, $services);

        $compatibilities = [];
        foreach ($event->compatibilities as $compatibility) {
            if (\in_array($compatibility['name'], $serviceNames, true)) {
                // this app is a service
                $compatibility['statusName'] = ExtensionCompatibility::PLUGIN_COMPATIBILITY_UPDATABLE_FUTURE;
                $compatibility['statusMessage'] = 'With new Shopware version';
                $compatibility['statusColor'] = 'yellow';
                $compatibility['statusVariant'] = null;
            }

            $compatibilities[] = $compatibility;
        }

        $event->compatibilities = $compatibilities;
    }
}

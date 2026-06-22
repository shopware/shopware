<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\DTO\Service;
use Shopware\Core\Service\Event\NewServicesInstalledEvent;
use Shopware\Core\Service\Message\InstallServicesMessage;
use Shopware\Core\Service\ServiceRegistry\Client;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('framework')]
class AllServiceInstaller
{
    public function __construct(
        private readonly Client $serviceRegistryClient,
        private readonly ServiceStorage $serviceStorage,
        private readonly ServiceLifecycle $serviceLifecycle,
        private readonly MessageBusInterface $messageBus,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Discovers services in the registry that are not yet installed and hands each one to
     * ServiceLifecycle, which resolves the strategy and performs the resulting operation.
     * It should only be called from a higher-level, 'state'-aware class: Shopware\Core\Service\LifecycleManager.
     *
     * @return array<string> The newly installed services
     */
    public function install(Context $context): array
    {
        $existingServices = $this->serviceStorage->findAll($context);

        $installedServices = [];
        foreach ($this->getNewServices($existingServices) as $entry) {
            if ($this->serviceLifecycle->install($entry, $context)) {
                $installedServices[] = $entry->name;
            }
        }

        if ($installedServices !== []) {
            $this->eventDispatcher->dispatch(new NewServicesInstalledEvent());
        }

        return $installedServices;
    }

    public function scheduleInstall(): void
    {
        $this->messageBus->dispatch(new InstallServicesMessage());
    }

    /**
     * @param list<Service> $installedServices
     *
     * @return array<ServiceEntry>
     */
    private function getNewServices(array $installedServices): array
    {
        $names = array_map(static fn (Service $service) => $service->name, $installedServices);

        return array_filter(
            $this->serviceRegistryClient->getAll(),
            static fn (ServiceEntry $service) => !\in_array($service->name, $names, true)
        );
    }
}

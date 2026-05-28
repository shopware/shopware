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
        private readonly ServiceLifecycle $serviceLifecycle,
        private readonly ServiceRepository $serviceRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * This is a low-level class that is responsible for installing all services.
     * It should only be called from a higher-level with 'state' awareness class, Specifically: Shopware\Core\Service\LifecycleManager
     *
     * @return array<string> The newly installed services
     */
    public function install(Context $context): array
    {
        $existingServices = $this->serviceRepository->findAll($context);

        $installedServices = [];
        $newServices = $this->getNewServices($existingServices);
        foreach ($newServices as $service) {
            $result = $this->serviceLifecycle->install($service, $context);

            if ($result) {
                $installedServices[] = $service->name;
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

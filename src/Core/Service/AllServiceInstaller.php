<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Psr\Log\LoggerInterface;
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
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string> The newly installed services
     */
    public function reconcile(Context $context): array
    {
        $existingServices = $this->serviceStorage->findAll($context);
        $registryServices = $this->serviceRegistryClient->getAll();

        $installedServices = $this->installNewServices($existingServices, $registryServices, $context);

        $this->updateServices($existingServices, $registryServices, $context);

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
     * @param list<Service> $existingServices
     * @param array<ServiceEntry> $registryServices
     *
     * @return array<string>
     */
    private function installNewServices(array $existingServices, array $registryServices, Context $context): array
    {
        $installedServices = [];
        foreach ($this->getNewServices($existingServices, $registryServices) as $entry) {
            try {
                $installed = $this->serviceLifecycle->install($entry, $context);

                if ($installed) {
                    $installedServices[] = $entry->name;
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Cannot install service', ['service' => $entry->name, 'exception' => $e]);
            }
        }

        return $installedServices;
    }

    /**
     * @param list<Service> $existingServices
     * @param array<ServiceEntry> $registryServices
     */
    private function updateServices(array $existingServices, array $registryServices, Context $context): void
    {
        $registryServiceNames = [];
        foreach ($registryServices as $registryService) {
            $registryServiceNames[$registryService->name] = true;
        }

        foreach ($existingServices as $service) {
            if (!isset($registryServiceNames[$service->name])) {
                continue;
            }

            try {
                $this->serviceLifecycle->update($service->name, $context);
            } catch (\Throwable $exception) {
                $this->logger->warning('Cannot update service', ['service' => $service->name, 'exception' => $exception]);
            }
        }
    }

    /**
     * @param list<Service> $installedServices
     * @param array<ServiceEntry> $registryServices
     *
     * @return array<ServiceEntry>
     */
    private function getNewServices(array $installedServices, array $registryServices): array
    {
        $names = array_map(static fn (Service $service) => $service->name, $installedServices);

        return array_filter(
            $registryServices,
            static fn (ServiceEntry $service) => !\in_array($service->name, $names, true)
        );
    }
}

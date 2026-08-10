<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\DTO\Service;
use Shopware\Core\Service\Event\NewServicesInstalledEvent;
use Shopware\Core\Service\Message\InstallServicesMessage;
use Shopware\Core\Service\Message\UpdateServiceMessage;
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
     * Converges the shop toward the registry: discovers services that are not yet installed and hands
     * each one to ServiceLifecycle, and schedules an update for every already-installed service still
     * listed in the registry so it reaches the registry's latest revision.
     * It should only be called from a higher-level, 'state'-aware class: Shopware\Core\Service\LifecycleManager.
     *
     * @return array<string> The newly installed services
     */
    public function reconcile(Context $context): array
    {
        $existingServices = $this->serviceStorage->findAll($context);
        $registryServices = $this->serviceRegistryClient->getAll();

        $installedServices = $this->installNewServices($existingServices, $registryServices, $context);

        $this->scheduleUpdates($existingServices, $registryServices);

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
                $this->logger->warning(\sprintf('Cannot install service "%s" because of error: "%s"', $entry->name, $e->getMessage()));
            }
        }

        if ($installedServices !== []) {
            $this->eventDispatcher->dispatch(new NewServicesInstalledEvent());
        }

        return $installedServices;
    }

    /**
     * The update is idempotent (ServiceLifecycle::update no-ops when the installed revision already
     * matches), so enqueuing for every in-registry service is safe.
     *
     * @param list<Service> $existingServices
     * @param array<ServiceEntry> $registryServices
     */
    private function scheduleUpdates(array $existingServices, array $registryServices): void
    {
        $registryServiceNames = [];
        foreach ($registryServices as $registryService) {
            $registryServiceNames[$registryService->name] = true;
        }

        $scheduled = [];
        foreach ($existingServices as $service) {
            if (isset($registryServiceNames[$service->name])) {
                $this->messageBus->dispatch(new UpdateServiceMessage($service->name));
                $scheduled[] = $service->name;
            }
        }

        if ($scheduled !== []) {
            $this->logger->debug('Reconcile scheduled updates for installed services', [
                'count' => \count($scheduled),
                'services' => $scheduled,
            ]);
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

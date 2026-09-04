<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\DTO\Service;
use Shopware\Core\Service\Permission\PermissionsService;
use Shopware\Core\Service\Requirement\Gate;
use Shopware\Core\Service\Requirement\RequirementsValidator;
use Shopware\Core\Service\ServiceRegistry\Client;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * This class is responsible for managing the full lifecycle of self-managed services (apps).
 *
 * ENABLE_SERVICES controls whether services are installed. The persisted disabled state is handled through the
 * services_enabled requirement and is evaluated per service.
 *
 * If its requirements are met, each service can have two states:
 * Started: The service is running. The underlying application backing the service has all the required permissions.
 * Stopped: The service is not running. The underlying application backing the service is in a Pending Permission state.
 *
 * @internal
 */
#[Package('framework')]
class LifecycleManager
{
    public const AUTO_ENABLED = 'auto';

    public const CONFIG_KEY_SERVICES_DISABLED = 'core.services.disabled';

    public function __construct(
        private readonly string $enabled,
        private readonly string $appEnv,
        private readonly Privileges $privileges,
        private readonly SystemConfigService $systemConfigService,
        private readonly ServiceStorage $serviceStorage,
        private readonly ServiceLifecycle $serviceLifecycle,
        private readonly AllServiceInstaller $serviceInstaller,
        private readonly PermissionsService $permissionsService,
        private readonly Client $client,
        private readonly RequirementsValidator $requirementsValidator,
    ) {
    }

    public function sync(Context $context): void
    {
        $this->removeOrphanedServices($this->serviceStorage->findAll($context), $context);
        $this->serviceLifecycle->reevaluateInstalled($context);
    }

    /**
     * Level-triggered counterpart to the update push (ServiceController::triggerUpdate): installs new
     * services, converges installed ones to the registry's latest revision, both via the same
     * idempotent update path, and re-evaluates the installed services against their requirements.
     * Only runs if ENABLE_SERVICES allows it. No orphan removal, that stays in sync().
     *
     * @return array<string> The newly installed services
     */
    public function reconcile(Context $context): array
    {
        if (!$this->enabled()) {
            return [];
        }

        $installed = $this->serviceInstaller->reconcile($context);
        $this->serviceLifecycle->reevaluateInstalled($context);

        return $installed;
    }

    public function syncState(string $serviceName, Context $context): void
    {
        $service = $this->serviceStorage->findByName($serviceName, $context);
        if ($service === null) {
            throw ServiceException::serviceNotInstalled($serviceName);
        }

        $this->syncPrivileges($service, $context);
    }

    /**
     * Re-evaluate all services that list the given requirement.
     * Called when a requirement's state changes.
     */
    public function reevaluateRequirement(string $requirementName, Context $context): void
    {
        foreach ($this->serviceStorage->findAll($context) as $service) {
            if (\in_array($requirementName, $service->requirements, true)) {
                $this->syncPrivileges($service, $context);
            }
        }
    }

    /**
     * This method clears the persisted disabled state and schedules the installation of all services.
     */
    public function enable(): void
    {
        $this->systemConfigService->delete(self::CONFIG_KEY_SERVICES_DISABLED, null, true);

        $this->serviceInstaller->scheduleInstall();
    }

    /**
     * Disables services as a unit: persists the disabled flag, syncs the installed services, and revokes
     * their permissions.
     */
    public function disable(Context $context): void
    {
        $this->systemConfigService->set(self::CONFIG_KEY_SERVICES_DISABLED, true, null, true);

        $this->serviceLifecycle->reevaluateInstalled($context);
        $this->permissionsService->revoke($context);
    }

    public function enabled(): bool
    {
        return !$this->areDisabledFromEnv();
    }

    private function areDisabledFromEnv(): bool
    {
        if ($this->enabled === self::AUTO_ENABLED) {
            $enabled = $this->appEnv === 'prod';
        } else {
            $enabled = filter_var($this->enabled, \FILTER_VALIDATE_BOOLEAN);
        }

        return !$enabled;
    }

    private function syncPrivileges(Service $service, Context $context): void
    {
        if ($this->requirementsValidator->isSatisfied($service->requirements, Gate::PRIVILEGES)) {
            $this->privileges->acceptAllForApps([$service->id], $context);
        } else {
            $this->privileges->revokeAllForApps([$service->id], $context);
        }
    }

    /**
     * @param list<Service> $services
     */
    private function removeOrphanedServices(array $services, Context $context): void
    {
        $registryServices = $this->client->getAll();

        if ($registryServices === []) {
            // this is not safe to do if there are zero services.
            // it could be a transient error or a misconfiguration.
            return;
        }

        $registryServiceNames = [];
        foreach ($registryServices as $registryService) {
            $registryServiceNames[$registryService->name] = true;
        }

        foreach ($services as $service) {
            if (!isset($registryServiceNames[$service->name])) {
                $this->serviceLifecycle->uninstall($service->name, $context);
            }
        }
    }
}

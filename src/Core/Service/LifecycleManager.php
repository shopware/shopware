<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Permission\PermissionsService;
use Shopware\Core\Service\Requirement\RequirementsValidator;
use Shopware\Core\Service\ServiceRegistry\Client;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * This class is responsible for managing the full lifecycle of self-managed services (apps).
 *
 * Services (As a unit) can have two states:
 * Disabled: No Service is usable, or installed.
 * Enabled: All the applications backing the services are installed.
 *
 * Then, if enabled, each service can have two states:
 * Started: The service is running. The underlying application backing the service has all the required permissions.
 * Stopped: The service is not running. The underlying application backing the service is in a Pending Permission state.
 *
 * @internal
 *
 * @phpstan-import-type ServiceSourceConfig from ServiceSourceResolver
 */
#[Package('framework')]
class LifecycleManager
{
    public const AUTO_ENABLED = 'auto';

    public const CONFIG_KEY_SERVICES_DISABLED = 'core.services.disabled';

    /**
     * @param EntityRepository<AppCollection> $repository
     */
    public function __construct(
        private readonly string $enabled,
        private readonly string $appEnv,
        private readonly Privileges $privileges,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $repository,
        private readonly AbstractAppLifecycle $appLifecycle,
        private readonly AllServiceInstaller $serviceInstaller,
        private readonly PermissionsService $permissionsService,
        private readonly Client $client,
        private readonly RequirementsValidator $requirementsValidator,
    ) {
    }

    /**
     * This method installs all services, only if Services (as a unit) are enabled.
     *
     * @return array<string> The newly installed services
     */
    public function install(Context $context): array
    {
        if (!$this->enabled()) {
            return [];
        }

        return $this->serviceInstaller->install($context);
    }

    public function sync(Context $context): void
    {
        $services = $this->getAllServices($context);
        $this->removeOrphanedServices($services, $context);
    }

    public function syncState(string $service, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $service));
        $criteria->addFilter(new EqualsFilter('selfManaged', true));
        $app = $this->repository->search($criteria, $context)->getEntities()->first();
        if ($app === null) {
            throw ServiceException::serviceNotInstalled($service);
        }

        $this->syncPrivileges($app, $context);
    }

    public function syncPrivileges(AppEntity $app, Context $context): void
    {
        if ($this->requirementsValidator->isSatisfied($app)) {
            $this->privileges->acceptAllForApps([$app->getId()], $context);
        } else {
            $this->privileges->revokeAllForApps([$app->getId()], $context);
        }
    }

    /**
     * Re-evaluate all services that list the given requirement.
     * Called when a requirement's state changes.
     */
    public function syncRequirement(string $requirementName, Context $context): void
    {
        foreach ($this->getAllServices($context) as $app) {
            $requirements = $this->getRequirements($app);
            if (\in_array($requirementName, $requirements, true)) {
                $this->syncPrivileges($app, $context);
            }
        }
    }

    /**
     * This method enables the services (as aa unit), allowing them to be installed and later used.
     * It also schedules the installation of all services.
     */
    public function enable(): void
    {
        $this->systemConfigService->delete(self::CONFIG_KEY_SERVICES_DISABLED);

        $this->serviceInstaller->scheduleInstall();
    }

    /**
     * This method disables the services (as a unit), preventing any service from being installed or used.
     */
    public function disable(Context $context): void
    {
        foreach ($this->getAllServices($context) as $service) {
            $this->appLifecycle->delete($service->getName(), ['id' => $service->getId()], $context);
        }

        $this->permissionsService->revoke($context);
        $this->systemConfigService->set(self::CONFIG_KEY_SERVICES_DISABLED, true);
    }

    public function enabled(): bool
    {
        return !$this->areDisabledFromEnv() && !$this->areDisabledFromConfig();
    }

    private function removeOrphanedServices(AppCollection $services, Context $context): void
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
            if (!isset($registryServiceNames[$service->getName()])) {
                $this->appLifecycle->delete($service->getName(), ['id' => $service->getId()], $context);
            }
        }
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

    private function areDisabledFromConfig(): bool
    {
        return $this->systemConfigService->getBool(self::CONFIG_KEY_SERVICES_DISABLED);
    }

    private function getAllServices(Context $context): AppCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('selfManaged', true));

        return $this->repository->search($criteria, $context)->getEntities();
    }

    /**
     * @return list<string>
     */
    private function getRequirements(AppEntity $app): array
    {
        /** @var ServiceSourceConfig $sourceConfig */
        $sourceConfig = $app->getSourceConfig();

        return AppInfo::fromNameAndSourceConfig($app->getName(), $sourceConfig)->requirements;
    }
}

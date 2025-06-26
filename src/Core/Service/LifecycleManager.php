<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * This class is responsible for managing the full lifecycle of self-managed services (apps).
 *
 * @internal
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
    ) {
    }

    /**
     * @return array<string> The newly installed services
     */
    public function install(Context $context): array
    {
        if (!$this->enabled()) {
            return [];
        }

        return $this->serviceInstaller->install($context);
    }

    public function start(Context $context): void
    {
        /** @var list<string> $serviceIds */
        $serviceIds = $this->getAllServices($context)->getIds();

        $this->privileges->acceptAllForApps($serviceIds, $context);
    }

    public function stop(Context $context): void
    {
        /** @var list<string> $serviceIds */
        $serviceIds = $this->getAllServices($context)->getIds();

        $this->privileges->revokeAllForApps($serviceIds, $context);
    }

    public function enable(): void
    {
        $this->systemConfigService->delete(self::CONFIG_KEY_SERVICES_DISABLED);

        $this->serviceInstaller->scheduleInstall();
    }

    public function disable(Context $context): void
    {
        foreach ($this->getAllServices($context) as $service) {
            $this->appLifecycle->delete($service->getName(), ['id' => $service->getId()], $context);
        }

        $this->permissionsService->revokePermissions($context);
        $this->systemConfigService->set(self::CONFIG_KEY_SERVICES_DISABLED, true);
    }

    public function enabled(): bool
    {
        return !$this->areDisabledFromEnv() && !$this->areDisabledFromConfig();
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
        return $this->systemConfigService->getBool(self::CONFIG_KEY_SERVICES_DISABLED) === true;
    }

    private function getAllServices(Context $context): AppCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('selfManaged', true));

        return $this->repository->search($criteria, $context)->getEntities();
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class AllServiceInstaller
{
    public const AUTO_ENABLED = 'auto';

    /**
     * @internal
     *
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly string $enabled,
        private readonly string $appEnv,
        private readonly ServiceRegistryClient $serviceRegistryClient,
        private readonly ServiceLifecycle $serviceLifecycle,
        private readonly EntityRepository $appRepository,
    ) {
    }

    /**
     * @return array<string> The newly installed services
     */
    public function install(Context $context): array
    {
        // auto means not explicitly enabled, then we enable it based on the app environment
        if ($this->enabled === self::AUTO_ENABLED) {
            $enabled = $this->appEnv === 'prod';
        } else {
            $enabled = filter_var($this->enabled, \FILTER_VALIDATE_BOOLEAN);
        }

        if (!$enabled) {
            return [];
        }

        $existingServices = $this->appRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('selfManaged', true)),
            $context
        );

        $installedServices = [];
        $newServices = $this->getNewServices($existingServices);
        foreach ($newServices as $service) {
            $result = $this->serviceLifecycle->install($service, $context);

            if ($result) {
                $installedServices[] = $service->name;
            }
        }

        return $installedServices;
    }

    /**
     * @param EntitySearchResult<AppCollection> $installedServices
     *
     * @return array<ServiceRegistryEntry>
     */
    private function getNewServices(EntitySearchResult $installedServices): array
    {
        $names = $installedServices->map(fn (AppEntity $app) => $app->getName());

        return array_filter(
            $this->serviceRegistryClient->getAll(),
            static fn (ServiceRegistryEntry $service) => !\in_array($service->name, $names, true)
        );
    }
}

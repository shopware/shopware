<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Staging\Handler;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;

/**
 * @internal
 */
#[Package('framework')]
readonly class StagingExtensionHandler
{
    /**
     * @param EntityRepository<PluginCollection> $pluginRepository
     * @param EntityRepository<AppCollection> $appRepository
     * @param list<string> $extensionsToDisable
     */
    public function __construct(
        private EntityRepository $pluginRepository,
        private PluginLifecycleService $pluginLifecycleService,
        private EntityRepository $appRepository,
        private AppStateService $appStateService,
        private array $extensionsToDisable = [],
    ) {
    }

    public function __invoke(SetupStagingEvent $event): void
    {
        if ($this->extensionsToDisable === []) {
            return;
        }

        $names = array_values(array_unique(array_filter(array_map(static fn ($v) => trim($v), $this->extensionsToDisable))));
        if ($names === []) {
            return;
        }

        $event->io->info(\sprintf('Staging: Checking %d extension(s) to disable: %s', \count($names), implode(', ', $names)));

        $foundNames = [];

        $pluginCriteria = new Criteria();
        $pluginCriteria->addFilter(new EqualsAnyFilter('name', $names));
        $plugins = $this->pluginRepository->search($pluginCriteria, $event->context)->getEntities();

        foreach ($plugins as $plugin) {
            $foundNames[] = $plugin->getName();

            if (!$plugin->getActive()) {
                $event->io->comment(\sprintf('Plugin %s is already inactive.', $plugin->getName()));
                continue;
            }

            try {
                $this->pluginLifecycleService->deactivatePlugin($plugin, $event->context);
                $event->io->info(\sprintf('Deactivated plugin %s for staging.', $plugin->getName()));
            } catch (\Throwable $e) {
                $event->io->warning(\sprintf('Failed to deactivate plugin %s: %s', $plugin->getName(), $e->getMessage()));
            }
        }

        $appCriteria = new Criteria();
        $appCriteria->addFilter(new EqualsAnyFilter('name', $names));
        $apps = $this->appRepository->search($appCriteria, $event->context)->getEntities();

        foreach ($apps as $app) {
            $foundNames[] = $app->getName();

            if (!$app->isActive()) {
                $event->io->comment(\sprintf('App %s is already inactive.', $app->getName()));
                continue;
            }

            try {
                $this->appStateService->deactivateApp($app->getId(), $event->context);
                $event->io->info(\sprintf('Deactivated app %s for staging.', $app->getName()));
            } catch (\Throwable $e) {
                $event->io->warning(\sprintf('Failed to deactivate app %s: %s', $app->getName(), $e->getMessage()));
            }
        }

        $missing = array_diff($names, array_unique($foundNames));
        foreach ($missing as $miss) {
            $event->io->warning(\sprintf('Configured extension %s not found and could not be disabled.', $miss));
        }
    }
}

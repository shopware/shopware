<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Staging\Handler;

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
readonly class StagingPluginHandler
{
    /**
     * @param EntityRepository<PluginCollection> $pluginRepository
     * @param list<string> $pluginsToDisable
     */
    public function __construct(
        private EntityRepository $pluginRepository,
        private PluginLifecycleService $pluginLifecycleService,
        private array $pluginsToDisable = [],
    ) {
    }

    public function __invoke(SetupStagingEvent $event): void
    {
        if ($this->pluginsToDisable === []) {
            return;
        }

        $names = array_values(array_unique(array_filter(array_map(static fn ($v) => \is_string($v) ? trim($v) : '', $this->pluginsToDisable))));
        if ($names === []) {
            return;
        }

        $event->io->info(\sprintf('Staging: Checking %d plugin(s) to disable: %s', \count($names), implode(', ', $names)));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', $names));

        $plugins = $this->pluginRepository->search($criteria, $event->context)->getEntities();

        $foundNames = [];
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

        $missing = array_diff($names, $foundNames);
        foreach ($missing as $miss) {
            $event->io->warning(\sprintf('Configured plugin %s not found and could not be disabled.', $miss));
        }
    }
}

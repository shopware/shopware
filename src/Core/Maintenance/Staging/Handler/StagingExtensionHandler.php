<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Staging\Handler;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\AbstractExtensionLifecycle;
use Shopware\Core\Kernel;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;

/**
 * @internal
 */
#[Package('framework')]
readonly class StagingExtensionHandler
{
    public function __construct(
        private Kernel $kernel,
        private AbstractExtensionDataProvider $extensionDataProvider,
        private AbstractExtensionLifecycle $extensionLifecycleService,
    ) {
    }

    public function __invoke(SetupStagingEvent $event): void
    {
        $extensionsToDisable = array_values(array_unique(array_filter(array_map(static fn ($v) => trim($v), $event->extensionsToDisable))));
        if ($extensionsToDisable === []) {
            return;
        }

        if ($this->kernel->getPluginLoader() instanceof ComposerPluginLoader) {
            $event->io->warning(
                \sprintf("Staging: Should disable %d extension(s): %s\nHowever the ComposerPluginLoader is used, which does not support disabling extensions, therefore they should be uninstalled using composer directly.", \count($extensionsToDisable), implode(', ', $extensionsToDisable))
            );

            return;
        }

        $event->io->info(
            \sprintf('Staging: Checking %d extension(s) to disable: %s', \count($extensionsToDisable), implode(', ', $extensionsToDisable))
        );

        $extensionCriteria = new Criteria();
        $extensionCriteria->addFilter(new EqualsAnyFilter('name', $extensionsToDisable));

        $extensions = $this->extensionDataProvider->getInstalledExtensions(
            context: $event->context,
            searchCriteria: $extensionCriteria,
        );

        $foundExtensions = [];
        foreach ($extensions as $extension) {
            $foundExtensions[] = $extension->getName();

            if (!$extension->getActive()) {
                $event->io->comment(\sprintf('Extension %s is already inactive.', $extension->getName()));
                continue;
            }

            try {
                $this->extensionLifecycleService->deactivate($extension->getType(), $extension->getName(), $event->context);
                $event->io->info(\sprintf('Deactivated extension %s for staging.', $extension->getName()));
            } catch (\Throwable $e) {
                $event->io->warning(\sprintf('Failed to deactivate extension %s: %s', $extension->getName(), $e->getMessage()));
            }
        }

        $missing = array_diff($extensionsToDisable, array_unique($foundExtensions));
        foreach ($missing as $miss) {
            $event->io->warning(\sprintf('Configured extension %s not found and could not be disabled.', $miss));
        }
    }
}

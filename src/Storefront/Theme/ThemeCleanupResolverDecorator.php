<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\ShopIdChangeResolver\Resolver;
use Shopware\Core\Framework\App\ShopIdChangeResolver\UninstallAppsStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

/**
 * Decorates the Core shop-id-change Resolver to run Storefront theme cleanup after the silent uninstall
 * strategy completed, without Core having to know about themes. The app names are captured before the
 * strategy runs because the uninstall path deletes the app rows.
 *
 * @internal
 */
#[Package('framework')]
readonly class ThemeCleanupResolverDecorator extends Resolver
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private Resolver $inner,
        private EntityRepository $appRepository,
        private StorefrontPluginRegistry $themeRegistry,
        private ThemeLifecycleHandler $themeLifecycleHandler,
    ) {
        parent::__construct([]);
    }

    public function resolve(string $strategyName, Context $context): void
    {
        if ($strategyName !== UninstallAppsStrategy::STRATEGY_NAME) {
            $this->inner->resolve($strategyName, $context);

            return;
        }

        // Capture the technical names before the strategy deletes the app rows.
        $uninstalledNames = [];
        foreach ($this->appRepository->search(new Criteria(), $context)->getEntities() as $app) {
            $uninstalledNames[] = $app->getName();
        }

        $this->inner->resolve($strategyName, $context);

        $this->cleanupThemes($uninstalledNames, $context);
    }

    /**
     * @return array<string>
     */
    public function getAvailableStrategies(): array
    {
        return $this->inner->getAvailableStrategies();
    }

    /**
     * @param array<string> $uninstalledNames
     */
    private function cleanupThemes(array $uninstalledNames, Context $context): void
    {
        foreach ($uninstalledNames as $name) {
            $config = $this->themeRegistry->getConfigurations()->getByTechnicalName($name);
            if ($config !== null) {
                $this->themeLifecycleHandler->handleThemeUninstall($config, $context);
            }
        }

        $remaining = $this->themeRegistry
            ->getConfigurations()
            ->filter(static fn (StorefrontPluginConfiguration $config): bool => !\in_array($config->getTechnicalName(), $uninstalledNames, true));

        $this->themeLifecycleHandler->refreshAllActiveThemeImportMaps($context, $remaining);
    }
}

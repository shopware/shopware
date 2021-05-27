<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Storefront\Theme\Exception\ThemeAssignmentException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;

class ThemeLifecycleHandler
{
    private ThemeLifecycleService $themeLifecycleService;

    private ThemeService $themeService;

    private EntityRepositoryInterface $themeRepository;

    private StorefrontPluginRegistryInterface $storefrontPluginRegistry;

    private Connection $connection;

    public function __construct(
        ThemeLifecycleService $themeLifecycleService,
        ThemeService $themeService,
        EntityRepositoryInterface $themeRepository,
        StorefrontPluginRegistryInterface $storefrontPluginRegistry,
        Connection $connection
    ) {
        $this->themeLifecycleService = $themeLifecycleService;
        $this->themeService = $themeService;
        $this->themeRepository = $themeRepository;
        $this->storefrontPluginRegistry = $storefrontPluginRegistry;
        $this->connection = $connection;
    }

    public function handleThemeInstallOrUpdate(
        StorefrontPluginConfiguration $config,
        StorefrontPluginConfigurationCollection $configurationCollection,
        Context $context
    ): void {
        if ($config->getIsTheme()) {
            $this->themeLifecycleService->refreshTheme($config, $context);
            $this->changeThemeActive($config->getTechnicalName(), true, $context);
        }

        $this->recompileThemesIfNecessary($config, $context, $configurationCollection);
    }

    public function handleThemeUninstall(StorefrontPluginConfiguration $config, Context $context): void
    {
        if ($config->getIsTheme()) {
            // throw an exception if theme is still assigned to a sales channel
            $this->validateThemeAssignment($config->getTechnicalName(), $context);

            // set active = false in the database to theme and all children
            $this->changeThemeActive($config->getTechnicalName(), false, $context);
        }

        $configs = $this->storefrontPluginRegistry->getConfigurations();
        $configs = $configs->filter(function (StorefrontPluginConfiguration $registeredConfig) use ($config): bool {
            return $registeredConfig->getTechnicalName() !== $config->getTechnicalName();
        });

        $this->recompileThemesIfNecessary($config, $context, $configs);
    }

    /**
     * @throws ThemeAssignmentException
     * @throws InconsistentCriteriaIdsException
     */
    private function validateThemeAssignment(string $technicalName, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('salesChannels');
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));
        /** @var ThemeEntity|null $theme */
        $theme = $this->themeRepository->search($criteria, $context)->first();

        if (!$theme) {
            return;
        }

        $themeSalesChannel = [];
        $salesChannels = $theme->getSalesChannels() ?? new SalesChannelCollection();
        if ($salesChannels->count() > 0) {
            $themeSalesChannel[$technicalName] = array_values($salesChannels->getIds());
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentThemeId', $theme->getId()));
        $criteria->addAssociation('salesChannels');
        /** @var ThemeCollection $childThemes */
        $childThemes = $this->themeRepository->search($criteria, $context)->getEntities();

        $childThemeSalesChannel = [];
        if ($childThemes->count() > 0) {
            foreach ($childThemes as $childTheme) {
                $childThemeSalesChannels = $childTheme->getSalesChannels();
                if ($childThemeSalesChannels === null || $childThemeSalesChannels->count() === 0) {
                    continue;
                }
                $salesChannels->merge($childThemeSalesChannels);
                $childThemeSalesChannel[$childTheme->getName()] = array_values($childThemeSalesChannels->getIds());
            }
        }

        if (\count($themeSalesChannel) === 0 && \count($childThemeSalesChannel) === 0) {
            return;
        }

        throw new ThemeAssignmentException($technicalName, $themeSalesChannel, $childThemeSalesChannel, $salesChannels);
    }

    private function changeThemeActive(string $technicalName, bool $active, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));
        $criteria->addAssociation('childThemes');
        /** @var ThemeEntity|null $theme */
        $theme = $this->themeRepository->search($criteria, $context)->first();

        if (!$theme) {
            return;
        }

        $data = [];
        $data[] = ['id' => $theme->getId(), 'active' => $active];
        $childThemes = $theme->getChildThemes();
        if ($childThemes) {
            foreach ($childThemes->getIds() as $id) {
                $data[] = ['id' => $id, 'active' => $active];
            }
        }

        if (\count($data)) {
            $this->themeRepository->update($data, $context);
        }
    }

    private function recompileThemesIfNecessary(StorefrontPluginConfiguration $config, Context $context, StorefrontPluginConfigurationCollection $configurationCollection): void
    {
        if (!$config->hasFilesToCompile()) {
            return;
        }

        $mappings = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(sales_channel_id)) as sales_channel_id, LOWER(HEX(theme_id)) as theme_id FROM theme_sales_channel');

        foreach ($mappings as $mapping) {
            $this->themeService->compileTheme(
                $mapping['sales_channel_id'],
                $mapping['theme_id'],
                $context,
                $configurationCollection
            );
        }
    }
}

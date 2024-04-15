<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\Exception\ThemeAssignmentException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\Struct\ThemeDependencies;

#[Package('storefront')]
class ThemeLifecycleHandler
{
    public const STATE_SKIP_THEME_COMPILATION = 'skip-theme-compilation';

    /**
     * @internal
     */
    public function __construct(
        private readonly ThemeLifecycleService $themeLifecycleService,
        private readonly ThemeService $themeService,
        private readonly EntityRepository $themeRepository,
        private readonly StorefrontPluginRegistryInterface $storefrontPluginRegistry,
        private readonly Connection $connection
    ) {
    }

    public function handleThemeInstallOrUpdate(
        StorefrontPluginConfiguration $config,
        StorefrontPluginConfigurationCollection $configurationCollection,
        Context $context
    ): void {
        $themeId = null;
        if ($config->getIsTheme()) {
            $this->themeLifecycleService->refreshTheme($config, $context);
            $themeData = $this->getThemeDataByTechnicalName($config->getTechnicalName());
            $themeId = $themeData->getId();
            $this->changeThemeActive($themeData, true, $context);
        }

        $this->recompileThemesIfNecessary($config, $context, $configurationCollection, $themeId);
    }

    public function handleThemeUninstall(StorefrontPluginConfiguration $config, Context $context): void
    {
        $themeId = $this->deactivateTheme($config, $context);

        $configs = $this->storefrontPluginRegistry->getConfigurations();

        $configs = $configs->filter(fn (StorefrontPluginConfiguration $registeredConfig): bool => $registeredConfig->getTechnicalName() !== $config->getTechnicalName());

        $this->recompileThemesIfNecessary($config, $context, $configs, $themeId);
    }

    public function recompileAllActiveThemes(Context $context, ?StorefrontPluginConfigurationCollection $configurationCollection = null): void
    {
        // Recompile all themes as the extension generally extends the storefront
        $mappings = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(sales_channel_id)) as sales_channel_id, LOWER(HEX(theme_id)) as theme_id
             FROM theme_sales_channel'
        );

        foreach ($mappings as $mapping) {
            $this->themeService->compileTheme(
                $mapping['sales_channel_id'],
                $mapping['theme_id'],
                $context,
                $configurationCollection
            );
        }
    }

    /**
     * @throws ThemeAssignmentException
     * @throws InconsistentCriteriaIdsException
     */
    private function validateThemeAssignment(?string $themeId): void
    {
        if (!$themeId) {
            return;
        }

        if ($this->themeService->getThemeDependencyMapping($themeId)->count() === 0) {
            return;
        }

        $this->throwAssignmentException($themeId);
    }

    private function changeThemeActive(ThemeDependencies $themeData, bool $active, Context $context): void
    {
        if ($themeData->getId() === null) {
            return;
        }

        $data = [];
        $data[] = ['id' => $themeData->getId(), 'active' => $active];

        foreach ($themeData->getDependentThemes() as $id) {
            $data[] = ['id' => $id, 'active' => $active];
        }

        $this->themeRepository->upsert($data, $context);
    }

    private function recompileThemesIfNecessary(
        StorefrontPluginConfiguration $config,
        Context $context,
        StorefrontPluginConfigurationCollection $configurationCollection,
        ?string $themeId
    ): void {
        if ($context->hasState(self::STATE_SKIP_THEME_COMPILATION)) {
            return;
        }

        if (!$config->hasFilesToCompile() && !$config->hasAdditionalBundles()) {
            return;
        }

        if ($themeId !== null) {
            $this->themeService->compileThemeById(
                $themeId,
                $context,
                $configurationCollection
            );

            return;
        }

        $this->recompileAllActiveThemes($context, $configurationCollection);
    }

    private function getThemeDataByTechnicalName(string $technicalName): ThemeDependencies
    {
        $themeData = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(theme.id)) as id, LOWER(HEX(childTheme.id)) as dependentId FROM theme
                LEFT JOIN theme as childTheme ON childTheme.parent_theme_id = theme.id
                WHERE theme.technical_name = :technicalName',
            ['technicalName' => $technicalName]
        );

        if (empty($themeData)) {
            return new ThemeDependencies();
        }

        $themes = new ThemeDependencies(current($themeData)['id']);
        foreach ($themeData as $data) {
            if ($data['dependentId']) {
                $themes->addDependentTheme($data['dependentId']);
            }
        }

        return $themes;
    }

    private function throwAssignmentException(string $themeId): void
    {
        $salesChannels = [];
        $themeSalesChannel = [];
        $themeName = $themeId;

        try {
            $themeData = $this->connection->fetchAllAssociative(
                'SELECT theme.name as themeName, childTheme.name as dthemeName, LOWER(HEX(theme.id)) as id,
                LOWER(HEX(childTheme.id)) as dependentId, LOWER(HEX(tsc.sales_channel_id)) as saleschannelId,
                sc.name as saleschannelName, dsc.name as dsaleschannelName,
                LOWER(HEX(dtsc.sales_channel_id)) as dsaleschannelId
                FROM theme
                LEFT JOIN theme as childTheme ON childTheme.parent_theme_id = theme.id
                LEFT JOIN theme_sales_channel as tsc ON theme.id = tsc.theme_id
                LEFT JOIN sales_channel_translation as sc ON tsc.sales_channel_id = sc.sales_channel_id AND sc.language_id = :langId
                LEFT JOIN theme_sales_channel as dtsc ON childTheme.id = dtsc.theme_id
                LEFT JOIN sales_channel_translation as dsc ON dtsc.sales_channel_id = dsc.sales_channel_id AND dsc.language_id = :langId
                WHERE theme.id = :id',
                ['id' => Uuid::fromHexToBytes($themeId), 'langId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
            );

            $childThemeSalesChannel = [];
            foreach ($themeData as $data) {
                $themeName = $data['themeName'];
                if (isset($data['id'], $data['saleschannelId']) && $data['id'] === $themeId) {
                    $themeSalesChannel[(string) $data['themeName']][] = (string) $data['saleschannelId'];
                    $salesChannels[(string) $data['saleschannelId']] = (string) $data['saleschannelName'];
                }
                if (isset($data['dsaleschannelId']) && !empty($data['dsaleschannelId']) && isset($data['dthemeName'])) {
                    $childThemeSalesChannel[(string) $data['dthemeName']][] = (string) $data['dsaleschannelId'];
                    $salesChannels[(string) $data['dsaleschannelId']] = (string) $data['dsaleschannelName'];
                }
            }
        } catch (\Throwable $e) {
            // on case an error occurs while fetching data for the exception we still want to have the correct exception
            throw new ThemeAssignmentException(
                $themeId,
                [],
                [],
                $salesChannels,
                $e
            );
        }

        throw new ThemeAssignmentException(
            $themeName,
            $themeSalesChannel,
            $childThemeSalesChannel,
            $salesChannels
        );
    }

    public function deactivateTheme(StorefrontPluginConfiguration $config, Context $context): ?string
    {
        $themeId = null;
        if ($config->getIsTheme()) {
            $themeData = $this->getThemeDataByTechnicalName($config->getTechnicalName());
            $themeId = $themeData->getId();

            // throw an exception if theme is still assigned to a sales channel
            $this->validateThemeAssignment($themeId);

            // set active = false in the database to theme and all children
            $this->changeThemeActive($themeData, false, $context);
        }

        return $themeId;
    }
}

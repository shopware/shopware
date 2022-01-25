<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Uuid\Uuid;
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
        $themeId = null;
        if ($config->getIsTheme()) {
            $this->themeLifecycleService->refreshTheme($config, $context);
            $themeData = $this->getThemeDataByTechnicalName($config->getTechnicalName());
            $themeId = $themeData['id'];
            $this->changeThemeActive($themeData, true, $context);
        }

        $this->recompileThemesIfNecessary($config, $context, $configurationCollection, $themeId);
    }

    public function handleThemeUninstall(StorefrontPluginConfiguration $config, Context $context): void
    {
        $themeId = null;
        if ($config->getIsTheme()) {
            $themeData = $this->getThemeDataByTechnicalName($config->getTechnicalName());
            $themeId = $themeData['id'];

            // throw an exception if theme is still assigned to a sales channel
            $this->validateThemeAssignment($themeId);

            // set active = false in the database to theme and all children
            $this->changeThemeActive($themeData, false, $context);
        }

        $configs = $this->storefrontPluginRegistry->getConfigurations();

        $configs = $configs->filter(function (StorefrontPluginConfiguration $registeredConfig) use ($config): bool {
            return $registeredConfig->getTechnicalName() !== $config->getTechnicalName();
        });

        $this->recompileThemesIfNecessary($config, $context, $configs, $themeId);
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

    private function changeThemeActive(array $themeData, bool $active, Context $context): void
    {
        if (empty($themeData)) {
            return;
        }

        $data = [];
        $data[] = ['id' => $themeData['id'], 'active' => $active];

        if (isset($themeData['dependentThemes'])) {
            foreach ($themeData['dependentThemes'] as $id) {
                $data[] = ['id' => $id, 'active' => $active];
            }
        }

        if (\count($data)) {
            $this->themeRepository->upsert($data, $context);
        }
    }

    private function recompileThemesIfNecessary(
        StorefrontPluginConfiguration $config,
        Context $context,
        StorefrontPluginConfigurationCollection $configurationCollection,
        ?string $themeId
    ): void {
        if (!$config->hasFilesToCompile()) {
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

    private function getThemeDataByTechnicalName(string $technicalName): array
    {
        $themeData = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(theme.id)) as id, LOWER(HEX(childTheme.id)) as dependentId FROM theme 
                LEFT JOIN theme as childTheme ON childTheme.parent_theme_id = theme.id 
                WHERE theme.technical_name = :technicalName',
            ['technicalName' => $technicalName]
        );

        if (empty($themeData)) {
            return [
                'id' => null,
            ];
        }

        $themes = [
            'id' => current($themeData)['id'],
        ];
        foreach ($themeData as $data) {
            if ($data['dependentId']) {
                $themes['dependentThemes'][] = $data['dependentId'];
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
                if (isset($data['id']) && isset($data['saleschannelId']) && $data['id'] === $themeId && $data['saleschannelId'] !== null) {
                    $themeSalesChannel[$data['themeName']][] = $data['saleschannelId'];
                    $salesChannels[$data['saleschannelId']] = $data['saleschannelName'];
                }
                if (isset($data['dsaleschannelId']) && !empty($data['dsaleschannelId']) && isset($data['dthemeName'])) {
                    $childThemeSalesChannel[$data['dthemeName']][] = $data['dsaleschannelId'];
                    $salesChannels[$data['dsaleschannelId']] = $data['dsaleschannelName'];
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
}

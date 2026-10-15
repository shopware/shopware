<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\Exception\ThemeAssignmentException;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\Struct\ThemeDependencies;

#[Package('discovery')]
class ThemeLifecycleHandler
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed.
     * use {@see AbstractAppLifecycle::STATE_SKIP_THEME_COMPILATION} instead.
     * This constant only re-exports it so existing references keep working until removal.
     */
    public const STATE_SKIP_THEME_COMPILATION = AbstractAppLifecycle::STATE_SKIP_THEME_COMPILATION;

    /**
     * @internal
     *
     * @param EntityRepository<ThemeCollection> $themeRepository
     */
    public function __construct(
        private readonly ThemeLifecycleService $themeLifecycleService,
        private readonly ThemeService $themeService,
        private readonly EntityRepository $themeRepository,
        private readonly StorefrontPluginRegistry $storefrontPluginRegistry,
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
            $this->themeLifecycleService->refreshTheme($config, $context, $configurationCollection);
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

        $configs = $configs->filter(static fn (StorefrontPluginConfiguration $registeredConfig): bool => $registeredConfig->getTechnicalName() !== $config->getTechnicalName());

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

    public function refreshAllActiveThemeImportMaps(Context $context, ?StorefrontPluginConfigurationCollection $configurationCollection = null): void
    {
        $mappings = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(sales_channel_id)) as sales_channel_id, LOWER(HEX(theme_id)) as theme_id
             FROM theme_sales_channel'
        );

        foreach ($mappings as $mapping) {
            $this->themeService->refreshThemeImportMap(
                $mapping['sales_channel_id'],
                $mapping['theme_id'],
                $context,
                $configurationCollection
            );
        }
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

    /**
     * @throws ThemeAssignmentException
     * @throws ThemeException
     * @throws InconsistentCriteriaIdsException
     */
    private function validateThemeAssignment(?string $themeId): void
    {
        if ($themeId === null || $themeId === '') {
            return;
        }

        $mappings = $this->themeService->getThemeDependencyMapping($themeId);
        if ($mappings->count() === 0) {
            return;
        }

        $this->throwAssignmentException($themeId, $mappings);
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
        if ($context->hasState(AbstractAppLifecycle::STATE_SKIP_THEME_COMPILATION)) {
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

        if ($themeData === []) {
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

    private function throwAssignmentException(string $themeId, ThemeSalesChannelCollection $mappings): void
    {
        $salesChannels = [];
        $themeSalesChannel = [];

        try {
            $themeIds = [$themeId];
            $salesChannelIds = [];
            foreach ($mappings as $mapping) {
                $themeIds[] = $mapping->getThemeId();
                $salesChannelIds[] = $mapping->getSalesChannelId();
            }

            /** @var array<string, string> $themeNames */
            $themeNames = $this->connection->fetchAllKeyValue(
                'SELECT LOWER(HEX(id)), name FROM theme WHERE id IN (:themeIds)',
                ['themeIds' => Uuid::fromHexToBytesList(array_unique($themeIds))],
                ['themeIds' => ArrayParameterType::BINARY],
            );
            $salesChannels = $this->connection->fetchAllKeyValue(
                'SELECT LOWER(HEX(sales_channel_id)), name FROM sales_channel_translation
                 WHERE sales_channel_id IN (:salesChannelIds) AND language_id = :langId',
                [
                    'salesChannelIds' => Uuid::fromHexToBytesList(array_unique($salesChannelIds)),
                    'langId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                ],
                ['salesChannelIds' => ArrayParameterType::BINARY],
            );

            $themeName = $themeNames[$themeId] ?? $themeId;
            $childThemeSalesChannel = [];
            // Describe exactly the recursive assignments that blocked the lifecycle operation.
            foreach ($mappings as $mapping) {
                $mappedThemeName = $themeNames[$mapping->getThemeId()] ?? $mapping->getThemeId();
                $salesChannelId = $mapping->getSalesChannelId();
                $salesChannels[$salesChannelId] ??= '';
                if ($mapping->getThemeId() === $themeId) {
                    $themeSalesChannel[$mappedThemeName][] = $salesChannelId;
                } else {
                    $childThemeSalesChannel[$mappedThemeName][] = $salesChannelId;
                }
            }
        } catch (\Throwable $e) {
            // on case an error occurs while fetching data for the exception we still want to have the correct exception
            throw ThemeException::themeAssignmentException(
                $themeId,
                [],
                [],
                $salesChannels,
                $e
            );
        }

        throw ThemeException::themeAssignmentException(
            $themeName,
            $themeSalesChannel,
            $childThemeSalesChannel,
            $salesChannels
        );
    }
}

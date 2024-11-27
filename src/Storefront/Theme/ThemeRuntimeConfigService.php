<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;

#[Package('storefront')]
class ThemeRuntimeConfigService
{
    public function __construct(
        private ThemeFileResolver $themeFileResolver,
        private StorefrontPluginRegistryInterface $pluginRegistry,
        private ThemeService $themeService,
        private readonly Connection $connection,
    ) {
    }

    public function getRuntimeConfig(string $themeId): ?ThemeRuntimeConfig
    {
        $resolvedTheme = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT
                `theme_id` as `themeId`,
                `resolved_config` as `resolvedConfig`,
                `script_files` as `scriptFiles`,
                `style_files` as `styleFiles`,
                `icon_sets` as `iconSets`,
                `updated_at` as `updatedAt`
                FROM `theme_runtime_config`
                WHERE `theme_id` = :themeId
            SQL,
            ['themeId' => Uuid::fromHexToBytes($themeId)],
        );

        if (!$resolvedTheme) {
            return null;
        }

        return ThemeRuntimeConfig::fromArray([
            'themeId' => Uuid::fromBytesToHex($resolvedTheme['themeId']),
            'resolvedConfig' => json_decode($resolvedTheme['resolvedConfig'], true, 512, \JSON_THROW_ON_ERROR),
            'scriptFiles' => json_decode($resolvedTheme['scriptFiles'], true, 512, \JSON_THROW_ON_ERROR),
            'styleFiles' => json_decode($resolvedTheme['styleFiles'], true, 512, \JSON_THROW_ON_ERROR),
            'iconSets' => json_decode($resolvedTheme['iconSets'], true, 512, \JSON_THROW_ON_ERROR),
            'updatedAt' => \DateTime::createFromFormat(Defaults::STORAGE_DATE_TIME_FORMAT, $resolvedTheme['updatedAt']),
        ]);
    }

    public function saveRuntimeConfig(ThemeRuntimeConfig $config): void
    {
        $this->connection->executeStatement(<<<'SQL'
            REPLACE INTO `theme_runtime_config` (theme_id, resolved_config, script_files, style_files, icon_sets, updated_at)
            VALUES (:themeId, :resolvedConfig, :scriptFiles, :styleFiles, :iconSets, :updatedAt)
            SQL, [
            'themeId' => Uuid::fromHexToBytes($config->themeId),
            'resolvedConfig' => json_encode($config->resolvedConfig, \JSON_THROW_ON_ERROR),
            'scriptFiles' => json_encode($config->scriptFiles, \JSON_THROW_ON_ERROR),
            'styleFiles' => json_encode($config->styleFiles, \JSON_THROW_ON_ERROR),
            'iconSets' => json_encode($config->iconSets, \JSON_THROW_ON_ERROR),
            'updatedAt' => $config->updatedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function updateRuntimeConfig(string $themeId, string $themeTechnicalName, Context $context): void
    {
        $configCollection = $this->pluginRegistry->getConfigurations();
        $themeConfig = $configCollection->getByTechnicalName($themeTechnicalName);

        $resolvedFiles = $this->resolveThemeFiles($themeConfig, $configCollection);

        $runtimeConfig = new ThemeRuntimeConfig(
            $themeId,
            $this->themeService->getThemeConfiguration($themeId, false, $context),
            $resolvedFiles['js'],
            $resolvedFiles['css'],
            $this->prepareThemeIconSets($themeConfig),
            new \DateTime(),
        );

        $this->saveRuntimeConfig($runtimeConfig);
    }

    private function prepareThemeIconSets(StorefrontPluginConfiguration $themeConfig): array
    {
        $iconConfig = [];
        foreach ($themeConfig->getIconSets() as $pack => $path) {
            $iconConfig[$pack] = [
                'path' => $path,
                'namespace' => $themeConfig->getTechnicalName(),
            ];
        }

        return $iconConfig;
    }

    private function resolveThemeFiles(StorefrontPluginConfiguration $themeConfig, StorefrontPluginConfigurationCollection $configCollection): array
    {
        $resolvedFiles = $this->themeFileResolver->resolveFiles($themeConfig, $configCollection, false);

        return [
            'css' => [],
            'js' => $resolvedFiles[ThemeFileResolver::SCRIPT_FILES]->getPublicPaths('js'),
        ];
    }
}

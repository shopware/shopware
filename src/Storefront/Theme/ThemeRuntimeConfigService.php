<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;

/**
 * @internal
 */
#[Package('framework')]
class ThemeRuntimeConfigService
{
    public function __construct(
        private ThemeFileResolver $themeFileResolver,
        private StorefrontPluginRegistry $pluginRegistry,
        private ThemeService $themeService,
        private readonly Connection $connection,
    ) {
    }

    // todo: refactor separating db operations and runtime config resolving/enriching
    public function getResolvedRuntimeConfig(string $themeId): ?ThemeRuntimeConfig
    {
        $runtimeConfig = $this->getRuntimeConfig($themeId);

        if ($runtimeConfig === null) {
            return null;
        }

        if ($runtimeConfig->scriptFiles === null) {
            $runtimeConfig = $this->updateRuntimeConfig($runtimeConfig->themeId, $runtimeConfig->technicalName, Context::createDefaultContext(), true);
        }

        return $runtimeConfig;
    }

    public function getRuntimeConfigByName(string $technicalName): ?ThemeRuntimeConfig
    {
        $record = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT
                `theme_id`,
                `technical_name`,
                `resolved_config`,
                `view_inheritance`,
                `script_files`,
                `icon_sets`,
                `updated_at`
                FROM `theme_runtime_config`
                WHERE `technical_name` = :technicalName
            SQL,
            ['technicalName' => $technicalName],
        );

        if (!$record) {
            return null;
        }

        return $this->hydrateRecord($record);
    }

    // todo: check if can switch all usages to the technical name
    public function getRuntimeConfig(string $themeId): ?ThemeRuntimeConfig
    {
        $record = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT
                `theme_id`,
                `technical_name`,
                `resolved_config`,
                `view_inheritance`,
                `script_files`,
                `icon_sets`,
                `updated_at`
                FROM `theme_runtime_config`
                WHERE `theme_id` = :themeId
            SQL,
            ['themeId' => Uuid::fromHexToBytes($themeId)],
        );

        if (!$record) {
            return null;
        }

        return $this->hydrateRecord($record);
    }

    public function saveRuntimeConfig(ThemeRuntimeConfig $config): void
    {
        $this->connection->executeStatement(<<<'SQL'
            REPLACE INTO `theme_runtime_config` (theme_id, technical_name, resolved_config, view_inheritance, script_files, icon_sets, updated_at)
            VALUES (:themeId, :technicalName, :resolvedConfig, :viewInheritance, :scriptFiles, :iconSets, :updatedAt)
            SQL, [
            'themeId' => Uuid::fromHexToBytes($config->themeId),
            'technicalName' => $config->technicalName,
            'resolvedConfig' => json_encode($config->resolvedConfig, \JSON_THROW_ON_ERROR),
            'viewInheritance' => json_encode($config->viewInheritance, \JSON_THROW_ON_ERROR),
            'scriptFiles' => json_encode($config->scriptFiles, \JSON_THROW_ON_ERROR),
            'iconSets' => json_encode($config->iconSets, \JSON_THROW_ON_ERROR),
            'updatedAt' => $config->updatedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function updateRuntimeConfig(string $themeId, string $themeTechnicalName, Context $context, bool $resolveFiles = false): ThemeRuntimeConfig
    {
        $configCollection = $this->pluginRegistry->getConfigurations();
        $themeConfig = $configCollection->getByTechnicalName($themeTechnicalName);
        if ($themeConfig === null) {
            // todo: replace with specific exception (resulting in 500 error)
            throw ThemeException::couldNotFindThemeByName($themeTechnicalName);
        }

        $runtimeConfig = ThemeRuntimeConfig::fromArray([
            'themeId' => $themeId,
            'technicalName' => $themeTechnicalName,
            'resolvedConfig' => $this->themeService->getThemeConfiguration($themeId, false, $context),
            'viewInheritance' => $themeConfig->getViewInheritance(),
            'scriptFiles' => $resolveFiles ? $this->resolveThemeJs($themeConfig, $configCollection) : null,
            'iconSets' => $this->prepareThemeIconSets($themeConfig),
            'updatedAt' => new \DateTime(),
        ]);

        $this->saveRuntimeConfig($runtimeConfig);

        return $runtimeConfig;
    }

    /**
     * @todo: cache in class variable
     *
     * @return array<string>
     */
    public function getActiveThemeNames(): array
    {
        return $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT `technical_name`
                FROM `theme_runtime_config`
            SQL,
        );
    }

    /**
     * @param array<string, mixed> $record
     */
    private function hydrateRecord(array $record): ThemeRuntimeConfig
    {
        return ThemeRuntimeConfig::fromArray([
            'themeId' => Uuid::fromBytesToHex($record['theme_id']),
            'technicalName' => (string) $record['technical_name'],
            'resolvedConfig' => json_decode($record['resolved_config'], true, 512, \JSON_THROW_ON_ERROR),
            'viewInheritance' => json_decode($record['view_inheritance'], true, 512, \JSON_THROW_ON_ERROR),
            'scriptFiles' => json_decode($record['script_files'], true, 512, \JSON_THROW_ON_ERROR),
            'iconSets' => json_decode($record['icon_sets'], true, 512, \JSON_THROW_ON_ERROR),
            'updatedAt' => \DateTime::createFromFormat(Defaults::STORAGE_DATE_TIME_FORMAT, $record['updated_at']),
        ]);
    }

    /**
     * @return array<string, array{path: string, namespace: string}>
     */
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

    /**
     * @return array<string>
     */
    private function resolveThemeJs(StorefrontPluginConfiguration $themeConfig, StorefrontPluginConfigurationCollection $configCollection): array
    {
        $resolvedFiles = $this->themeFileResolver->resolveFiles($themeConfig, $configCollection, false);

        return $resolvedFiles[ThemeFileResolver::SCRIPT_FILES]->getPublicPaths('js');
    }
}

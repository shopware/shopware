<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore tested via an integration test
 */
#[Package('framework')]
class ThemeRuntimeConfigStorage
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function getByName(string $technicalName): ?ThemeRuntimeConfig
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

    public function getById(string $themeId): ?ThemeRuntimeConfig
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

    public function save(ThemeRuntimeConfig $config): void
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

    /**
     * @return array<string>
     */
    public function getActiveThemeNames(): array
    {
        return $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT DISTINCT `technical_name`
                FROM `theme_runtime_config`
                WHERE `technical_name` IS NOT NULL
            SQL,
        );
    }

    /**
     * Returns ids of theme copies (using the same theme implementation by duplicated config) for a given theme.
     *
     * @return array<string>
     */
    public function getCopiesIds(string $themeId): array
    {
        return $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT LOWER(HEX(`id`)) AS id
                FROM `theme`
                WHERE `parent_theme_id` = :themeId AND `technical_name` IS NULL
            SQL,
            ['themeId' => Uuid::fromHexToBytes($themeId)],
        );
    }

    /**
     * Returns ids of child themes and theme copies, recursively.
     *
     * @return array<string>
     */
    public function getChildThemeIds(string $parentThemeId): array
    {
        $processedThemeIds = [$parentThemeId];
        $childThemeIds = [];
        $pendingParentIds = [$parentThemeId];

        while (!empty($pendingParentIds)) {
            $directChildren = $this->connection->fetchFirstColumn(
                <<<'SQL'
                    SELECT LOWER(HEX(id)) as id FROM theme WHERE parent_theme_id IN (:parentIds)
                SQL,
                ['parentIds' => array_map(fn ($id) => Uuid::fromHexToBytes($id), $pendingParentIds)],
                [
                    'parentIds' => ArrayParameterType::STRING,
                ]
            );

            $pendingParentIds = [];
            foreach ($directChildren as $childId) {
                $childId = (string) $childId;

                // Skip if we've already processed this theme (prevents infinite loops)
                if (\in_array($childId, $processedThemeIds, true)) {
                    continue;
                }

                $processedThemeIds[] = $childId;
                $childThemeIds[] = $childId;
                $pendingParentIds[] = $childId;
            }
        }

        return $childThemeIds;
    }

    public function getThemeTechnicalName(string $themeId): ?string
    {
        $names = $this->connection->fetchAssociative('
            SELECT theme.technical_name as themeName, parentTheme.technical_name as parentThemeName
            FROM theme
                LEFT JOIN theme AS parentTheme ON parentTheme.id = theme.parent_theme_id
            WHERE theme.id = :id
        ', [
            'id' => Uuid::fromHexToBytes($themeId),
        ]);

        if ($names === false) {
            return null;
        }

        return $names['themeName'] ?? $names['parentThemeName'] ?? null;
    }

    public function getThemeIdByTechnicalName(string $technicalName): ?string
    {
        $themeId = $this->connection->fetchOne('
            SELECT LOWER(HEX(id)) FROM theme WHERE technical_name = :technicalName
        ', [
            'technicalName' => $technicalName,
        ]);

        return $themeId === false ? null : $themeId;
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
            'updatedAt' => \DateTime::createFromFormat(Defaults::STORAGE_DATE_TIME_FORMAT, $record['updated_at']) ?: null,
        ]);
    }
}

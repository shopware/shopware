<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('storefront')]
/**
 * @internal
 */
final class DatabaseSalesChannelThemeLoader
{
    /**
     * @deprecated tag:v6.7.0 - Will be removed in 6.7.0 as the cache key is not used anymore
     */
    final public const CACHE_KEY = 'sales-channel-themes';

    /**
     * @var array<string, array<int, string>>
     */
    private array $themes = [];

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<int, string>
     */
    public function load(string $salesChannelId): array
    {
        if (!empty($this->themes[$salesChannelId])) {
            return $this->themes[$salesChannelId];
        }

        return $this->readFromDB($salesChannelId);
    }

    public function reset(): void
    {
        $this->themes = [];
    }

    /**
     * @return array<int, string>
     */
    private function readFromDB(string $salesChannelId): array
    {
        $themes = $this->connection->fetchAssociative('
            SELECT LOWER(HEX(theme.id)) themeId, theme.technical_name as themeName, parentTheme.technical_name as parentThemeName, LOWER(HEX(parentTheme.parent_theme_id)) as grandParentThemeId
            FROM sales_channel
                LEFT JOIN theme_sales_channel ON sales_channel.id = theme_sales_channel.sales_channel_id
                LEFT JOIN theme ON theme_sales_channel.theme_id = theme.id
                LEFT JOIN theme AS parentTheme ON parentTheme.id = theme.parent_theme_id
            WHERE sales_channel.id = :salesChannelId
        ', [
            'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
        ]);

        if (\is_array($themes) && isset($themes['grandParentThemeId']) && \is_string($themes['grandParentThemeId'])) {
            $themes['grandParentNames'] = $this->getGrantParents($themes['grandParentThemeId']);
        }

        $usedThemes = array_filter([
            $themes['themeName'] ?? null,
            $themes['parentThemeName'] ?? null,
        ]);

        if (isset($themes['grandParentNames'])) {
            $usedThemes = array_merge($usedThemes, $themes['grandParentNames']);
        }

        return $this->themes[$salesChannelId] = $usedThemes ?: [];
    }

    /**
     * @return array<int, string>
     */
    private function getGrantParents(mixed $grandParentThemeId): array
    {
        $grandParents = $this->connection->fetchAssociative('
            SELECT theme.technical_name as themeName, parentTheme.technical_name as parentThemeName, LOWER(HEX(parentTheme.parent_theme_id)) as grandParentThemeId
            FROM theme
                LEFT JOIN theme AS parentTheme ON parentTheme.id = theme.parent_theme_id
            WHERE theme.id = :id
        ', [
            'id' => Uuid::fromHexToBytes($grandParentThemeId),
        ]);

        $filtered = array_filter([
            $grandParents['themeName'] ?? null,
            $grandParents['parentThemeName'] ?? null,
        ]);

        if (\is_array($grandParents) && isset($grandParents['grandParentThemeId']) && \is_string($grandParents['grandParentThemeId'])) {
            $filtered = array_merge($filtered, $this->getGrantParents($grandParents['grandParentThemeId']));
        }

        return $filtered;
    }
}

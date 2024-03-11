<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @deprecated tag:v6.7.0 - will be removed. Use DatabaseSalesChannelThemeLoader instead
 */
#[Package('storefront')]
class SalesChannelThemeLoader implements ResetInterface
{
    /**
     * @var array<string, array{themeId?: string, themeName?: string, parentThemeName?: string}>
     */
    private array $themes = [];

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array{themeId?: string, themeName?: string, parentThemeName?: string, grandParentNames?: array<string, mixed>}
     */
    public function load(string $salesChannelId): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.7.0.0')
        );

        if (!empty($this->themes[$salesChannelId])) {
            return $this->themes[$salesChannelId];
        }

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
            $themes['grandParentNames'] = $this->getGrandParents($themes['grandParentThemeId']);
        }

        return $this->themes[$salesChannelId] = $themes ?: [];
    }

    public function reset(): void
    {
        if (!Feature::isActive('v6.7.0.0')) { // reset interface does not work with triggerDeprecation
            Feature::triggerDeprecationOrThrow(
                'v6.7.0.0',
                Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.7.0.0')
            );
        }

        $this->themes = [];
    }

    /**
     * @return array<int, string>
     */
    private function getGrandParents(mixed $grandParentThemeId): array
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
            $filtered = array_merge($filtered, $this->getGrandParents($grandParents['grandParentThemeId']));
        }

        return $filtered;
    }
}

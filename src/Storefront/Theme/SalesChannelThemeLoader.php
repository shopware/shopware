<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

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
     * @return array{themeId?: string, themeName?: string, parentThemeName?: string}
     */
    public function load(string $salesChannelId): array
    {
        if (!empty($this->themes[$salesChannelId])) {
            return $this->themes[$salesChannelId];
        }

        $theme = $this->connection->fetchAssociative('
            SELECT LOWER(HEX(theme.id)) themeId, theme.technical_name as themeName, parentTheme.technical_name as parentThemeName
            FROM sales_channel
                LEFT JOIN theme_sales_channel ON sales_channel.id = theme_sales_channel.sales_channel_id
                LEFT JOIN theme ON theme_sales_channel.theme_id = theme.id
                LEFT JOIN theme AS parentTheme ON parentTheme.id = theme.parent_theme_id
            WHERE sales_channel.id = :salesChannelId
        ', [
            'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
        ]);

        return $this->themes[$salesChannelId] = $theme ?: [];
    }

    public function reset(): void
    {
        $this->themes = [];
    }
}

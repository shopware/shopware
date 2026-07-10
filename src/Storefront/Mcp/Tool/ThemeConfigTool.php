<?php declare(strict_types=1);

namespace Shopware\Storefront\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\ThemeService;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * This tool lives in the Storefront bundle because it depends on ThemeService,
 * which is a Storefront service. Placing it in Core/Framework would create an
 * inverted dependency (Core -> Storefront). The McpToolCompilerPass discovers
 * any service tagged shopware.mcp.tool regardless of bundle.
 */
#[McpTool(name: 'shopware-theme-config', description: 'Read or update theme appearance settings (colors, logos, fonts) for a sales channel. Use action "get" to read the current theme config. Use action "update" with a config JSON to change values; dryRun=true (default) previews changes. Pass a salesChannelId to scope to a specific channel (see shopware://sales-channels for IDs).')]
#[McpToolRequires('theme:read')]
#[McpToolRequires('theme:update')]
#[Package('framework')]
class ThemeConfigTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ThemeService $themeService,
        private readonly McpContextProvider $contextProvider,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(
        string $salesChannelId = '',
        string $action = 'get',
        string $config = '{}',
        bool $dryRun = true,
    ): string {
        if ($salesChannelId === '') {
            return $this->error('salesChannelId is required. Use the shopware://sales-channels resource to find available sales channel IDs.');
        }

        $context = $this->contextProvider->getContext();

        $requiredPrivileges = match ($action) {
            'get' => ['theme:read'],
            'update' => ['theme:read', 'theme:update'],
            default => [],
        };

        if ($error = $this->requirePrivilege($context, ...$requiredPrivileges)) {
            return $error;
        }

        $themeId = $this->resolveThemeId($salesChannelId);

        if ($themeId === null) {
            return $this->error(\sprintf('No theme assigned to sales channel "%s".', $salesChannelId));
        }

        return match ($action) {
            'get' => $this->handleGet($themeId, $context),
            'update' => $this->handleUpdate($themeId, $config, $dryRun, $context),
            default => $this->error(\sprintf('Unknown action "%s". Use "get" or "update".', $action)),
        };
    }

    private function handleGet(string $themeId, Context $context): string
    {
        try {
            $configuration = $this->themeService->getPlainThemeConfiguration($themeId, $context);
        } catch (\Throwable $e) {
            return $this->error('Failed to read theme config: ' . $e->getMessage());
        }

        return $this->success([
            'themeId' => $themeId,
            'config' => $configuration,
        ]);
    }

    private function handleUpdate(string $themeId, string $configJson, bool $dryRun, Context $context): string
    {
        /** @var array<string, array{value: mixed}> $configValues */
        $configValues = $this->decodeJsonOrError($configJson, 'config');
        if (\is_string($configValues)) {
            return $configValues;
        }

        if ($configValues === []) {
            return $this->error('Config must be a non-empty JSON object with key-value pairs, e.g. {"sw-color-brand-primary": {"value": "#0000ff"}}');
        }

        if ($dryRun) {
            return $this->success([
                'themeId' => $themeId,
                'configToApply' => $configValues,
                'note' => 'Dry-run preview only. Config key names are not validated against the theme schema.',
            ], ['dryRun' => true]);
        }

        try {
            $this->themeService->updateTheme($themeId, $configValues, null, $context);
        } catch (\Throwable $e) {
            return $this->error('Theme update failed: ' . $e->getMessage());
        }

        return $this->success([
            'themeId' => $themeId,
            'updatedKeys' => array_keys($configValues),
        ], ['dryRun' => false]);
    }

    private function resolveThemeId(string $salesChannelId): ?string
    {
        $result = $this->connection->fetchOne(
            'SELECT LOWER(HEX(theme_id)) FROM theme_sales_channel WHERE sales_channel_id = :id',
            ['id' => Uuid::fromHexToBytes($salesChannelId)],
        );

        return $result !== false ? (string) $result : null;
    }
}

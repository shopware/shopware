<?php declare(strict_types=1);

namespace Shopware\Storefront\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\ThemeService;

/**
 * @experimental stableVersion:v6.8.0
 *
 * This tool lives in the Storefront bundle because it depends on ThemeService,
 * which is a Storefront service. Placing it in Core/Framework would create an
 * inverted dependency (Core -> Storefront). The McpToolCompilerPass discovers
 * any service tagged shopware.mcp.tool regardless of bundle.
 */
#[Package('discovery')]
#[McpTool(
    name: 'shopware-theme-config',
    description: 'Read or update theme appearance settings (colors, logos, fonts) for a sales channel. Use action "get" to read the current theme config. Use action "update" with a config JSON to change values; dryRun=true (default) previews changes. salesChannelId accepts either the sales channel UUID or its name as shown in the admin, e.g. "Storefront". See shopware://sales-channels for the full list.'
)]
#[McpToolGroup('theme')]
#[McpToolRequires('theme:read')]
#[McpToolRequires('theme:update')]
class ThemeConfigTool extends McpToolResponse
{
    /**
     * Upper bound for the sales channel names listed in a "not found" error.
     */
    private const MAX_SUGGESTED_NAMES = 20;

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
        if ($action !== 'get' && $action !== 'update') {
            return $this->error(\sprintf('Unknown action "%s". Use "get" or "update".', $action));
        }

        if ($salesChannelId === '') {
            return $this->error('salesChannelId is required. Use the shopware://sales-channels resource to find available sales channel IDs.');
        }

        $context = $this->contextProvider->getContext();

        $requiredPrivileges = $action === 'update'
            ? ['theme:read', 'theme:update']
            : ['theme:read'];

        if ($error = $this->requirePrivilege($context, ...$requiredPrivileges)) {
            return $error;
        }

        // Resolving runs after the privilege check so the error hints cannot enumerate sales
        // channel names. Infrastructure failures stay uncaught on purpose: per the McpToolResponse
        // contract only business errors become an error envelope.
        $resolved = $this->resolveSalesChannelId($salesChannelId);

        if (isset($resolved['error'])) {
            return $this->error($resolved['error']);
        }

        $themeId = $this->resolveThemeId($resolved['id']);

        if ($themeId === null) {
            return $this->error(\sprintf('No theme assigned to sales channel "%s".', $salesChannelId));
        }

        return $action === 'get'
            ? $this->handleGet($themeId, $context)
            : $this->handleUpdate($themeId, $config, $dryRun, $context);
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

    /**
     * Accepts either a sales channel UUID or its name, because agents typically know the name
     * shown in the admin, not the ID.
     *
     * @return array{id: string}|array{error: string}
     */
    private function resolveSalesChannelId(string $input): array
    {
        $input = trim($input);
        $ids = $this->fetchSalesChannelIds($input);

        if (\count($ids) === 1) {
            return ['id' => $ids[0]];
        }

        if ($ids === []) {
            return ['error' => \sprintf(
                'Sales channel "%s" not found. Available sales channels: %s. Pass one of these names or a sales channel UUID (see shopware://sales-channels).',
                $input,
                $this->listSalesChannelNames(),
            )];
        }

        return ['error' => \sprintf(
            'Sales channel "%s" is ambiguous, %d channels match it. Pass one of these IDs instead: %s.',
            $input,
            \count($ids),
            implode(', ', $ids),
        )];
    }

    private function listSalesChannelNames(): string
    {
        $names = $this->fetchSalesChannelNames();

        if ($names === []) {
            return 'none';
        }

        $shown = \array_slice($names, 0, self::MAX_SUGGESTED_NAMES);
        $list = implode(', ', array_map(static fn (string $name): string => '"' . $name . '"', $shown));

        if (\count($names) > self::MAX_SUGGESTED_NAMES) {
            $list .= \sprintf(' and %d more', \count($names) - self::MAX_SUGGESTED_NAMES);
        }

        return $list;
    }

    /**
     * Matches the input against the ID and the name in one query so neither form shadows the
     * other. sales_channel_translation.name uses utf8mb4_unicode_ci, so the name match is
     * case-insensitive through the column collation. DISTINCT collapses the one row per language
     * a single channel has.
     *
     * @return list<string>
     */
    private function fetchSalesChannelIds(string $input): array
    {
        // Uuid::isValid() only matches lowercase hex, but agents copy uppercase IDs.
        $uuid = strtolower($input);

        return array_map(
            static fn (mixed $id): string => (string) $id,
            $this->connection->fetchFirstColumn(
                <<<'SQL'
                    SELECT DISTINCT LOWER(HEX(`sc`.`id`))
                    FROM `sales_channel` `sc`
                    LEFT JOIN `sales_channel_translation` `sct` ON `sct`.`sales_channel_id` = `sc`.`id`
                    WHERE `sc`.`id` = :id OR `sct`.`name` = :name
                    SQL,
                [
                    // A non-UUID input binds NULL, which no ID can equal.
                    'id' => Uuid::isValid($uuid) ? Uuid::fromHexToBytes($uuid) : null,
                    'name' => $input,
                ],
            ),
        );
    }

    /**
     * @return list<string>
     */
    private function fetchSalesChannelNames(): array
    {
        return array_map(
            static fn (mixed $name): string => (string) $name,
            $this->connection->fetchFirstColumn(
                'SELECT DISTINCT `name` FROM `sales_channel_translation` WHERE `name` IS NOT NULL ORDER BY `name`',
            ),
        );
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

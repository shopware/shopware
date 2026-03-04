<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-system-config-read', description: 'Read Shopware system configuration values. Provide a config key domain (e.g. "core.listing") to get all values under that prefix, or a full key for a single value. Optionally scope to a sales channel.')]
#[Package('framework')]
class SystemConfigReadTool
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function __invoke(string $key, ?string $salesChannelId = null): string
    {
        if (str_contains($key, '.') && substr_count($key, '.') >= 2) {
            $value = $this->systemConfigService->get($key, $salesChannelId);

            return json_encode(['key' => $key, 'value' => $value], \JSON_THROW_ON_ERROR);
        }

        $domain = $this->systemConfigService->getDomain($key, $salesChannelId);

        return json_encode(['domain' => $key, 'values' => $domain], \JSON_THROW_ON_ERROR);
    }
}
